<?php

namespace App\Services\Checks;

use App\Models\CheckAssignment;
use App\Models\CheckExecution;
use App\Services\Checks\Executors\BrowserExecutor;
use App\Services\Checks\Executors\HttpExecutor;

class CheckExecutionService
{
    public function __construct(
        private readonly HttpExecutor $httpExecutor,
        private readonly AssertionEvaluator $assertionEvaluator,
        private readonly BrowserExecutor $browserExecutor,
    ) {}

    public function run(?CheckAssignment $assignment, ?CheckExecution $execution = null): ?CheckExecution
    {
        $evaluations = [];

        if (! $assignment?->exists || ! $assignment->store()->exists()) {
            return null;
        }

        if (! $assignment->enabled) {
            return null;
        }

        $assignment->loadMissing('checkDefinition');
        $definition = $assignment->checkDefinition;

        if (! $definition) {
            return null;
        }

        $executorType = $execution !== null
            ? $execution->definition_snapshot['executor_type']
            : $definition->executor_type;

        if (! in_array($executorType, ['http', 'browser'], true)) {
            throw new \InvalidArgumentException("Executor type não suportado: {$executorType}");
        }

        try {
            if ($execution) {
                $execution->update(['status' => 'running', 'started_at' => now()]);
            } else {
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
            }

            $assertions = $execution->definition_snapshot['assertions'] ?? null;

            if (! is_array($assertions) || ! array_is_list($assertions) || $assertions === []) {
                throw new \InvalidArgumentException(
                    'A execução precisa de uma lista não vazia de condições.'
                );
            }

            $executor = $executorType === 'browser' ? $this->browserExecutor : $this->httpExecutor;
            $result = $executor->execute(
                $assignment->store->store_url,
                $execution->definition_snapshot['configuration']
            );

            if (isset($result['observation_error'])) {
                throw new \RuntimeException($result['observation_error']);
            }

            foreach ($assertions as $assertion) {
                $evaluations[] = $this->assertionEvaluator->evaluate($result, $assertion);
            }

            $allPassed = collect($evaluations)->every(fn (array $evaluation) => ($evaluation['passed'] ?? false) === true);

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
                    'result' => isset($result) ? ['observed' => $result, 'assertions' => $evaluations] : null,
                    'finished_at' => now(),
                ]);
            } elseif ($execution === null) {
                throw $e;
            }
        }

        return $execution;
    }
}
