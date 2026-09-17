<?php

namespace App\Services\Checks;

use App\Models\StoreCheck;
use App\Services\PlatformApi\PlatformApiAdapterFactory;
use App\Support\Redactor;

class ApiHealthCheck implements CheckStrategy
{
    public function __construct(private readonly PlatformApiAdapterFactory $factory) {}

    public function run(StoreCheck $check): CheckResult
    {
        $start = microtime(true);
        try {
            $this->factory->make($check->store)->listProducts([], 1, 1);
        } catch (\RuntimeException $e) {
            return new CheckResult($check->severity, 'API não confirmou autenticação.', [], [], Redactor::clean($e->getMessage()));
        }

        return new CheckResult('OK', 'Autenticação e leitura confirmadas.', [], ['response_ms' => (int) ((microtime(true) - $start) * 1000)]);
    }
}
