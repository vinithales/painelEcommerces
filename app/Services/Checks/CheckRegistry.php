<?php

namespace App\Services\Checks;

use App\Models\StoreCheck;
use App\Support\Redactor;

class CheckRegistry
{
    public function resolve(string $type): CheckStrategy
    {
        if (! in_array($type, CheckConfiguration::TYPES, true)) {
            throw new \InvalidArgumentException('Tipo de check não suportado.');
        }

        return app(match ($type) {
            'API_HEALTH' => ApiHealthCheck::class,'MAGENTO_CRON' => MagentoCronCheck::class,default => SiteCheck::class
        });
    }

    public function evaluate(StoreCheck $check): CheckResult
    {
        try {
            app(CheckConfiguration::class)->validate($check->check_type, $check->configuration);

            return $this->resolve($check->check_type)->run($check);
        } catch (\Throwable $e) {
            return new CheckResult('UNKNOWN', 'Não foi possível executar a verificação.', [], [], Redactor::clean($e->getMessage()));
        }
    }
}
