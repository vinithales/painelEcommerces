<?php

namespace App\Services\Operations;

use App\Models\Store;
use App\Services\Checks\CheckConfiguration;
use App\Services\Checks\MagentoCronCheck;
use App\Services\Infrastructure\ServerConnectionFactory;
use App\Services\PlatformApi\PlatformApiAdapterFactory;
use App\Support\Redactor;

class SupportOperations
{
    public const API = ['api_auth', 'product_id', 'product_sku', 'order_id', 'store_info'];

    public const SERVER = ['server_status', 'server_logs', 'server_cron'];

    public function run(Store $store, string $operation, array $parameters = []): array
    {
        if (in_array($operation, self::SERVER, true)) {
            $server = app(ServerConnectionFactory::class)->make($store);
            if (! $store->isMagento2() && $operation !== 'server_status') {
                throw new \RuntimeException('Operação disponível para Magento 2.');
            }
            if ($operation === 'server_logs') {
                return $server->logs($parameters['file'] ?? '', isset($parameters['cursor']) ? (int) $parameters['cursor'] : null, (int) config('operations.log_bytes'));
            }
            if ($operation === 'server_status') {
                return $server->status();
            }
            $config = CheckConfiguration::defaults('MAGENTO_CRON');
            $result = app(MagentoCronCheck::class)->evaluate($server->cron($config['running_minutes']), $config);

            return ['status' => $result->status, 'summary' => $result->summary, 'metrics' => $result->metrics];
        }
        if (! in_array($operation, self::API, true)) {
            throw new \InvalidArgumentException('Operação não autorizada.');
        }
        $adapter = app(PlatformApiAdapterFactory::class)->make($store);
        $id = (string) ($parameters['identifier'] ?? '');
        try {
            $body = match ($operation) {
                'api_auth' => $adapter->listProducts([], 1, 1),
                'product_id' => $store->isMagento2() ? $this->magentoProductId($adapter, $id) : $adapter->getProduct($id),
                'product_sku' => $store->isMagento2() ? $adapter->getProduct($id) : $adapter->listProducts(['sku' => $id], 1, 10),
                'order_id' => $adapter->getOrder($id),
                'store_info' => $adapter->storeInformation()
            };

            return $adapter->telemetry() + ['body' => $body, 'status' => 'OK'];
        } catch (\Throwable $e) {
            return $adapter->telemetry() + ['status' => 'FAILED', 'error' => Redactor::clean($e->getMessage())];
        }
    }

    private function magentoProductId($adapter, string $id): array
    {
        $list = $adapter->listProducts(['id' => $id], 1, 1);
        if (empty($list['data'])) {
            throw new \RuntimeException('Produto não encontrado.');
        }

        return $adapter->getProduct($list['data'][0]['sku']);
    }
}
