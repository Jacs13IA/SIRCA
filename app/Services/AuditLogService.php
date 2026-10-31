<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    public function record(Model $subject, ?User $actor, string $event, array $oldValues = [], array $newValues = []): AuditLog
    {
        return $subject->morphMany(AuditLog::class, 'auditable')->create([
            'actor_user_id' => $actor?->id,
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}