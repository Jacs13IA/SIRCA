<?php

namespace Tests\Feature\Admin;

use App\Models\Citizen;
use App\Models\Department;
use App\Models\Report;
use App\Models\ReportAssignment;
use App\Models\ReportStatus;
use App\Models\ReportType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_operator_can_upload_and_download_pdf_evidence(): void
    {
        Storage::fake('local');
        [$report, $operator] = $this->reportAssignedToOperator();

        $this->actingAs($operator)
            ->post(route('reports.evidence.store', $report), ['evidence' => UploadedFile::fake()->create('foto.pdf', 32, 'application/pdf')])
            ->assertRedirect();

        $evidence = $report->evidences()->firstOrFail();
        Storage::disk('local')->assertExists($evidence->file_path);

        $this->get(route('reports.evidence.download', [$report, $evidence]))->assertOk();
    }

    public function test_assigned_operator_cannot_upload_disallowed_file_type(): void
    {
        Storage::fake('local');
        [$report, $operator] = $this->reportAssignedToOperator();

        $this->actingAs($operator)
            ->post(route('reports.evidence.store', $report), ['evidence' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')])
            ->assertSessionHasErrors('evidence');

        $this->assertDatabaseCount('evidences', 0);
    }

    private function reportAssignedToOperator(): array
    {
        $department = Department::query()->create(['name' => 'Servicios Públicos', 'slug' => 'servicios-publicos', 'is_active' => true]);
        $role = Role::query()->firstOrCreate(['slug' => 'operator'], ['name' => 'Operador']);
        $operator = User::factory()->create(['role_id' => $role->id, 'department_id' => $department->id]);
        $status = ReportStatus::query()->create(['code' => 'registrado', 'name' => 'Registrado', 'sort_order' => 1]);
        $type = ReportType::query()->create(['department_id' => $department->id, 'name' => 'Luminaria', 'slug' => 'luminaria', 'is_active' => true]);
        $citizen = Citizen::query()->create(['name' => 'Ciudadano']);
        $report = Report::query()->create([
            'folio' => 'EVIDENCE-001',
            'citizen_id' => $citizen->id,
            'department_id' => $department->id,
            'report_type_id' => $type->id,
            'report_status_id' => $status->id,
            'description' => 'Reporte con evidencia',
            'location' => 'Champotón',
        ]);
        ReportAssignment::query()->create(['report_id' => $report->id, 'assigned_user_id' => $operator->id, 'assigned_at' => now()]);

        return [$report, $operator];
    }
}