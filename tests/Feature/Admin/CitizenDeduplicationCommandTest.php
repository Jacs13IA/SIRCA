<?php

namespace Tests\Feature\Admin;

use App\Models\Citizen;
use App\Models\Department;
use App\Models\Report;
use App\Models\ReportStatus;
use App\Models\ReportType;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ReportStatusSeeder;
use Database\Seeders\ReportTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CitizenDeduplicationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.citizen_matching.key' => 'citizen-dedup-test-matching-key-123456']);
        $this->seed([DepartmentSeeder::class, ReportStatusSeeder::class, ReportTypeSeeder::class]);
    }

    public function test_default_run_is_a_dry_run_and_does_not_change_profiles_or_reports(): void
    {
        $first = $this->createCitizen();
        $second = $this->createCitizen();
        $this->createReport($first, 'SIR-TEST-00000001');
        $this->createReport($second, 'SIR-TEST-00000002');

        Artisan::call('citizens:deduplicate');

        $this->assertSame(2, Citizen::query()->count());
        $this->assertDatabaseCount('citizen_identity_locks', 0);
        $this->assertNull($first->fresh()->email_fingerprint);
        $this->assertStringNotContainsString('persona@example.test', Artisan::output());
    }

    public function test_apply_consolidates_exact_profiles_and_preserves_every_report(): void
    {
        $first = $this->createCitizen();
        $second = $this->createCitizen();
        $firstReport = $this->createReport($first, 'SIR-TEST-00000001');
        $secondReport = $this->createReport($second, 'SIR-TEST-00000002');

        Artisan::call('citizens:deduplicate', ['--apply' => true]);

        $this->assertSame(1, Citizen::query()->count());
        $this->assertSoftDeleted('citizens', ['id' => $second->id]);
        $this->assertSame($first->id, $firstReport->fresh()->citizen_id);
        $this->assertSame($first->id, $secondReport->fresh()->citizen_id);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Citizen::class,
            'auditable_id' => $second->id,
            'event' => 'citizen.duplicates_merged',
        ]);

        Artisan::call('citizens:deduplicate', ['--apply' => true]);
        $this->assertSame(1, Citizen::query()->count());
        $this->assertDatabaseCount('citizen_match_reviews', 0);
    }

    public function test_apply_queues_shared_contact_profiles_without_merging_them(): void
    {
        $first = $this->createCitizen('persona@example.test', '9811234567');
        $second = $this->createCitizen('persona@example.test', '9821234567');

        Artisan::call('citizens:deduplicate', ['--apply' => true]);

        $this->assertSame(2, Citizen::query()->count());
        $this->assertDatabaseHas('citizen_match_reviews', [
            'citizen_id' => $first->id,
            'reason' => 'legacy_shared_contact',
            'status' => 'pending',
        ]);

        Artisan::call('citizens:deduplicate', ['--apply' => true]);
        $this->assertDatabaseCount('citizen_match_reviews', 1);
    }

    private function createCitizen(string $email = 'persona@example.test', string $phone = '9811234567'): Citizen
    {
        return Citizen::query()->create([
            'name' => 'Persona de prueba',
            'email' => $email,
            'phone' => $phone,
            'contact_consent' => true,
        ]);
    }

    private function createReport(Citizen $citizen, string $folio): Report
    {
        $department = Department::query()->where('slug', 'servicios-publicos')->firstOrFail();
        $reportType = ReportType::query()->where('slug', 'luminaria-apagada')->firstOrFail();
        $status = ReportStatus::query()->where('code', 'registrado')->firstOrFail();

        return Report::query()->create([
            'folio' => $folio,
            'citizen_id' => $citizen->id,
            'department_id' => $department->id,
            'report_type_id' => $reportType->id,
            'report_status_id' => $status->id,
            'description' => 'Reporte para probar consolidación.',
            'location' => 'Centro, Champotón',
        ]);
    }
}
