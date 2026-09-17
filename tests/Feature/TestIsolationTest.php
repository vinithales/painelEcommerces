<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestIsolationTest extends TestCase
{
    public function test_test_database_is_always_in_memory_even_with_docker_environment(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('testing', config('app.env'));
    }
}
