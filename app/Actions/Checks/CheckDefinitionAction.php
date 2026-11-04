<?php

namespace App\Actions\Checks;

use App\Models\CheckDefinition;
use App\Actions\Checks\CreateCheckDefinitionAction;
use App\Actions\Checks\UpdateCheckDefinitionAction;
use App\Actions\Checks\DeleteCheckDefinitionAction;

class CheckDefinitionAction
{
    public function __construct(
        private readonly CheckDefinitionValidator $validator,
        private readonly CreateCheckDefinitionAction $createAction,
        private readonly UpdateCheckDefinitionAction $updateAction,
        private readonly DeleteCheckDefinitionAction $deleteAction
    ) {}


    public function create(array $data): CheckDefinition
    {
        return $this->createAction->execute($data);
    }

    public function update(CheckDefinition $checkDefinition, array $data): CheckDefinition
    {
        return $this->updateAction->execute($checkDefinition, $data);
    }

    public function delete(CheckDefinition $checkDefinition)
    {
        return $this->deleteAction->execute($checkDefinition);
    }
}
