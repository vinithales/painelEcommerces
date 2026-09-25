<?php

namespace Tests\Feature;

use App\Models\CheckAssignment;
use App\Models\CheckDefinition;
use App\Models\Store;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_a_definition_and_a_store_and_casts_its_attributes(): void
    {
        $definition = $this->createDefinition();
        $store = $this->createStore();

        $assignment = CheckAssignment::create([
            'check_definition_id' => $definition->id,
            'store_id' => $store->id,
            'interval_minutes' => 15,
            'enabled' => 1,
        ])->fresh();

        $this->assertTrue($assignment->enabled);
        $this->assertSame(15, $assignment->interval_minutes);
        $this->assertTrue($assignment->checkDefinition->is($definition));
        $this->assertTrue($assignment->store->is($store));
        $this->assertTrue($store->fresh()->checkAssignments->contains($assignment));
    }

    public function test_a_definition_can_only_be_assigned_once_to_the_same_store(): void
    {
        $definition = $this->createDefinition();
        $store = $this->createStore();
        $attributes = [
            'check_definition_id' => $definition->id,
            'store_id' => $store->id,
            'interval_minutes' => 15,
            'enabled' => true,
        ];

        CheckAssignment::create($attributes);

        $this->expectException(QueryException::class);

        CheckAssignment::create($attributes);
    }

    private function createDefinition(): CheckDefinition
    {
        return CheckDefinition::create([
            'name' => 'Disponibilidade da loja',
            'level' => 'SITE',
            'executor_type' => 'http',
            'configuration' => ['url' => 'https://example.com'],
            'assertions' => ['status_code' => 200],
        ]);
    }

    private function createStore(): Store
    {
        return Store::create([
            'name' => 'Loja teste',
            'platform' => Store::PLATFORM_WOOCOMMERCE,
            'store_url' => 'https://example.com',
        ]);
    }
}
