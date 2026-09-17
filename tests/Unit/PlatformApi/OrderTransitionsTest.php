<?php

namespace Tests\Unit\PlatformApi;

use App\Services\PlatformApi\Adapters\Magento2ApiAdapter;
use App\Services\PlatformApi\Adapters\WooCommerceApiAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class OrderTransitionsTest extends TestCase
{
    private function client(array $responses, array &$history): Client
    {
        $stack = HandlerStack::create(new MockHandler(array_map(fn ($r) => new Response(200, [], json_encode($r)), $responses)));
        $stack->push(Middleware::history($history));

        return new Client(['handler' => $stack]);
    }

    public function test_magento_cancel_uses_real_endpoint_and_reads_final_state(): void
    {
        $history = [];
        $adapter = new Magento2ApiAdapter('https://example.com', 'k', 's', 't', 'ts', client: $this->client([['entity_id' => 2, 'state' => 'new', 'status' => 'pending'], true, ['entity_id' => 2, 'state' => 'canceled', 'status' => 'canceled']], $history));
        $result = $adapter->updateOrder('2', ['operation' => 'cancel']);
        $this->assertSame('canceled', $result['data']['state']);
        $this->assertSame('POST', $history[1]['request']->getMethod());
        $this->assertSame('orders/2/cancel', (string) $history[1]['request']->getUri());
        $this->assertCount(3, $history);
    }

    public function test_magento_rejects_direct_status_without_http(): void
    {
        $history = [];
        $adapter = new Magento2ApiAdapter('https://example.com', 'k', 's', 't', 'ts', client: $this->client([], $history));
        try {
            $adapter->updateOrder('2', ['status' => 'complete']);
            $this->fail('Expected rejection');
        } catch (\RuntimeException $e) {
            $this->assertCount(0, $history);
        }
    }

    public function test_magento_rejects_invalid_state_transition(): void
    {
        $history = [];
        $adapter = new Magento2ApiAdapter('https://example.com', 'k', 's', 't', 'ts', client: $this->client([['entity_id' => 2, 'state' => 'complete', 'status' => 'complete']], $history));
        $this->expectException(\RuntimeException::class);
        $adapter->updateOrder('2', ['operation' => 'cancel']);
    }

    public function test_woo_status_is_confirmed_with_followup_read(): void
    {
        $history = [];
        $adapter = new WooCommerceApiAdapter('https://example.com', 'k', 's', $this->client([['id' => 2, 'status' => 'processing'], ['id' => 2, 'status' => 'completed'], ['id' => 2, 'status' => 'completed']], $history));
        $this->assertSame('completed', $adapter->updateOrder('2', ['status' => 'completed'])['data']['status']);
        $this->assertCount(3, $history);
        $this->assertSame(['status' => 'completed'], json_decode((string) $history[1]['request']->getBody(), true));
    }

    public function test_woo_rejects_refund_disguised_as_status(): void
    {
        $history = [];
        $adapter = new WooCommerceApiAdapter('https://example.com', 'k', 's', $this->client([['id' => 2, 'status' => 'processing']], $history));
        $this->expectException(\RuntimeException::class);
        $adapter->updateOrder('2', ['status' => 'refunded']);
    }

    public function test_product_paths_encode_sku_and_translate_magento_fields(): void
    {
        $history = [];
        $adapter = new Magento2ApiAdapter('https://example.com', 'k', 's', 't', 'ts', client: $this->client([['id' => 42, 'sku' => 'A/B #1', 'status' => 2]], $history));
        $adapter->updateProduct('A/B #1', ['status' => 'disabled', 'description' => 'Nova']);
        $this->assertSame('products/A%2FB%20%231', (string) $history[0]['request']->getUri());
        $payload = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame(['product' => ['status' => 2, 'custom_attributes' => [['attribute_code' => 'description', 'value' => 'Nova']]]], $payload);
    }
}
