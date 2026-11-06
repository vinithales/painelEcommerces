<?php

namespace Tests\Feature;

use App\Jobs\RunCheckAssignmentJob;
use App\Livewire\Stores\CheckDefinitions;
use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\CheckExecution;
use App\Models\Store;
use App\Models\User;
use App\Services\Checks\CheckExecutionService;
use App\Services\Checks\Executors\HttpExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CheckDefinitionsExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_runs_all_enabled_stores_of_the_selected_definition(): void
    {
        Queue::fake();
        $definition = $this->definition();
        $first = $this->assignment($definition, 'Principal');
        $second = $this->assignment($definition, 'Secundária');
        $this->assignment($definition, 'Desabilitada', false);
        $this->assignment($this->definition(), 'Outra definição');

        $component = $this->executionComponent()
            ->assertSeeHtml('aria-haspopup="dialog"')
            ->call('openExecution', $definition->id)
            ->assertSet('executionScope', 'all')
            ->assertSee('Todas as lojas habilitadas')
            ->assertSee('Uma loja');
        Queue::assertNothingPushed();
        $component->set('executionScope', 'all');
        Queue::assertNothingPushed();
        $component->call('executeSelected')
            ->assertHasNoErrors()
            ->assertSee('Execução agendada para 2 lojas habilitadas.')
            ->assertSet('executionDefinitionId', $definition->id)
            ->assertSee('Na fila')
            ->call('executeSelected');

        Queue::assertPushed(RunCheckAssignmentJob::class, 2);
        Queue::assertPushed(RunCheckAssignmentJob::class, fn ($job) => $job->assignmentId === $first->id);
        Queue::assertPushed(RunCheckAssignmentJob::class, fn ($job) => $job->assignmentId === $second->id);
    }

    public function test_operator_runs_only_the_chosen_store(): void
    {
        Queue::fake();
        $definition = $this->definition();
        $this->assignment($definition, 'Principal');
        $selected = $this->assignment($definition, 'Secundária');

        $this->executionComponent()
            ->call('openExecution', $definition->id)
            ->set('executionScope', 'single')
            ->set('executionAssignmentId', $selected->id)
            ->call('executeSelected')
            ->assertHasNoErrors()
            ->assertSee('Execução agendada para Secundária.')
            ->assertSet('executionDefinitionId', $definition->id);

        Queue::assertPushed(RunCheckAssignmentJob::class, 1);
        Queue::assertPushed(RunCheckAssignmentJob::class, fn ($job) => $job->assignmentId === $selected->id);
    }

    public function test_one_enabled_store_is_preselected_and_cancelling_queues_nothing(): void
    {
        Queue::fake();
        $definition = $this->definition();
        $assignment = $this->assignment($definition, 'Principal');

        $this->executionComponent()
            ->call('openExecution', $definition->id)
            ->assertSet('executionScope', 'single')
            ->assertSet('executionAssignmentId', $assignment->id)
            ->assertSee('Executar nesta loja')
            ->call('closeExecution')
            ->assertSet('executionDefinitionId', null);

        Queue::assertNothingPushed();
    }

    public function test_disabled_or_unrelated_assignments_cannot_be_selected(): void
    {
        Queue::fake();
        $definition = $this->definition();
        $this->assignment($definition, 'Principal');
        $disabled = $this->assignment($definition, 'Desabilitada', false);
        $unrelated = $this->assignment($this->definition(), 'Outra definição');

        $component = $this->executionComponent()->call('openExecution', $definition->id)->set('executionScope', 'single');
        foreach ([$disabled->id, $unrelated->id] as $id) {
            $component->set('executionAssignmentId', $id)
                ->call('executeSelected')
                ->assertHasErrors('executionAssignmentId')
                ->assertSet('executionDefinitionId', $definition->id);
        }
        Queue::assertNothingPushed();
    }

    public function test_single_execution_requires_a_store_and_a_valid_scope(): void
    {
        Queue::fake();
        $definition = $this->definition();
        $this->assignment($definition, 'Principal');

        $component = $this->executionComponent()->call('openExecution', $definition->id);
        $component->set('executionAssignmentId', null)->call('executeSelected')->assertHasErrors('executionAssignmentId');
        $component->set('executionScope', 'invalid')->call('executeSelected')->assertHasErrors('executionScope');
        Queue::assertNothingPushed();
    }

    public function test_unsupported_executor_and_no_enabled_stores_queue_nothing(): void
    {
        Queue::fake();
        $unsupported = $this->definition('smtp');
        $this->assignment($unsupported, 'SSH');
        $empty = $this->definition();
        $disabled = $this->definition();
        $this->assignment($disabled, 'Desabilitada', false);

        foreach ([$unsupported, $empty, $disabled] as $definition) {
            $this->executionComponent()->call('openExecution', $definition->id)
                ->set('executionScope', 'all')->call('executeSelected')->assertHasErrors('executionScope');
        }
        Queue::assertNothingPushed();
    }

    public function test_existing_run_now_still_checks_enabled_and_executor_type(): void
    {
        Queue::fake();
        $http = $this->definition();
        $enabled = $this->assignment($http, 'Principal');
        $disabled = $this->assignment($http, 'Desabilitada', false);
        $unsupported = $this->assignment($this->definition('smtp'), 'SSH');
        $component = $this->executionComponent();

        $component->call('runNow', $disabled->id)->call('runNow', $unsupported->id);
        Queue::assertNothingPushed();
        $component->call('runNow', $enabled->id);
        Queue::assertPushed(RunCheckAssignmentJob::class, 1);
        Queue::assertPushed(RunCheckAssignmentJob::class, fn ($job) => $job->assignmentId === $enabled->id);
    }

    public function test_group_selection_queues_only_the_selected_enabled_stores(): void
    {
        Queue::fake();
        $definition = $this->definition();
        $first = $this->assignment($definition, 'Principal');
        $second = $this->assignment($definition, 'Secundária');
        $this->assignment($definition, 'Não selecionada');
        $disabled = $this->assignment($definition, 'Desabilitada', false);

        $component = $this->executionComponent()->call('openExecution', $definition->id)
            ->set('executionScope', 'group');
        $component->call('executeSelected')->assertHasErrors('executionAssignmentIds');
        $component->set('executionAssignmentIds', [$first->id, $disabled->id])
            ->call('executeSelected')->assertHasErrors('executionAssignmentIds');
        Queue::assertNothingPushed();

        $component->set('executionAssignmentIds', [$first->id, $second->id]);
        Queue::assertNothingPushed();
        $component->call('executeSelected')->assertHasNoErrors()->assertSee('0 de 2 finalizadas');
        Queue::assertPushed(RunCheckAssignmentJob::class, 2);
        $this->assertSame([$first->id, $second->id], CheckExecution::orderBy('id')->pluck('check_assignment_id')->all());
    }

    public function test_tracked_jobs_update_progress_and_display_execution_errors(): void
    {
        Queue::fake();
        $httpExecutor = \Mockery::mock(HttpExecutor::class);
        $httpExecutor->shouldReceive('execute')->twice()->andReturnUsing(fn ($url) => [
            'status_code' => str_contains($url, 'principal') ? 200 : 503,
            'response_time' => 100,
            'url' => $url,
            'body' => 'Resposta da loja',
        ]);
        $this->instance(HttpExecutor::class, $httpExecutor);
        $definition = $this->definition();
        $this->assignment($definition, 'Principal');
        $this->assignment($definition, 'Secundária');
        $component = $this->executionComponent()->call('openExecution', $definition->id)->call('executeSelected');
        $jobs = Queue::pushed(RunCheckAssignmentJob::class);

        foreach ($jobs as $job) {
            $job->handle(app(CheckExecutionService::class));
        }
        $this->assertSame(2, CheckExecution::count());
        $component->call('refreshExecutionProgress')
            ->assertSee('Concluída')->assertSee('Falhou')->assertSee('2 de 2 finalizadas')
            ->assertSee('Uma ou mais condições da verificação não foram atendidas.');

        $execution = CheckExecution::findOrFail($jobs->last()->executionId);
        $execution->update(['status' => 'running', 'outcome' => null]);
        $component->call('refreshExecutionProgress')->assertSee('Executando');
        $jobs->last()->failed(new \RuntimeException('Tempo limite da execução excedido.'));
        $component->call('refreshExecutionProgress')
            ->assertSee('Erro')->assertSee('Tempo limite da execução excedido.')
            ->assertDontSeeHtml('wire:poll.2s.keep-alive');
    }

    private function executionComponent()
    {
        return Livewire::actingAs(User::factory()->create(['role' => 'n1']))->test(CheckDefinitions::class);
    }

    private function definition(string $executor = 'http'): CheckDefinition
    {
        return CheckDefinition::create([
            'name' => 'Disponibilidade',
            'level' => 'SITE',
            'executor_type' => $executor,
            'configuration' => ['path' => '/', 'method' => 'GET', 'timeout' => 10, 'follow_redirects' => false],
            'assertions' => [['field' => 'status_code', 'operator' => 'equals', 'expected' => 200]],
        ]);
    }

    private function assignment(CheckDefinition $definition, string $name, bool $enabled = true): CheckAssignment
    {
        $store = Store::create([
            'name' => $name,
            'platform' => Store::PLATFORM_WOOCOMMERCE,
            'store_url' => 'https://'.str($name)->slug().'.example.com',
        ]);

        return CheckAssignment::create([
            'check_definition_id' => $definition->id,
            'store_id' => $store->id,
            'interval_minutes' => 5,
            'enabled' => $enabled,
        ]);
    }
}

