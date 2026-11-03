<?php

namespace App\Policies;

use App\Models\CitizenMatchReview;
use App\Models\User;

class CitizenMatchReviewPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isGlobalAdministrator($actor);
    }

    public function resolve(User $actor, CitizenMatchReview $review): bool
    {
        return $this->isGlobalAdministrator($actor) && $review->status === 'pending';
    }

    private function isGlobalAdministrator(User $actor): bool
    {
        return $actor->hasRole('administrator') && $actor->department_id === null;
    }
}
