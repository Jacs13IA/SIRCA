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
use Database\Seeders\ReportStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReportStatusSeeder::class);
    }

    public function test_roleless_user_cannot_access_report_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
    }

    public function test_operator_only_sees_reports_assigned_to_them(): void
    {
        [$department, $otherDepartment] = $this->departments();
        $operator = $this->userWithRole('operator', $department);
        $assignedReport = $this->reportFor($department);
        $unassignedReport = $this->reportFor($department, 'FOLIO-UNASSIGNED');
        $this->reportFor($otherDepartment, 'FOLIO-OTHER-DEPARTMENT');

        ReportAssignment::query()->create([
            'report_id' => $assignedReport->id,
            'assigned_user_id' => $operator->id,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($operator)->get(route('reports.index'));

        $response->assertOk()->assertSee('FOLIO-001')->assertDontSee('FOLIO-UNASSIGNED')->assertDontSee('FOLIO-OTHER-DEPARTMENT');
    }

    public function test_supervisor_cannot_open_report_from_another_department(): void
    {
        [$department, $otherDepartment] = $this->departments();
        $supervisor = $this->userWithRole('supervisor', $department);
        $report = $this->reportFor($otherDepartment);

        $this->actingAs($supervisor)->get(route('reports.show', $report))->assertForbidden();
    }

    public function test_supervisor_can_open_report_detail(): void
    {
        [$department] = $this->departments();
        $supervisor = $this->userWithRole('supervisor', $department);
        $report = $this->reportFor($department);

        $this->actingAs($supervisor)
            ->get(route('reports.show', $report))
            ->assertOk()
            ->assertSee($report->folio)
            ->assertSee('Datos del reporte');
    }

    public function test_assignment_moves_report_to_assigned_and_records_activity_and_audit(): void
    {
        [$department] = $this->departments();
        $supervisor = $this->userWithRole('supervisor', $department);
        $operator = $this->userWithRole('operator', $department);
        $report = $this->reportFor($department);

        $this->actingAs($supervisor)
            ->post(route('reports.assign', $report), ['assigned_user_id' => $operator->id])
            ->assertRedirect();

        $this->assertSame('asignado', $report->fresh()->status->code);
        $this->assertDatabaseHas('report_assignments', [
            'report_id' => $report->id,
            'assigned_user_id' => $operator->id,
            'unassigned_at' => null,
        ]);
        $this->assertDatabaseHas('report_activities', ['report_id' => $report->id, 'type' => 'assignment']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'report.assigned', 'auditable_id' => $report->id]);
        $notification = $operator->notifications()->firstOrFail();
        $this->assertSame('Nuevo reporte asignado', $notification->data['title']);
        $this->assertSame($report->folio, $notification->data['folio']);
        $this->assertSame(route('reports.show', $report), $notification->data['url']);
    }

    public function test_closed_reports_cannot_be_updated(): void
    {
        [$department] = $this->departments();
        $supervisor = $this->userWithRole('supervisor', $department);
        $report = $this->reportFor($department, status: 'cerrado');
        $report->forceFill(['closed_at' => now()])->save();

        $this->actingAs($supervisor)
            ->patch(route('reports.status.update', $report), [
                'report_status_code' => 'en_proceso',
                'description' => 'Intento de cambio',
            ])
            ->assertForbidden();
    }

    public function test_valid_status_transition_is_recorded_and_skipped_transition_is_rejected(): void
    {
        [$department] = $this->departments();
        $supervisor = $this->userWithRole('supervisor', $department);
        $report = $this->reportFor($department, status: 'asignado');

        $this->actingAs($supervisor)
            ->patch(route('reports.status.update', $report), [
                'report_status_code' => 'en_proceso',
                'description' => 'La cuadrilla inició la atención.',
            ])
            ->assertRedirect();

        $this->assertSame('en_proceso', $report->fresh()->status->code);
        $this->assertDatabaseHas('report_activities', ['report_id' => $report->id, 'type' => 'status_change']);

        $this->patch(route('reports.status.update', $report), [
            'report_status_code' => 'cerrado',
            'description' => 'Intento de saltar etapas.',
        ])->assertSessionHasErrors('report_status_code');

        $this->assertSame('en_proceso', $report->fresh()->status->code);
    }

    private function departments(): array
    {
        return [
            Department::query()->create(['name' => 'Servicios Públicos', 'slug' => 'servicios-publicos', 'is_active' => true]),
            Department::query()->create(['name' => 'Protección Civil', 'slug' => 'proteccion-civil', 'is_active' => true]),
        ];
    }

    private function userWithRole(string $slug, Department $department): User
    {
        $role = Role::query()->firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);

        return User::factory()->create([
            'role_id' => $role->id,
            'department_id' => $department->id,
        ]);
    }

    private function reportFor(Department $department, string $folio = 'FOLIO-001', string $status = 'registrado'): Report
    {
        $reportStatus = ReportStatus::query()->firstOrCreate(
            ['code' => $status],
            ['name' => ucfirst($status), 'sort_order' => 1, 'is_final' => $status === 'cerrado'],
        );
        $type = ReportType::query()->firstOrCreate(
            ['department_id' => $department->id, 'slug' => 'luminaria-apagada'],
            ['name' => 'Luminaria apagada', 'is_active' => true],
        );
        $citizen = Citizen::query()->create(['name' => 'Ciudadano de prueba']);

        return Report::query()->create([
            'folio' => $folio,
            'citizen_id' => $citizen->id,
            'department_id' => $department->id,
            'report_type_id' => $type->id,
            'report_status_id' => $reportStatus->id,
            'description' => 'Reporte de prueba',
            'location' => 'Champotón',
        ]);
    }
}