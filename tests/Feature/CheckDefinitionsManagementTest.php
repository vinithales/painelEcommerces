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
            ->set('httpConfiguration', ['path' => '/', 'method' => 'GET', 'timeout' => '10', 'follow_redirects' => false])
            ->set('assertions', [['field' => 'status_code', 'operator' => 'equals', 'expected' => '200']])
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
            ->set('httpConfiguration', ['path' => '/health', 'method' => 'HEAD', 'timeout' => '20', 'follow_redirects' => true])
            ->set('assertions', [['field' => 'status_code', 'operator' => 'equals', 'expected' => '204']])
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
            ->set('httpConfiguration', ['path' => '/', 'method' => 'GET'])
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

    public function test_many_associations_show_three_previews_and_a_complete_dialog(): void
    {
        $definition = CheckDefinition::create([
            'name' => 'Verificação com muitas lojas',
            'level' => 'SITE',
            'executor_type' => 'http',
            'configuration' => ['path' => '/', 'method' => 'GET', 'timeout' => 10, 'follow_redirects' => false],
            'assertions' => [['field' => 'status_code', 'operator' => 'equals', 'expected' => 200]],
        ]);
        foreach (range(1, 5) as $index) {
            $definition->assignments()->create([
                'store_id' => $this->store("Loja {$index}")->id,
                'interval_minutes' => 5,
                'enabled' => $index !== 4,
                'next_run_at' => $index === 5 ? '2026-10-08 12:30:00' : null,
            ]);
        }

        $html = Livewire::actingAs($this->user('n1'))
            ->test(CheckDefinitions::class)
            ->assertSee('Ver todas (5)')
            ->assertSee('Desabilitada')
            ->assertSee('Não agendada')
            ->assertSee('08/10/2026 12:30')
            ->html();

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(3, $xpath->query('//span[contains(concat(" ", normalize-space(@class), " "), " assignment-chip ")]')->length);
        $this->assertSame(5, $xpath->query('//dialog//tbody/tr')->length);
    }

    public function test_condition_rows_save_numbers_and_text_with_the_correct_types(): void
    {
        Livewire::actingAs($this->user('admin'))
            ->test(CheckDefinitions::class)
            ->call('create')
            ->assertDontSee('Asserções (JSON)')
            ->assertDontSee('Configuração (JSON)')
            ->set('name', 'Condições visuais')
            ->call('nextStep')
            ->call('nextStep')
            ->assertSee('Quando considerar que está tudo certo?')
            ->call('addAssertion')
            ->assertSet('assertions.1.operator', 'less_than')
            ->set('assertions.1.expected', '3000')
            ->call('addAssertion')
            ->set('assertions.2.field', 'body')
            ->assertSet('assertions.2.operator', 'equals')
            ->assertSet('assertions.2.expected', '')
            ->set('assertions.2.expected', '123')
            ->call('save')
            ->assertHasNoErrors();

        $definition = CheckDefinition::where('name', 'Condições visuais')->firstOrFail();
        $this->assertSame(3000, $definition->assertions[1]['expected']);
        $this->assertSame('123', $definition->assertions[2]['expected']);

        Livewire::test(CheckDefinitions::class)
            ->call('edit', $definition->id)
            ->assertSet('assertions', $definition->assertions)
            ->assertSet('httpConfiguration', $definition->configuration)
            ->call('removeAssertion', 1)
            ->assertSet('assertions.1.field', 'body')
            ->call('removeAssertion', 1)
            ->call('removeAssertion', 0)
            ->assertSet('assertions', [['field' => 'status_code', 'operator' => 'equals', 'expected' => 200]]);
    }

    public function test_condition_rows_reject_invalid_numbers_and_accept_text_operators(): void
    {
        $component = Livewire::actingAs($this->user('admin'))
            ->test(CheckDefinitions::class)
            ->call('create')
            ->set('name', 'Condições inválidas')
            ->set('assertions', [['field' => 'response_time', 'operator' => 'less_than', 'expected' => 'abc']])
            ->call('save')
            ->assertHasErrors(['assertions.0.expected'])
            ->assertSet('formOpen', true);

        $component->set('assertions.0.expected', '2.5')
            ->call('save')
            ->assertHasErrors(['assertions.0.expected']);

        $component->set('assertions', [['field' => 'body', 'operator' => 'contains', 'expected' => 'texto']])
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_wizard_validates_each_step_and_preserves_values_when_going_back(): void
    {
        Livewire::actingAs($this->user('admin'))
            ->test(CheckDefinitions::class)
            ->call('create')
            ->assertSet('formStep', 1)
            ->assertSee('Próximo')
            ->assertDontSee('Tempo limite (segundos)')
            ->call('nextStep')
            ->assertHasErrors(['name'])
            ->assertSet('formStep', 1)
            ->set('name', 'Monitoramento em etapas')
            ->call('nextStep')
            ->assertSet('formStep', 2)
            ->assertSee('Tempo limite (segundos)')
            ->set('httpConfiguration.path', 'invalido')
            ->call('nextStep')
            ->assertHasErrors(['configuration.path'])
            ->assertSet('formStep', 2)
            ->set('httpConfiguration.path', '/health')
            ->call('previousStep')
            ->assertSet('formStep', 1)
            ->assertSet('name', 'Monitoramento em etapas')
            ->call('previousStep')
            ->assertSet('formStep', 1)
            ->call('nextStep')
            ->assertSet('httpConfiguration.path', '/health')
            ->call('nextStep')
            ->assertSet('formStep', 3)
            ->set('assertions.0.expected', 'abc')
            ->call('nextStep')
            ->assertHasErrors(['assertions.0.expected'])
            ->assertSet('formStep', 3)
            ->set('assertions.0.expected', '200')
            ->call('nextStep')
            ->assertSet('formStep', 4)
            ->assertSee('Concluir')
            ->assertDontSee('Próximo')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('formOpen', false);

        $definition = CheckDefinition::where('name', 'Monitoramento em etapas')->firstOrFail();
        $this->assertSame('/health', $definition->configuration['path']);
        $this->assertSame(200, $definition->assertions[0]['expected']);

        Livewire::test(CheckDefinitions::class)
            ->call('edit', $definition->id)
            ->assertSet('formStep', 1)
            ->assertSet('name', 'Monitoramento em etapas');
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
