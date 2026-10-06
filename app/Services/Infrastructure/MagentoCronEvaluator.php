<?php

namespace App\Services\Infrastructure;

use Carbon\Carbon;

class MagentoCronEvaluator
{
    public const DEFAULTS = [
        'stale_minutes' => 15,
        'running_minutes' => 30,
        'max_pending' => 100,
        'max_missed' => 0,
        'max_errors' => 0,
    ];

    public function evaluate(array $metrics, array $configuration, string $severity = 'CRITICAL'): array
    {
        foreach (['last_execution', 'pending', 'missed', 'error', 'stuck_running', 'overdue'] as $field) {
            if (! array_key_exists($field, $metrics)) {
                throw new \RuntimeException('Métrica de cron ausente: '.$field);
            }
        }

        $stale = ! $metrics['last_execution']
            || Carbon::parse($metrics['last_execution'], 'UTC')->lt(now()->subMinutes($configuration['stale_minutes']));
        $failed = $stale || $metrics['pending'] > $configuration['max_pending']
            || $metrics['missed'] > $configuration['max_missed']
            || $metrics['error'] > $configuration['max_errors'] || $metrics['stuck_running'] > 0;

        return [
            'status' => $failed ? $severity : 'OK',
            'summary' => $stale ? 'Cron sem execução recente.' : ($failed ? 'Cron apresenta backlog, erros ou jobs travados.' : 'Cron executando dentro dos limites.'),
            'metrics' => $metrics,
        ];
    }
}
