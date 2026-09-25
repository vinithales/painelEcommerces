<?php

namespace Tests\Feature;

use App\Models\CheckDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_configuration_and_assertions_as_arrays(): void
    {
        $configuration = [
            'method' => 'GET',
            'timeout_seconds' => 10,
            'headers' => ['Accept' => 'application/json'],
        ];
        $assertions = [
            'status_code' => 200,
            'body_contains' => 'healthy',
        ];

        $definition = CheckDefinition::create([
            'name' => 'Saúde da API',
            'level' => 'API',
            'executor_type' => 'http',
            'configuration' => $configuration,
            'assertions' => $assertions,
        ])->fresh();

        $this->assertSame('Saúde da API', $definition->name);
        $this->assertSame('API', $definition->level);
        $this->assertSame('http', $definition->executor_type);
        $this->assertSame($configuration, $definition->configuration);
        $this->assertSame($assertions, $definition->assertions);
    }
}
