<?php

namespace Tests\Feature\Admin;

use App\Models\Citizen;
use App\Models\CitizenMatchReview;
use App\Models\Department;
use App\Models\Report;
use App\Models\ReportStatus;
use App\Models\ReportType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ReportStatusSeeder;
use Database\Seeders\ReportTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CitizenAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, DepartmentSeeder::class, ReportStatusSeeder::class, ReportTypeSeeder::class]);
        config(['services.mcp.api_token' => 'test-mcp-api-token']);
        config(['services.citizen_matching.key' => 'citizen-admin-test-matching-key-123456']);
    }

    public function test_only_global_administrators_can_open_citizen_management(): void
    {
        $administrator = $this->userWithRole('administrator');
        $department = Department::query()->where('slug', 'servicios-publicos')->firstOrFail();
        $departmentAdministrator = $this->userWithRole('administrator', $department);
        $operator = $this->userWithRole('operator');

        $this->actingAs($administrator)->get(route('admin.citizens.index'))->assertOk();
        $this->actingAs($departmentAdministrator)->get(route('admin.citizens.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.citizens.index'))->assertForbidden();

        $citizen = Citizen::query()->create(['name' => 'Registro protegido']);
        $deletedCitizen = Citizen::query()->create(['name' => 'Registro eliminado']);
        $deletedCitizen->delete();

        $this->actingAs($operator)
            ->post(route('admin.citizens.store'), ['name' => 'No autorizado', 'contact_consent' => '0'])
            ->assertForbidden();
        $this->put(route('admin.citizens.update', $citizen), ['name' => 'No autorizado', 'contact_consent' => '0'])
            ->assertForbidden();
        $this->delete(route('admin.citizens.destroy', $citizen))->assertForbidden();
        $this->patch(route('admin.citizens.restore', $deletedCitizen))->assertForbidden();
    }

    public function test_contact_information_requires_consent_and_is_encrypted(): void
    {
        $administrator = $this->userWithRole('administrator');

        $this->actingAs($administrator)->post(route('admin.citizens.store'), [
            'name' => 'Ciudadano de prueba',
            'email' => 'ciudadano@example.test',
            'phone' => '9811234567',
            'contact_consent' => '0',
        ])->assertSessionHasErrors('contact_consent');

        $this->assertDatabaseCount('citizens', 0);

        $this->post(route('admin.citizens.store'), [
            'name' => 'Ciudadano de prueba',
            'email' => 'ciudadano@example.test',
            'phone' => '9811234567',
            'contact_consent' => '1',
        ])->assertRedirect(route('admin.citizens.index'));

        $citizen = Citizen::query()->firstOrFail();
        $this->assertSame('ciudadano@example.test', $citizen->email);
        $this->assertSame('9811234567', $citizen->phone);
        $this->assertNotSame('ciudadano@example.test', DB::table('citizens')->where('id', $citizen->id)->value('email'));
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'citizen.created',
            'auditable_type' => Citizen::class,
            'auditable_id' => $citizen->id,
        ]);

        $auditPayload = DB::table('audit_logs')->where('auditable_id', $citizen->id)->value('new_values');
        $this->assertStringNotContainsString('ciudadano@example.test', (string) $auditPayload);
        $this->assertStringNotContainsString('9811234567', (string) $auditPayload);
    }

    public function test_global_administrator_can_view_update_deactivate_and_restore_citizen(): void
    {
        $administrator = $this->userWithRole('administrator');
        $citizen = Citizen::query()->create([
            'name' => 'Nombre anterior',
            'contact_consent' => false,
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.citizens.show', $citizen))
            ->assertOk()
            ->assertSee('Nombre anterior');

        $this->put(route('admin.citizens.update', $citizen), [
            'name' => 'Nombre actualizado',
            'email' => null,
            'phone' => null,
            'contact_consent' => '0',
        ])->assertRedirect(route('admin.citizens.show', $citizen));

        $this->assertSame('Nombre actualizado', $citizen->fresh()->name);

        $this->delete(route('admin.citizens.destroy', $citizen))
            ->assertRedirect(route('admin.citizens.index'));
        $this->assertSoftDeleted('citizens', ['id' => $citizen->id]);

        $this->patch(route('admin.citizens.restore', $citizen))
            ->assertRedirect(route('admin.citizens.index', ['status' => 'all']));
        $this->assertDatabaseHas('citizens', ['id' => $citizen->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'citizen.updated', 'auditable_id' => $citizen->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'citizen.deactivated', 'auditable_id' => $citizen->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'citizen.restored', 'auditable_id' => $citizen->id]);
    }

    public function test_global_administrator_can_filter_active_deleted_and_named_citizens(): void
    {
        $administrator = $this->userWithRole('administrator');
        Citizen::query()->create(['name' => 'Ana Ciudadana']);
        $deletedCitizen = Citizen::query()->create(['name' => 'Beto Ciudadano']);
        $deletedCitizen->delete();

        $this->actingAs($administrator)
            ->get(route('admin.citizens.index', ['search' => 'Ana']))
            ->assertOk()
            ->assertSee('Ana Ciudadana')
            ->assertDontSee('Beto Ciudadano');

        $this->get(route('admin.citizens.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Beto Ciudadano')
            ->assertDontSee('Ana Ciudadana');

        $this->get(route('admin.citizens.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee('Ana Ciudadana')
            ->assertSee('Beto Ciudadano');
    }

    public function test_global_administrator_can_merge_a_reviewed_duplicate_and_preserve_reports(): void
    {
        $administrator = $this->userWithRole('administrator');
        $first = $this->createCitizenThroughApi('ana@example.test', '9811234567');
        $second = $this->createCitizenThroughApi('beto@example.test', '9821234567');
        $provisional = $this->createCitizenThroughApi('ana@example.test', '9821234567');
        $provisionalReportIds = $provisional->reports()->pluck('id')->all();
        $review = CitizenMatchReview::query()->sole();

        $operator = $this->userWithRole('operator');
        $this->actingAs($operator)->get(route('admin.citizen-match-reviews.index'))->assertForbidden();

        $this->actingAs($administrator)
            ->get(route('admin.citizen-match-reviews.index'))
            ->assertOk()
            ->assertSee('a***@example.test')
            ->assertDontSee('ana@example.test')
            ->assertDontSee('9811234567');

        $this->actingAs($administrator)
            ->patch(route('admin.citizen-match-reviews.resolve', $review), [
                'resolution' => 'same_person',
                'canonical_citizen_id' => $first->id,
            ])
            ->assertRedirect(route('admin.citizen-match-reviews.index'));

        $this->assertSoftDeleted('citizens', ['id' => $provisional->id]);
        $this->assertDatabaseHas('reports', ['id' => $provisionalReportIds[0], 'citizen_id' => $first->id]);
        $this->assertDatabaseHas('citizen_match_reviews', [
            'id' => $review->id,
            'status' => 'resolved',
            'resolution' => 'same_person',
            'resolved_by' => $administrator->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'citizen.duplicates_merged',
            'auditable_id' => $provisional->id,
        ]);
    }

    public function test_global_administrator_can_mark_reviewed_profiles_as_distinct(): void
    {
        $administrator = $this->userWithRole('administrator');
        $this->createCitizenThroughApi('ana@example.test', '9811234567');
        $this->createCitizenThroughApi('beto@example.test', '9821234567');
        $provisional = $this->createCitizenThroughApi('ana@example.test', '9821234567');
        $review = CitizenMatchReview::query()->sole();

        $this->actingAs($administrator)
            ->patch(route('admin.citizen-match-reviews.resolve', $review), ['resolution' => 'distinct'])
            ->assertRedirect(route('admin.citizen-match-reviews.index'));

        $this->assertDatabaseHas('citizens', ['id' => $provisional->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('citizen_match_reviews', [
            'id' => $review->id,
            'status' => 'resolved',
            'resolution' => 'distinct',
        ]);
    }

    public function test_contact_edits_update_fingerprints_used_by_report_matching(): void
    {
        $administrator = $this->userWithRole('administrator');
        $citizen = $this->createCitizenThroughApi('old@example.test', '9811234567');

        $this->actingAs($administrator)
            ->put(route('admin.citizens.update', $citizen), [
                'name' => $citizen->name,
                'email' => 'new@example.test',
                'phone' => '9821234567',
                'contact_consent' => '1',
            ])
            ->assertRedirect(route('admin.citizens.show', $citizen));

        $this->createCitizenThroughApi('new@example.test', '+52 982 123 4567');

        $this->assertSame(1, Citizen::query()->count());
    }

    public function test_soft_deleted_citizen_remains_available_from_report_history(): void
    {
        $administrator = $this->userWithRole('administrator');
        $department = Department::query()->where('slug', 'servicios-publicos')->firstOrFail();
        $reportType = ReportType::query()->where('slug', 'luminaria-apagada')->firstOrFail();
        $status = ReportStatus::query()->create([
            'code' => 'registered',
            'name' => 'Registrado',
        ]);
        $citizen = Citizen::query()->create([
            'name' => 'Ciudadano con reporte',
            'contact_consent' => false,
        ]);
        $report = Report::query()->create([
            'folio' => 'SIRCA-TEST-0001',
            'citizen_id' => $citizen->id,
            'department_id' => $department->id,
            'report_type_id' => $reportType->id,
            'report_status_id' => $status->id,
            'description' => 'Luminaria fuera de servicio.',
            'location' => 'Centro, Champotón',
        ]);

        $this->actingAs($administrator)->delete(route('admin.citizens.destroy', $citizen));

        $this->assertSame('Ciudadano con reporte', $report->fresh()->citizen->name);
        $this->actingAs($administrator)
            ->get(route('reports.show', $report))
            ->assertOk()
            ->assertSee('Ciudadano con reporte')
            ->assertSee('SIRCA-TEST-0001');
    }

    private function userWithRole(string $slug, ?Department $department = null): User
    {
        $role = Role::query()->where('slug', $slug)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'department_id' => $department?->id,
        ]);
    }

    private function createCitizenThroughApi(string $email, string $phone): Citizen
    {
        $reportType = ReportType::query()->where('slug', 'luminaria-apagada')->firstOrFail();
        $response = $this->withToken('test-mcp-api-token')
            ->postJson('/api/v1/reports', [
                'report_type_id' => $reportType->id,
                'description' => 'Prueba de coincidencia de contacto.',
                'location' => 'Centro, Champotón',
                'citizen' => [
                    'name' => 'Ciudadano de prueba',
                    'email' => $email,
                    'phone' => $phone,
                    'contact_consent' => true,
                ],
            ]);

        $response->assertCreated();
        $report = Report::query()->where('folio', $response->json('data.folio'))->firstOrFail();

        return Citizen::query()->findOrFail($report->citizen_id);
    }
}
