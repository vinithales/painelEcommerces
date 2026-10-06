<?php

namespace Tests\Feature;

use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreVerificationSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_pages_render_current_verification_result(): void
    {
        $store = Store::create([
            'name' => 'Loja monitorada',
            'platform' => Store::PLATFORM_WOOCOMMERCE,
            'store_url' => 'https://example.com',
        ]);
        $definition = CheckDefinition::create([
            'name' => 'Disponibilidade HTTP',
            'level' => 'API',
            'executor_type' => 'http',
            'configuration' => ['path' => '/'],
            'assertions' => [['field' => 'status_code', 'operator' => 'equals', 'expected' => 200]],
        ]);
        $assignment = CheckAssignment::create([
            'check_definition_id' => $definition->id,
            'store_id' => $store->id,
            'interval_minutes' => 5,
            'enabled' => true,
        ]);
        $assignment->executions()->create([
            'definition_snapshot' => $definition->only(['name', 'level', 'executor_type', 'configuration', 'assertions']),
            'status' => 'completed',
            'outcome' => 'failed',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('stores.index'))
            ->assertOk()
            ->assertSee('Loja monitorada')
            ->assertSee('CRITICAL');

        $this->get(route('stores.overview', $store))
            ->assertOk()
            ->assertSee('Disponibilidade HTTP')
            ->assertSee('Falhas recentes');
    }

    public function test_store_check_history_shows_empty_state_and_execution_details(): void
    {
        $store = Store::create([
            'name' => 'Loja com histórico',
            'platform' => Store::PLATFORM_WOOCOMMERCE,
            'store_url' => 'https://example.com',
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('stores.overview', $store))
            ->assertOk()
            ->assertSee(route('stores.check-history', $store));

        $this->get(route('stores.check-history', $store))
            ->assertOk()
            ->assertSee('Nenhuma execução de verificação encontrada.');

        $definition = CheckDefinition::create([
            'name' => 'Nome atual',
            'level' => 'SITE',
            'executor_type' => 'http',
            'configuration' => ['path' => '/'],
            'assertions' => [],
        ]);
        $assignment = CheckAssignment::create([
            'check_definition_id' => $definition->id,
            'store_id' => $store->id,
            'interval_minutes' => 5,
            'enabled' => true,
        ]);
        $assignment->executions()->create([
            'definition_snapshot' => ['name' => 'Nome na execução'],
            'status' => 'error',
            'error_message' => 'Falha de conexão',
        ]);

        $this->get(route('stores.check-history', $store))
            ->assertOk()
            ->assertSee('Nome na execução')
            ->assertSee('Aguardando')
            ->assertSee('Falha de conexão')
            ->assertDontSee('Nome atual');
    }
}
