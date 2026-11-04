<?php

namespace App\Services\Checks;

use App\Models\CheckExecution;

class CheckExecutionRetentionService
{
    public function clean(): int
    {
        $cleaned = 0;

        CheckExecution::query()
            ->whereIn('status', ['completed', 'error'])
            ->whereNotNull('finished_at')
            ->where('finished_at', '<', now()->subDays(90))
            ->chunkById(100, function ($executions) use (&$cleaned) {
                foreach ($executions as $execution) {
                    $result = $execution->result;

                    if (!is_array($result)) {
                        continue;
                    }
                    unset($result['observed']['body']); // Remove o corpo da resposta do histórico
    
                    foreach ($result['assertions'] ?? [] as $index => $assertion) {
                        if (($assertion['field'] ?? null) === 'body') {
                            unset($result['assertions'][$index]['actual']);
                        }
                    }

                    $execution->result = $result;

                    if ($execution->isDirty('result')) {
                        $execution->save();
                        $cleaned++;
                    }
                }
            });
        return $cleaned;

    }
}