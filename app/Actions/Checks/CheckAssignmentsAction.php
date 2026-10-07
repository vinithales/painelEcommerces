<?php

namespace App\Actions\Checks;

use App\Models\CheckAssignment;
use Illuminate\Database\Eloquent\Builder;

class CheckAssignmentsAction
{

    public function dueCheckAssignments(): Builder
     {
        return CheckAssignment::query()
            ->where('enabled', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }
}