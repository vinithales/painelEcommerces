<?php

namespace Tests\Feature\Checks;

use App\Models\Store;
use App\Services\IntegrationCheckers\Checks\IndexCheck;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class IndexCheckTest extends TestCase
{
    private function makeCheck(array $responses): IndexCheck
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        return new IndexCheck($client);
    }

    private function makeStore(string $url = 'https://example.com'): Store
    {
        return new Store(['store_url' => $url]);
    }

    private function htmlWithMeta(string $content): string
    {
        return '<html><head><meta name="robots" content="' . $content . '"></head><body></body></html>';
    }

    // --- key / supports ---

    public function test_key_returns_indexability(): void
    {
        $this->assertSame('indexability', (new IndexCheck())->key());
    }

    public function test_supports_returns_true_when_store_url_is_set(): void
    {
        $this->assertTrue((new IndexCheck())->supports($this->makeStore('https://example.com')));
    }

    public function test_supports_returns_false_when_store_url_is_empty(): void
    {
        $this->assertFalse((new IndexCheck())->supports($this->makeStore('')));
    }

    // --- indexable (no directives) ---

    public function test_returns_indexable_when_no_directives(): void
    {
        $check = $this->makeCheck([new Response(200, [], '<html><head></head><body></body></html>')]);

        $result = $check->check($this->makeStore());

        $this->assertSame('indexable', $result['status']);
        $this->assertNull($result['error']);
        $this->assertSame([], $result['directives']);
    }

    // --- noindex ---

    public function test_returns_noindex_when_meta_noindex(): void
    {
        $check = $this->makeCheck([new Response(200, [], $this->htmlWithMeta('noindex'))]);

        $result = $check->check($this->makeStore());

        $this->assertSame('noindex', $result['status']);
        $this->assertStringContainsString('noindex', $result['error']);
    }

    public function test_returns_noindex_nofollow_when_meta_noindex_nofollow(): void
    {
        $check = $this->makeCheck([new Response(200, [], $this->htmlWithMeta('noindex, nofollow'))]);

        $result = $check->check($this->makeStore());

        $this->assertSame('noindex_nofollow', $result['status']);
        $this->assertStringContainsString('noindex', $result['error']);
        $this->assertStringContainsString('nofollow', $result['error']);
    }

    // --- nofollow ---

    public function test_returns_nofollow_when_meta_nofollow(): void
    {
        $check = $this->makeCheck([new Response(200, [], $this->htmlWithMeta('nofollow'))]);

        $result = $check->check($this->makeStore());

        $this->assertSame('nofollow', $result['status']);
        $this->assertStringContainsString('nofollow', $result['error']);
    }

    // --- index only ---

    public function test_returns_index_when_meta_index(): void
    {
        $check = $this->makeCheck([new Response(200, [], $this->htmlWithMeta('index'))]);

        $result = $check->check($this->makeStore());

        $this->assertSame('index', $result['status']);
        $this->assertNull($result['error']);
    }

    // --- index + nofollow ---

    public function test_returns_index_nofollow_when_meta_index_nofollow(): void
    {
        $check = $this->makeCheck([new Response(200, [], $this->htmlWithMeta('index, nofollow'))]);

        $result = $check->check($this->makeStore());

        $this->assertSame('index_nofollow', $result['status']);
    }

    // --- X-Robots-Tag header ---

    public function test_detects_noindex_from_x_robots_tag_header(): void
    {
        $check = $this->makeCheck([
            new Response(200, ['X-Robots-Tag' => 'noindex'], '<html><head></head><body></body></html>'),
        ]);

        $result = $check->check($this->makeStore());

        $this->assertSame('noindex', $result['status']);
        $this->assertContains('noindex', $result['directives']);
    }

    // --- X-Robots-Tag takes precedence over (or combines with) meta ---

    public function test_combines_directives_from_meta_and_x_robots_tag(): void
    {
        $check = $this->makeCheck([
            new Response(200, ['X-Robots-Tag' => 'nofollow'], $this->htmlWithMeta('noindex')),
        ]);

        $result = $check->check($this->makeStore());

        $this->assertSame('noindex_nofollow', $result['status']);
        $this->assertContains('noindex', $result['directives']);
        $this->assertContains('nofollow', $result['directives']);
    }

    // --- special directives in error message ---

    public function test_error_includes_noarchive_directive(): void
    {
        $check = $this->makeCheck([new Response(200, [], $this->htmlWithMeta('noarchive'))]);

        $result = $check->check($this->makeStore());

        $this->assertStringContainsString('noarchive', $result['error']);
    }

    public function test_error_includes_max_snippet_with_value(): void
    {
        $check = $this->makeCheck([new Response(200, [], $this->htmlWithMeta('max-snippet:-1'))]);

        $result = $check->check($this->makeStore());

        $this->assertStringContainsString('max-snippet:-1', $result['error']);
    }

    // --- request failure ---

    public function test_returns_failed_on_request_exception(): void
    {
        $exception = new RequestException('Connection timeout', new Request('GET', 'https://example.com'));
        $check = $this->makeCheck([$exception]);

        $result = $check->check($this->makeStore());

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('HTTP request failed', $result['error']);
        $this->assertSame([], $result['directives']);
    }

    // --- case-insensitive meta name ---

    public function test_meta_robots_name_is_case_insensitive(): void
    {
        $html = '<html><head><meta name="ROBOTS" content="noindex"></head><body></body></html>';
        $check = $this->makeCheck([new Response(200, [], $html)]);

        $result = $check->check($this->makeStore());

        $this->assertSame('noindex', $result['status']);
    }
}
