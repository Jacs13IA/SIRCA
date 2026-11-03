<?php

namespace App\Services;

use App\Models\Report;
use App\Models\ReportStatus;
use App\Models\ReportType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportRegistrationService
{
    public function __construct(private readonly CitizenIdentityService $citizenIdentity) {}

    public function register(array $data): Report
    {
        return DB::transaction(function () use ($data): Report {
            $reportType = ReportType::query()
                ->with('department')
                ->where('is_active', true)
                ->findOrFail($data['report_type_id']);

            abort_unless($reportType->department?->is_active, 404);

            $citizen = $this->citizenIdentity->resolveForReport($data['citizen']);

            $registeredStatus = ReportStatus::query()->where('code', 'registrado')->firstOrFail();
            $report = Report::query()->create([
                'folio' => $this->generateFolio(),
                'citizen_id' => $citizen->id,
                'department_id' => $reportType->department_id,
                'report_type_id' => $reportType->id,
                'report_status_id' => $registeredStatus->id,
                'description' => $data['description'],
                'location' => $data['location'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]);

            $report->activities()->create([
                'user_id' => null,
                'type' => 'created',
                'description' => 'Reporte ciudadano recibido por el canal digital.',
                'metadata' => ['source' => 'mcp'],
                'to_status_id' => $registeredStatus->id,
            ]);

            $report->auditLogs()->create([
                'actor_user_id' => null,
                'event' => 'report.created_via_mcp',
                'new_values' => [
                    'folio' => $report->folio,
                    'citizen_id' => $citizen->id,
                    'report_type_id' => $reportType->id,
                    'department_id' => $reportType->department_id,
                    'report_status_id' => $registeredStatus->id,
                    'source' => 'mcp',
                ],
            ]);

            return $report->load(['status', 'reportType', 'department', 'citizen']);
        });
    }

    private function generateFolio(): string
    {
        return 'SIR-'.now()->format('ym').'-'.Str::upper(Str::random(8));
    }
}
