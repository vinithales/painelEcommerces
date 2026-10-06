<?php

namespace Tests\Feature;

use App\Livewire\Stores\CheckDefinitions;
use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\CheckExecution;
use App\Models\Store;
use App\Models\User;
use App\Services\Checks\CheckDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CheckDefinitionsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_definition_and_associates_it_with_stores(): void
    {
        $firstStore = $this->store('Loja principal');
        $secondStore = $this->store('Loja secundária');

        Livewire::actingAs($this->user('admin'))
            ->test(CheckDefinitions::class)
            ->call('create')
            ->set('name', 'Disponibilidade da homepage')
            ->set('level', 'SITE')
            ->set('executorType', 'http')
            ->set('configurationJson', '{"path":"/","method":"GET","timeout":10,"follow_redirects":false}')
            ->set('assertionsJson', '[{"field":"status_code","operator":"equals","expected":200}]')
            ->set("storeAssignments.{$firstStore->id}.selected", true)
            ->set("storeAssignments.{$firstStore->id}.interval_minutes", 5)
            ->set("storeAssignments.{$secondStore->id}.selected", true)
            ->set("storeAssignments.{$secondStore->id}.interval_minutes", 30)
            ->set("storeAssignments.{$secondStore->id}.enabled", false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('formOpen', false);

        $definition = CheckDefinition::where('name', 'Disponibilidade da homepage')->firstOrFail();

        $this->assertSame([
            'path' => '/',
            'method' => 'GET',
            'timeout' => 10,
            'follow_redirects' => false,
        ], $definition->configuration);
        $this->assertSame([
            [
                'field' => 'status_code',
                'operator' => 'equals',
                'expected' => 200,
            ],
        ], $definition->assertions);
        $this->assertDatabaseHas('check_assignments', [
            'check_definition_id' => $definition->id,
            'store_id' => $firstStore->id,
            'interval_minutes' => 5,
            'enabled' => true,
        ]);
        $this->assertDatabaseHas('check_assignments', [
            'check_definition_id' => $definition->id,
            'store_id' => $secondStore->id,
            'interval_minutes' => 30,
            'enabled' => false,
        ]);
    }

    public function test_admin_can_edit_a_definition_and_keep_its_store_association(): void
    {
        $store = $this->store('Loja editada');
        $definition = CheckDefinition::create([
            'name' => 'Disponibilidade inicial',
            'level' => 'SITE',
            'executor_type' => 'http',
            'configuration' => [
                'path' => '/',
                'method' => 'GET',
                'timeout' => 10,
                'follow_redirects' => false,
            ],
            'assertions' => [[
                'field' => 'status_code',
                'operator' => 'equals',
                'expected' => 200,
            ]],
        ]);
        CheckAssignment::create([
            'check_definition_id' => $definition->id,
            'store_id' => $store->id,
            'interval_minutes' => 5,
            'enabled' => true,
        ]);

        Livewire::actingAs($this->user('admin'))
            ->test(CheckDefinitions::class)
            ->call('edit', $definition->id)
            ->set('name', 'Disponibilidade atualizada')
            ->set('configurationJson', '{"path":"/health","method":"HEAD","timeout":20,"follow_redirects":true}')
            ->set('assertionsJson', '[{"field":"status_code","operator":"equals","expected":204}]')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('formOpen', false);

        $definition->refresh();

        $this->assertSame('Disponibilidade atualizada', $definition->name);
        $this->assertSame('/health', $definition->configuration['path']);
        $this->assertSame(20, $definition->configuration['timeout']);
        $this->assertTrue($definition->configuration['follow_redirects']);
        $this->assertSame(204, $definition->assertions[0]['expected']);
        $this->assertDatabaseHas('check_assignments', [
            'check_definition_id' => $definition->id,
            'store_id' => $store->id,
        ]);
    }

    public function test_incomplete_configuration_reports_the_missing_fields(): void
    {
        Livewire::actingAs($this->user('admin'))
            ->test(CheckDefinitions::class)
            ->call('create')
            ->assertSet('configurationJson', "{\n    \"path\": \"/\",\n    \"method\": \"GET\",\n    \"timeout\": 10,\n    \"follow_redirects\": false\n}")
            ->set('name', 'Configuração incompleta')
            ->set('configurationJson', '{"path":"/","method":"GET"}')
            ->call('save')
            ->assertHasErrors([
                'configuration.timeout',
                'configuration.follow_redirects',
            ])
            ->assertSet('formOpen', true);
    }

    public function test_operator_can_view_but_cannot_open_the_definition_form(): void
    {
        $operator = $this->user('n1');

        $this->actingAs($operator)
            ->get(route('stores.checks'))
            ->assertOk()
            ->assertSee('Verificação')
            ->assertDontSee('Verificações 2')
            ->assertSeeLivewire(CheckDefinitions::class);

        $this->get(route('stores.check-definitions'))->assertRedirect(route('stores.checks'));

        Livewire::actingAs($operator)
            ->test(CheckDefinitions::class)
            ->call('create')
            ->assertForbidden();
    }

    public function test_an_assignment_with_execution_history_cannot_be_removed(): void
    {
        $definition = CheckDefinition::create([
            'name' => 'Saúde da API',
            'level' => 'API',
            'executor_type' => 'api',
            'configuration' => [
                'path' => '/',
                'method' => 'GET',
                'timeout' => 10,
                'follow_redirects' => false,
            ],
            'assertions' => [[
                'field' => 'status_code',
                'operator' => 'equals',
                'expected' => 200,
            ]],
        ]);
        $assignment = CheckAssignment::create([
            'check_definition_id' => $definition->id,
            'store_id' => $this->store('Loja com histórico')->id,
            'interval_minutes' => 10,
            'enabled' => true,
        ]);
        CheckExecution::create([
            'check_assignment_id' => $assignment->id,
            'status' => 'completed',
            'outcome' => 'passed',
            'result' => ['status_code' => 200],
        ]);

        try {
            app(CheckDefinitionService::class)->save($definition, $definition->only([
                'name',
                'level',
                'executor_type',
                'configuration',
                'assertions',
            ]), []);
            $this->fail('A remoção deveria preservar a associação com histórico.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('storeAssignments', $exception->errors());
        }

        $this->assertDatabaseHas('check_assignments', ['id' => $assignment->id]);
    }

    public function test_store_links_filter_definitions_and_preselect_the_store_for_execution(): void
    {
        $firstStore = $this->store('Principal');
        $selectedStore = $this->store('Selecionada');
        $otherStore = $this->store('Outra');
        $data = [
            'name' => 'Disponibilidade da loja',
            'level' => 'SITE',
            'executor_type' => 'http',
            'configuration' => ['path' => '/', 'method' => 'GET', 'timeout' => 10, 'follow_redirects' => false],
            'assertions' => [['field' => 'status_code', 'operator' => 'equals', 'expected' => 200]],
        ];
        $service = app(CheckDefinitionService::class);
        $definition = $service->save(null, $data, [
            ['store_id' => $firstStore->id, 'interval_minutes' => 5, 'enabled' => true],
            ['store_id' => $selectedStore->id, 'interval_minutes' => 5, 'enabled' => true],
        ]);
        $data['name'] = 'Definição de outra loja';
        $service->save(null, $data, [['store_id' => $otherStore->id, 'interval_minutes' => 5, 'enabled' => true]]);

        Livewire::actingAs($this->user('n1'))
            ->withQueryParams(['storeId' => $selectedStore->id])
            ->test(CheckDefinitions::class)
            ->assertSee('Disponibilidade da loja')
            ->assertDontSee('Definição de outra loja')
            ->call('openExecution', $definition->id)
            ->assertSet('executionScope', 'single')
            ->assertSet('executionAssignmentId', $definition->assignments->firstWhere('store_id', $selectedStore->id)->id)
            ->call('closeExecution')
            ->set('storeId', null)
            ->assertSee('Definição de outra loja');
    }

    private function store(string $name): Store
    {
        return Store::create([
            'name' => $name,
            'platform' => Store::PLATFORM_WOOCOMMERCE,
            'store_url' => 'https://'.str($name)->slug().'.example.com',
        ]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }
}
