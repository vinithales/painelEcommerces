<?php

namespace App\Services\Checks;

use App\Services\Checks\AssertionEvaluator;
use App\Services\Checks\Executors\HttpExecutor;
use App\Models\CheckAssignment;
use App\Models\CheckExecution;

class CheckExecutionService
{

    public function __construct(
        private readonly HttpExecutor $httpExecutor,
        private readonly AssertionEvaluator $assertionEvaluator,
    ) {
    }

    public function run(CheckAssignment $assignment): ?CheckExecution
    {
        $evaluations = [];

        if (!$assignment?->exists || !$assignment->store()->exists()) {
            return null;
        }

        if (!$assignment->enabled) {
            return null;
        }

        $assignment->loadMissing('checkDefinition');
        $definition = $assignment->checkDefinition;

        if (!$definition) {
            return null;
        }

        if (!in_array($definition->executor_type, ['http'], true)) {
            throw new \InvalidArgumentException("Executor type não suportado: {$definition->executor_type}");
        }

        $execution = null;

        try {
            $execution = $assignment->executions()->create([
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

            $assertions = $execution->definition_snapshot['assertions'] ?? null;

            if (!is_array($assertions) || !array_is_list($assertions) || $assertions === []) {
                throw new \InvalidArgumentException(
                    'A execução precisa de uma lista não vazia de condições.'
                );
            }


            $result = $this->httpExecutor->execute(
                $assignment->store->store_url,
                $execution->definition_snapshot['configuration']
            );

            foreach ($assertions as $assertion) {
                $evaluations[] = $this->assertionEvaluator->evaluate($result, $assertion);
            }

            $allPassed = collect($evaluations)->every(fn(array $evaluation) => ($evaluation['passed'] ?? false) === true);

            $execution->update([
                'status' => 'completed',
                'outcome' => $allPassed ? 'success' : 'failed',
                'result' => [
                    'observed' => $result,
                    'assertions' => $evaluations,
                ],
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            if ($execution) {
                $execution->update([
                    'status' => 'error',
                    'outcome' => null,
                    'error_message' => $e->getMessage(),
                    'finished_at' => now(),
                ]);
            } else if ($execution === null) {
                throw $e;
            }
        }

        return $execution;
    }


}