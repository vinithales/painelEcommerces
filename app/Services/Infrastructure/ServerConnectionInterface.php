<?php

namespace App\Services\Infrastructure;

interface ServerConnectionInterface
{
    public function logs(string $file, ?int $cursor = null, int $bytes = 65536): array;

    public function cron(int $runningMinutes): array;

    public function status(): array;
}
