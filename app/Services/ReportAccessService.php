<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReportAccessService
{
    public function visibleReports(User $user): Builder
    {
        $query = Report::query();

        if ($user->hasRole('administrator')) {
            return $query;
        }

        if ($user->department_id === null || (! $user->hasRole('supervisor') && ! $user->hasRole('operator'))) {
            return $query->whereKey([]);
        }

        $query->where('department_id', $user->department_id);

        if ($user->hasRole('operator')) {
            $query->whereHas('assignments', fn (Builder $assignmentQuery) => $assignmentQuery
                ->where('assigned_user_id', $user->id)
                ->whereNull('unassigned_at'));
        }

        return $query;
    }
}