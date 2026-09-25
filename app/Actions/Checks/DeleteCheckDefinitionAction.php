<?php

namespace App\Actions\Checks;

use App\Models\CheckDefinition;

class DeleteCheckDefinitionAction
{
    public function execute(CheckDefinition $checkDefinition)
    {
        $checkDefinition->delete();
    }
}