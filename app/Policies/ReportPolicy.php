<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('administrator')
            || ($user->department_id !== null && ($user->hasRole('supervisor') || $user->hasRole('operator')));
    }

    public function view(User $user, Report $report): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ($user->hasRole('supervisor')) {
            return $user->department_id === $report->department_id;
        }

        return $user->hasRole('operator')
            && $user->department_id === $report->department_id
            && $report->assignments()
                ->where('assigned_user_id', $user->id)
                ->whereNull('unassigned_at')
                ->exists();
    }

    public function assign(User $user, Report $report): bool
    {
        return ! $report->closed_at
            && ($user->hasRole('administrator')
                || ($user->hasRole('supervisor') && $user->department_id === $report->department_id));
    }

    public function updateStatus(User $user, Report $report): bool
    {
        if ($report->closed_at || $report->status?->is_final) {
            return false;
        }

        return $user->hasRole('administrator')
            || ($user->hasRole('supervisor') && $user->department_id === $report->department_id)
            || ($user->hasRole('operator')
                && $user->department_id === $report->department_id
                && $report->assignments()
                    ->where('assigned_user_id', $user->id)
                    ->whereNull('unassigned_at')
                    ->exists());
    }

    public function addActivity(User $user, Report $report): bool
    {
        return $this->view($user, $report) && ! $report->closed_at && ! $report->status?->is_final;
    }

    public function uploadEvidence(User $user, Report $report): bool
    {
        return $this->addActivity($user, $report);
    }

    public function viewEvidence(User $user, Report $report): bool
    {
        return $this->view($user, $report);
    }
}