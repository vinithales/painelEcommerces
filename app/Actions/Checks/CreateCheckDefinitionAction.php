<?php

namespace App\Actions\Checks;

use App\Models\CheckDefinition;

class CreateCheckDefinitionAction
{
    public function __construct(
        private readonly CheckDefinitionValidator $validator
    ) {}

    public function execute(array $data): CheckDefinition
    {
        $validatedData = $this->validator->validate($data);

        return CheckDefinition::create($validatedData);
    }

}
