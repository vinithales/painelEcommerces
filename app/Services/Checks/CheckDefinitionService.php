<?php

namespace App\Services\Checks;

use App\Actions\Checks\CheckDefinitionAction;
use App\Models\CheckDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CheckDefinitionService
{
    public function __construct(
        private readonly CheckDefinitionAction $definitions
    ) {}

    public function save(?CheckDefinition $definition, array $data, array $assignments): CheckDefinition
    {
        $assignments = $this->validateAssignments($assignments);

        return DB::transaction(function () use ($definition, $data, $assignments): CheckDefinition {
            $definition = $definition
                ? $this->definitions->update($definition, $data)
                : $this->definitions->create($data);

            $this->syncAssignments($definition, $assignments);

            return $definition->load('assignments.store');
        });
    }

    private function validateAssignments(array $assignments): array
    {
        return Validator::make(
            ['assignments' => $assignments],
            [
                'assignments' => 'array',
                'assignments.*.store_id' => 'required|integer|distinct|exists:stores,id',
                'assignments.*.interval_minutes' => 'required|integer|min:1|max:10080',
                'assignments.*.enabled' => 'required|boolean',
            ],
            [],
            [
                'assignments.*.store_id' => 'loja',
                'assignments.*.interval_minutes' => 'intervalo',
                'assignments.*.enabled' => 'estado',
            ]
        )->validate()['assignments'];
    }

    private function syncAssignments(CheckDefinition $definition, array $assignments): void
    {
        $storeIds = collect($assignments)->pluck('store_id')->map(fn ($id) => (int) $id)->all();
        $removed = $definition->assignments()->whereNotIn('store_id', $storeIds);

        if ((clone $removed)->whereHas('executions')->exists()) {
            throw ValidationException::withMessages([
                'storeAssignments' => 'Uma associação com execuções não pode ser removida. Desabilite-a para preservar o histórico.',
            ]);
        }

        $removed->delete();

        foreach ($assignments as $assignment) {
            $model = $definition->assignments()
                ->lockForUpdate()
                ->firstOrNew([
                    'store_id' => (int) $assignment['store_id'],
                ]);

            $model->fill([
                'interval_minutes' => (int) $assignment['interval_minutes'],
                'enabled' => (bool) $assignment['enabled'],
            ]);

            if (! $model->enabled) {
                $model->next_run_at = null;
            } elseif (! $model->exists || $model->isDirty('enabled') || $model->isDirty('interval_minutes')) {
                $model->next_run_at = now()->addMinutes($model->interval_minutes);
            }

            $model->save();
        }
    }
}
