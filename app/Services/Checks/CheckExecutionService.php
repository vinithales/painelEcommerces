<?php

namespace App\Services\Checks;

use App\Services\Checks\AssertionEvaluator;
use App\Services\Checks\Executors\HttpExecutor;
use App\Models\CheckAssignment;
use App\Models\CheckExecution;

class CheckExecutionService
{

    public function __construct(
        private readonly HttpExecutor $htttpExecutor,
        private readonly AssertionEvaluator $assertionEvaluator,
    ){
    }
    
    public function run(?CheckAssignment $assignment): ?CheckExecution
    {
        if (! $assignment?->exists || ! $assignment->store()->exists()) {
            return null;
        }

        $assignment->loadMissing('checkDefinition');
        $definition = $assignment->checkDefinition;

        if (! $definition) {
            return null;
        }

        return $assignment->executions()->create([
            'definition_snapshot' => $definition->only([
                'name',
                'level',
                'executor_type',
                'configuration',
                'assertions',
            ]),
            'status' => 'running',
            'started_at' => now(),
        ]);
    }


}