<?php

namespace Tests\Feature;

use App\Livewire\Stores\CheckDefinitions;
use App\Livewire\Stores\CheckReports;
use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\CheckExecution;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_shows_latest_results_conditions_and_scoped_counts(): void
    {
        $definition = $this->definition('Cache HIT');
        $failed = $this->assignment($definition, 'Loja MISS');
        $this->execution($failed, 'completed', 'failed', [
            'observed' => ['cache_status' => 'MISS'],
            'assertions' => [['field' => 'cache_status', 'operator' => 'equals', 'expected' => 'HIT', 'actual' => 'MISS', 'passed' => false]],
        ]);
        $recovered = $this->assignment($definition, 'Loja recuperada');
        $this->execution($recovered, 'completed', 'failed');
        $this->execution($recovered, 'completed', 'success');
        $error = $this->assignment($definition, 'Loja com erro');
        $this->execution($error, 'error')->update(['error_message' => 'Tempo limite excedido']);
        $this->assignment($definition, 'Loja sem execução');
        $running = $this->assignment($definition, 'Loja executando');
        $this->execution($running, 'running');
        $other = $this->assignment($this->definition('Console'), 'Loja de outra verificação');
        $this->execution($other, 'completed', 'failed');

        $component = $this->reportComponent()->set('definitionId', $definition->id)
            ->assertViewHas('counts', fn ($counts) => $counts === ['all' => 5, 'attention' => 2, 'success' => 1, 'active' => 1, 'never' => 1])
            ->assertSee('Esperado: Igual a HIT')->assertSee('Observado: MISS')
            ->assertSee('Tempo limite excedido')
            ->assertViewHas('assignments', fn ($rows) => $rows->total() === 5);

        $component->set('status', 'attention')
            ->assertViewHas('assignments', fn ($rows) => $rows->pluck('id')->all() === [$failed->id, $error->id]);
        $component->set('status', 'success')
            ->assertViewHas('assignments', fn ($rows) => $rows->pluck('id')->all() === [$recovered->id]);
        $component->set('storeId', $failed->store_id)->set('status', 'all')
            ->assertViewHas('counts', fn ($counts) => $counts['all'] === 1 && $counts['attention'] === 1);
    }

    public function test_queued_execution_preserves_the_last_actual_run_and_details_are_available_here(): void
    {
        $this->travelTo(now()->startOfSecond());
        $assignment = $this->assignment($this->definition('Cache'), 'Loja em fila');
        $finished = $this->execution($assignment, 'completed', 'success');
        $startedAt = $finished->started_at;
        $this->travel(10)->minutes();
        $queued = $assignment->executions()->create(['definition_snapshot' => ['name' => 'Cache'], 'status' => 'pending']);

        $this->reportComponent()->set('status', 'pending')
            ->assertViewHas('lastRunAt', fn ($time) => $time === $startedAt->toDateTimeString())
            ->assertViewHas('assignments', fn ($rows) => $rows->first()->latestExecution->id === $queued->id
                && $rows->first()->latestStartedExecution->id === $finished->id)
            ->assertSee($startedAt->format('d/m/Y H:i:s'))
            ->call('openDetails', $queued->id)->assertSee('Resposta observada')
            ->call('closeDetails')->assertSet('selectedExecutionId', null);
    }

    public function test_details_cannot_open_execution_outside_selected_definition(): void
    {
        $selected = $this->definition('Selecionada');
        $other = $this->assignment($this->definition('Outra'), 'Outra loja');
        $execution = $this->execution($other, 'completed', 'success');
        $component = $this->reportComponent()->set('definitionId', $selected->id);
        $this->expectException(ModelNotFoundException::class);
        $component->call('openDetails', $execution->id);
    }

    public function test_filters_reset_pagination_and_disabled_assignments_remain_visible(): void
    {
        $definition = $this->definition('Cache');
        for ($i = 1; $i <= 26; $i++) {
            $assignment = $this->assignment($definition, 'Loja '.$i);
        }
        $assignment->update(['enabled' => false]);
        $this->reportComponent()->call('gotoPage', 2)
            ->assertSee('Desabilitada')
            ->set('storeId', $assignment->store_id)
            ->assertSet('paginators.page', 1)
            ->assertViewHas('assignments', fn ($rows) => $rows->total() === 1)
            ->assertSee('Ainda não rodou');
    }

    public function test_definition_list_links_directly_to_failures_and_shows_last_run(): void
    {
        $definition = $this->definition('Cache HIT');
        $assignment = $this->assignment($definition, 'Loja MISS');
        $execution = $this->execution($assignment, 'completed', 'failed');
        Livewire::actingAs(User::factory()->create(['role' => 'n1']))->test(CheckDefinitions::class)
            ->assertSee('1 loja(s) com falha ou erro')
            ->assertSee($execution->started_at->format('d/m/Y H:i:s'))
            ->assertSee(route('stores.check-reports', ['definitionId' => $definition->id, 'status' => 'attention']));
    }

    public function test_report_requires_authentication_and_is_accessible_to_operators(): void
    {
        $this->get(route('stores.check-reports'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'n1']))->get(route('stores.check-reports'))
            ->assertOk()->assertSee('Nenhuma loja encontrada para os filtros selecionados.');
    }

    public function test_old_size_errors_are_explained_in_report_and_details_without_changing_saved_result(): void
    {
        $assignment = $this->assignment($this->definition('Cache HIT'), 'Loja com resposta grande');
        $execution = $this->execution($assignment, 'error');
        $execution->update([
            'definition_snapshot' => ['name' => 'Cache HIT', 'assertions' => [['field' => 'cache_status', 'operator' => 'equals', 'expected' => 'HIT']]],
            'error_message' => 'Resposta acima do limite.',
        ]);

        $this->reportComponent()
            ->assertSee('Resposta grande demais para o monitor')
            ->assertSee('Cache não verificado')
            ->assertSee('não permite concluir se a resposta foi HIT ou MISS')
            ->assertSee('Como investigar:')
            ->assertDontSee('Resposta acima do limite.')
            ->call('openDetails', $execution->id)
            ->assertSee('Detalhes técnicos')
            ->assertSee('Resposta acima do limite.');
        $this->assertSame('error', $execution->fresh()->status);
        $this->assertSame('Resposta acima do limite.', $execution->error_message);
        $this->assertNull($execution->result);
    }

    public function test_report_distinguishes_miss_from_unknown_cache_and_escapes_original_error(): void
    {
        $definition = $this->definition('Cache HIT');
        foreach (['MISS', 'UNKNOWN'] as $status) {
            $assignment = $this->assignment($definition, 'Loja '.$status);
            $this->execution($assignment, 'completed', 'failed', ['observed' => ['cache_status' => $status]]);
        }
        $error = $this->execution($this->assignment($definition, 'Loja com erro'), 'error');
        $error->update(['error_message' => '<script>alert("erro")</script>']);

        $this->reportComponent()->assertSee('MISS — resposta não atendida pelo cache')
            ->assertSee('Cache não identificado')->assertSee('Isso não significa MISS')
            ->call('openDetails', $error->id)
            ->assertSee('<script>alert("erro")</script>')->assertDontSeeHtml('<script>alert("erro")</script>');
    }

    private function reportComponent()
    {
        return Livewire::actingAs(User::factory()->create(['role' => 'n1']))->test(CheckReports::class);
    }

    private function definition(string $name): CheckDefinition
    {
        return CheckDefinition::create(['name' => $name, 'level' => 'SITE', 'executor_type' => 'http', 'configuration' => ['path' => '/'], 'assertions' => []]);
    }

    private function assignment(CheckDefinition $definition, string $name): CheckAssignment
    {
        $store = Store::create(['name' => $name, 'platform' => Store::PLATFORM_WOOCOMMERCE, 'store_url' => 'https://example.com']);

        return CheckAssignment::create(['check_definition_id' => $definition->id, 'store_id' => $store->id, 'enabled' => true, 'interval_minutes' => 5]);
    }

    private function execution(CheckAssignment $assignment, string $status, ?string $outcome = null, ?array $result = null): CheckExecution
    {
        return $assignment->executions()->create([
            'definition_snapshot' => ['name' => $assignment->checkDefinition->name],
            'status' => $status, 'outcome' => $outcome, 'result' => $result,
            'started_at' => now()->subMinutes(2), 'finished_at' => $status === 'running' ? null : now()->subMinute(),
        ]);
    }
}
