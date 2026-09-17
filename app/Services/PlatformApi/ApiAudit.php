<?php

namespace App\Services\PlatformApi;

use App\Models\ApiOperationLog;
use App\Models\Store;
use App\Support\Redactor;

class ApiAudit
{
    public static function call(Store $store, string $operation, string $resource, ?string $id, array $payload, callable $call): array
    {
        try {
            $result = $call();
            ApiOperationLog::create(['store_id' => $store->id, 'operation_type' => $operation, 'resource_type' => $resource, 'resource_id' => $id, 'payload' => $payload, 'response' => $operation === 'list' ? ['total' => $result['total'] ?? null] : ($result['data'] ?? $result), 'status' => 'ok']);

            return $result;
        } catch (\Throwable $e) {
            ApiOperationLog::create(['store_id' => $store->id, 'operation_type' => $operation, 'resource_type' => $resource, 'resource_id' => $id, 'payload' => $payload, 'status' => 'failed', 'error_message' => Redactor::clean($e->getMessage())]);
            throw $e;
        }
    }
}
