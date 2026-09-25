<?php

namespace App\Actions\Checks;

use App\Models\CheckDefinition;


class UpdateCheckDefinitionAction
{
    public function __construct(
        private readonly CheckDefinitionValidator $validator
    ) {}

    public function execute(CheckDefinition $checkDefinition, array $data): CheckDefinition
    {
        $validatedData = $this->validator->validate($data);

        $checkDefinition->update($validatedData);

        return $checkDefinition->refresh();
    }
}