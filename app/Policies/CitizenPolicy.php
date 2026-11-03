<?php

namespace App\Policies;

use App\Models\Citizen;
use App\Models\User;

class CitizenPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isGlobalAdministrator($actor);
    }

    public function view(User $actor, Citizen $citizen): bool
    {
        return $this->isGlobalAdministrator($actor);
    }

    public function create(User $actor): bool
    {
        return $this->isGlobalAdministrator($actor);
    }

    public function update(User $actor, Citizen $citizen): bool
    {
        return $this->isGlobalAdministrator($actor);
    }

    public function delete(User $actor, Citizen $citizen): bool
    {
        return $this->isGlobalAdministrator($actor) && ! $citizen->trashed();
    }

    public function restore(User $actor, Citizen $citizen): bool
    {
        return $this->isGlobalAdministrator($actor) && $citizen->trashed();
    }

    private function isGlobalAdministrator(User $actor): bool
    {
        return $actor->hasRole('administrator') && $actor->department_id === null;
    }
}
