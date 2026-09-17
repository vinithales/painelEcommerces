<?php

namespace App\Jobs;

use App\Mail\OperationalCheckAlert;
use App\Models\StoreCheckAlert;
use App\Support\Redactor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendCheckAlertJob implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public int $alertId) {}

    public function handle(): void
    {
        $alert = StoreCheckAlert::with('check.store')->find($this->alertId);
        if (! $alert || $alert->sent_at) {
            return;
        }
        $recipients = config('operations.recipients', []);
        if (! $recipients) {
            $alert->update(['error' => 'CHECK_ALERT_RECIPIENTS não configurado.']);

            return;
        }
        try {
            Mail::to($recipients)->send(new OperationalCheckAlert($alert));
            $alert->update(['sent_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            $alert->update(['error' => Redactor::clean($e->getMessage())]);
            throw $e;
        }
    }
}
