<?php

namespace App\Services\Checks;

final class CheckResult
{
    public function __construct(public readonly string $status, public readonly string $summary, public readonly array $details = [], public readonly array $metrics = [], public readonly ?string $error = null) {}
}
