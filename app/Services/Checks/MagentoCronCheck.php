<?php

namespace App\Services\Checks;

use App\Models\StoreCheck;
use App\Services\Infrastructure\ServerConnectionFactory;
use Carbon\Carbon;

class MagentoCronCheck implements CheckStrategy
{
    public function __construct(private readonly ServerConnectionFactory $factory) {}

    public function run(StoreCheck $check): CheckResult
    {
        if (! $check->store->isMagento2()) {
            return new CheckResult('UNKNOWN', 'Cron Magento exige uma loja Magento 2.');
        }

        return $this->evaluate($this->factory->make($check->store)->cron($check->configuration['running_minutes']), $check->configuration, $check->severity);
    }

    public function evaluate(array $metrics, array $c, string $severity = 'CRITICAL'): CheckResult
    {
        foreach (['last_execution', 'pending', 'missed', 'error', 'stuck_running', 'overdue'] as $field) {
            if (! array_key_exists($field, $metrics)) {
                throw new \RuntimeException('Métrica de cron ausente: '.$field);
            }
        }
        $stale = ! $metrics['last_execution'] || Carbon::parse($metrics['last_execution'], 'UTC')->lt(now()->subMinutes($c['stale_minutes']));
        $fail = $stale || $metrics['pending'] > $c['max_pending'] || $metrics['missed'] > $c['max_missed'] || $metrics['error'] > $c['max_errors'] || $metrics['stuck_running'] > 0;

        return new CheckResult($fail ? $severity : 'OK', $stale ? 'Cron sem execução recente.' : ($fail ? 'Cron apresenta backlog, erros ou jobs travados.' : 'Cron executando dentro dos limites.'), [], $metrics);
    }
}
