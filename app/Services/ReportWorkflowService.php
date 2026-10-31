<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Report;
use App\Models\ReportActivity;
use App\Models\ReportAssignment;
use App\Models\ReportStatus;
use App\Models\User;
use App\Notifications\ReportAssignedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportWorkflowService
{
    private const ALLOWED_TRANSITIONS = [
        'registrado' => ['asignado', 'cancelado'],
        'asignado' => ['en_proceso', 'cancelado'],
        'en_proceso' => ['atendido', 'cancelado'],
        'atendido' => ['cerrado', 'en_proceso'],
    ];

    public function assign(Report $report, User $assignee, User $actor, ?string $notes = null): void
    {
        DB::transaction(function () use ($report, $assignee, $actor, $notes): void {
            $report = Report::query()->lockForUpdate()->findOrFail($report->id);

            if ($report->closed_at || $report->status()->firstOrFail()->is_final) {
                throw ValidationException::withMessages(['report' => 'No se puede asignar un reporte cerrado o finalizado.']);
            }

            if (! $assignee->hasRole('operator') || $assignee->department_id !== $report->department_id) {
                throw ValidationException::withMessages(['assigned_user_id' => 'Selecciona un operador activo de la misma dirección.']);
            }

            $now = now();
            $previous = $report->assignments()->whereNull('unassigned_at')->first();

            if ($previous?->assigned_user_id === $assignee->id) {
                throw ValidationException::withMessages(['assigned_user_id' => 'El reporte ya está asignado a este operador.']);
            }

            $report->assignments()->whereNull('unassigned_at')->update(['unassigned_at' => $now]);

            ReportAssignment::query()->create([
                'report_id' => $report->id,
                'assigned_user_id' => $assignee->id,
                'assigned_by_user_id' => $actor->id,
                'assigned_at' => $now,
                'notes' => $notes,
            ]);
            $assignee->notify(new ReportAssignedNotification($report));

            $oldStatus = $report->status()->firstOrFail();
            $newStatus = ReportStatus::query()->where('code', 'asignado')->firstOrFail();

            if ($oldStatus->code === 'registrado') {
                $report->report_status_id = $newStatus->id;
                $report->save();
            } else {
                $newStatus = $oldStatus;
            }

            $report->activities()->create([
                'user_id' => $actor->id,
                'type' => 'assignment',
                'description' => $notes ?: 'Reporte asignado a '.$assignee->name.'.',
                'metadata' => ['assigned_user_id' => $assignee->id],
                'from_status_id' => $oldStatus->id,
                'to_status_id' => $newStatus->id,
            ]);

            $this->audit($report, $actor, 'report.assigned', [
                'assigned_user_id' => $previous?->assigned_user_id,
            ], [
                'assigned_user_id' => $assignee->id,
                'report_status_id' => $newStatus->id,
            ]);
        });
    }

    public function changeStatus(Report $report, ReportStatus $newStatus, User $actor, string $description): void
    {
        DB::transaction(function () use ($report, $newStatus, $actor, $description): void {
            $report = Report::query()->lockForUpdate()->findOrFail($report->id);
            $oldStatus = $report->status()->firstOrFail();
            $allowed = self::ALLOWED_TRANSITIONS[$oldStatus->code] ?? [];

            if (! in_array($newStatus->code, $allowed, true)) {
                throw ValidationException::withMessages(['report_status_code' => 'La transición de estado solicitada no está permitida.']);
            }

            if ($newStatus->code === 'asignado' && ! $report->assignments()->whereNull('unassigned_at')->exists()) {
                throw ValidationException::withMessages(['report_status_code' => 'Asigna primero un responsable para continuar.']);
            }

            $report->report_status_id = $newStatus->id;
            $report->closed_at = $newStatus->code === 'cerrado' ? now() : null;
            $report->save();

            $report->activities()->create([
                'user_id' => $actor->id,
                'type' => 'status_change',
                'description' => $description,
                'from_status_id' => $oldStatus->id,
                'to_status_id' => $newStatus->id,
            ]);

            $this->audit($report, $actor, 'report.status_changed',
                ['report_status_id' => $oldStatus->id],
                ['report_status_id' => $newStatus->id, 'closed_at' => $report->closed_at?->toISOString()],
            );
        });
    }

    public function addActivity(Report $report, User $actor, string $description): ReportActivity
    {
        return DB::transaction(function () use ($report, $actor, $description): ReportActivity {
            $activity = $report->activities()->create([
                'user_id' => $actor->id,
                'type' => 'note',
                'description' => $description,
            ]);

            $this->audit($report, $actor, 'report.activity_added', [], ['activity_id' => $activity->id]);

            return $activity;
        });
    }

    private function audit(Report $report, User $actor, string $event, array $oldValues, array $newValues): void
    {
        $report->auditLogs()->create([
            'actor_user_id' => $actor->id,
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}