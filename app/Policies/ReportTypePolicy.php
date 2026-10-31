<?php

namespace App\Policies;

use App\Models\ReportType;
use App\Models\User;

class ReportTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('administrator');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('administrator');
    }

    public function update(User $user, ReportType $reportType): bool
    {
        return $user->hasRole('administrator');
    }
}