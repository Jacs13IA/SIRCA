<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole('administrator');
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole('administrator');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->hasRole('administrator') && ! $actor->is($user);
    }

    public function delete(User $actor, User $user): bool
    {
        if (! $actor->hasRole('administrator') || $actor->is($user)) {
            return false;
        }

        if (! $user->hasRole('administrator')) {
            return true;
        }

        return User::query()
            ->where('role_id', $user->role_id)
            ->whereKeyNot($user->id)
            ->exists();
    }

    public function restore(User $actor, User $user): bool
    {
        return $actor->hasRole('administrator') && $user->trashed();
    }
}