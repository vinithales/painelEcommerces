<?php

namespace App\Services\Checks;

use App\Models\StoreCheck;

interface CheckStrategy
{
    public function run(StoreCheck $check): CheckResult;
}
