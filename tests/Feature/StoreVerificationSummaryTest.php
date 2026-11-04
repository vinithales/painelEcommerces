<?php

namespace Tests\Feature;

use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\Store;
use App\Models\User;
use App\Livewire\Stores\StoreCheckHistory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_history_details_show_saved_results_and_reject_another_stores_execution(): void
    {
        $store = Store::create(['name' => 'Loja A', 'platform' => Store::PLATFORM_WOOCOMMERCE, 'store_url' => 'https://a.example.com']);
        $otherStore = Store::create(['name' => 'Loja B', 'platform' => Store::PLATFORM_WOOCOMMERCE, 'store_url' => 'https://b.example.com']);
        $definition = CheckDefinition::create([
            'name' => 'Disponibilidade',
            'level' => 'SITE',
            'executor_type' => 'http',
            'configuration' => ['path' => '/'],
            'assertions' => [],
        ]);
        $assignment = CheckAssignment::create(['check_definition_id' => $definition->id, 'store_id' => $store->id, 'interval_minutes' => 5, 'enabled' => true]);
        $otherAssignment = CheckAssignment::create(['check_definition_id' => $definition->id, 'store_id' => $otherStore->id, 'interval_minutes' => 5, 'enabled' => true]);
        $execution = $assignment->executions()->create([
            'definition_snapshot' => ['name' => 'Nome histórico', 'level' => 'SITE', 'executor_type' => 'http'],
            'status' => 'completed',
            'outcome' => 'failed',
            'result' => [
                'observed' => ['status_code' => 503, 'response_time' => 120, 'url' => 'https://a.example.com/', 'body' => '<h1>Indisponível</h1>'],
                'assertions' => [['field' => 'status_code', 'expected' => 200, 'actual' => 503, 'passed' => false]],
            ],
        ]);
        $otherExecution = $otherAssignment->executions()->create(['definition_snapshot' => ['name' => 'Outra loja'], 'status' => 'pending']);

        $component = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(StoreCheckHistory::class, ['store' => $store])
            ->call('openDetails', $execution->id)
            ->assertSet('selectedExecutionId', $execution->id)
            ->assertSee('Resposta observada')
            ->assertSee('Nome histórico')
            ->assertSee('503')
            ->assertSee('Indisponível')
            ->assertDontSeeHtml('<h1>Indisponível</h1>');

        $component->call('closeDetails')->assertSet('selectedExecutionId', null)->assertDontSee('Resposta observada');

        $this->expectException(ModelNotFoundException::class);
        $component->call('openDetails', $otherExecution->id);
    }
}
