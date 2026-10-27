<?php

namespace App\Services\Checks;

use App\Models\CheckExecution;

class RecoverAbandonedCheckExecutionsService
{
    public const ABANDONED_AFTER_MINUTES = 5;

    public function recover(): int
    {
        $now = now();

        // Apply the eligibility conditions in the UPDATE itself so a completed
        // execution cannot be overwritten between selection and recovery.
        return CheckExecution::query()
            ->where('status', 'running')
            ->whereNotNull('started_at')
            ->where('started_at', '<', $now->copy()->subMinutes(self::ABANDONED_AFTER_MINUTES))
            ->whereNull('finished_at')
            ->update([
                'status' => 'error',
                'outcome' => null,
                'error_message' => 'Execução interrompida: permaneceu em execução por mais de 5 minutos sem finalizar.',
                'finished_at' => $now,
            ]);
    }
}
