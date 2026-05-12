<?php

namespace App\Services\IntegrationCheckers\Checks;

use App\Models\Store;

interface StoreCheck
{
    public function key(): string;

    public function supports(Store $store): bool;

    public function check(Store $store): array;
}