<?php

namespace App\Services;

use App\Models\Citizen;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CitizenAdministrationService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly CitizenIdentityService $identity,
    ) {}

    public function create(array $data, User $actor): Citizen
    {
        return DB::transaction(function () use ($data, $actor): Citizen {
            $citizen = $this->identity->createCitizen($data);
            $this->auditLog->record($citizen, $actor, 'citizen.created');

            return $citizen;
        });
    }

    public function update(Citizen $citizen, array $data, User $actor): Citizen
    {
        return DB::transaction(function () use ($citizen, $data, $actor): Citizen {
            $this->identity->updateCitizen($citizen, $data);
            $this->auditLog->record($citizen, $actor, 'citizen.updated');

            return $citizen;
        });
    }

    public function deactivate(Citizen $citizen, User $actor): void
    {
        DB::transaction(function () use ($citizen, $actor): void {
            $this->auditLog->record($citizen, $actor, 'citizen.deactivated');
            $citizen->delete();
        });
    }

    public function restore(Citizen $citizen, User $actor): void
    {
        DB::transaction(function () use ($citizen, $actor): void {
            $citizen->restore();
            $this->auditLog->record($citizen, $actor, 'citizen.restored');
        });
    }
}
