<?php

namespace Tests\Feature\Api;

use App\Models\Citizen;
use App\Models\Report;
use App\Models\ReportType;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ReportStatusSeeder;
use Database\Seeders\ReportTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mcp.api_token' => 'test-mcp-api-token']);
        config(['services.citizen_matching.key' => 'report-api-test-matching-key-123456']);
        $this->seed([DepartmentSeeder::class, ReportStatusSeeder::class, ReportTypeSeeder::class]);
    }

    public function test_mcp_bearer_token_is_required(): void
    {
        $this->getJson('/api/v1/report-types')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No autenticado.');
    }

    public function test_api_fails_closed_when_mcp_token_is_not_configured(): void
    {
        config(['services.mcp.api_token' => null]);

        $this->withToken('any-token')
            ->getJson('/api/v1/report-types')
            ->assertServiceUnavailable()
            ->assertJsonPath('success', false);
    }

    public function test_report_type_catalog_returns_active_types_only(): void
    {
        ReportType::query()->where('slug', 'luminaria-apagada')->update(['is_active' => false]);

        $this->withToken('test-mcp-api-token')
            ->getJson('/api/v1/report-types')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissing(['name' => 'Luminaria apagada'])
            ->assertJsonFragment(['name' => 'Árbol en riesgo']);
    }

    public function test_citizen_report_is_created_with_server_generated_folio_and_status(): void
    {
        $reportType = ReportType::query()->where('slug', 'luminaria-apagada')->firstOrFail();

        $response = $this->withToken('test-mcp-api-token')
            ->postJson('/api/v1/reports', $this->reportPayload($reportType->id));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.code', 'registrado')
            ->assertJsonStructure(['success', 'message', 'data' => ['folio', 'report_type', 'department', 'citizen' => ['name']]])
            ->assertJsonMissingPath('data.citizen.email')
            ->assertJsonMissingPath('data.citizen.phone');

        $folio = $response->json('data.folio');
        $this->assertMatchesRegularExpression('/^SIR-\d{4}-[A-Z0-9]{8}$/', $folio);

        $report = Report::query()->where('folio', $folio)->firstOrFail();
        $citizen = Citizen::query()->findOrFail($report->citizen_id);

        $this->assertSame('registrado', $report->status->code);
        $this->assertSame('ana@example.test', $citizen->email);
        $this->assertTrue($citizen->contact_consent);
        $auditValues = json_decode((string) DB::table('audit_logs')
            ->where('auditable_type', Report::class)
            ->where('auditable_id', $report->id)
            ->value('new_values'), true);
        $this->assertSame($citizen->id, $auditValues['citizen_id']);
        $this->assertArrayNotHasKey('email', $auditValues);
        $this->assertArrayNotHasKey('phone', $auditValues);
        $this->assertDatabaseHas('report_activities', [
            'report_id' => $report->id,
            'type' => 'created',
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Report::class,
            'auditable_id' => $report->id,
            'actor_user_id' => null,
            'event' => 'report.created_via_mcp',
        ]);
    }

    public function test_repeated_report_requests_reuse_the_same_citizen_when_both_contacts_match(): void
    {
        $reportType = ReportType::query()->where('slug', 'luminaria-apagada')->firstOrFail();
        $payload = $this->reportPayload($reportType->id);

        $firstResponse = $this->withToken('test-mcp-api-token')
            ->postJson('/api/v1/reports', $payload);
        $secondResponse = $this->postJson('/api/v1/reports', $payload);

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();

        $firstReport = Report::query()->where('folio', $firstResponse->json('data.folio'))->firstOrFail();
        $secondReport = Report::query()->where('folio', $secondResponse->json('data.folio'))->firstOrFail();

        $this->assertDatabaseCount('citizens', 1);
        $this->assertDatabaseCount('reports', 2);
        $this->assertDatabaseCount('citizen_identity_locks', 2);
        $this->assertSame($firstReport->citizen_id, $secondReport->citizen_id);
    }

    public function test_report_matching_normalizes_email_and_mexican_phone_formats(): void
    {
        $reportType = ReportType::query()->where('slug', 'luminaria-apagada')->firstOrFail();
        $firstPayload = $this->reportPayload($reportType->id);
        $secondPayload = $this->reportPayload($reportType->id);
        $secondPayload['citizen']['email'] = ' ANA@EXAMPLE.TEST ';
        $secondPayload['citizen']['phone'] = '+52 981 123 4567';

        $firstResponse = $this->withToken('test-mcp-api-token')->postJson('/api/v1/reports', $firstPayload);
        $secondResponse = $this->postJson('/api/v1/reports', $secondPayload);

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();
        $this->assertDatabaseCount('citizens', 1);
    }

    public function test_a_unique_single_contact_match_reuses_the_existing_citizen(): void
    {
        $reportType = ReportType::query()->where('slug', 'luminaria-apagada')->firstOrFail();
        $firstPayload = $this->reportPayload($reportType->id);
        $firstPayload['citizen']['phone'] = null;
        $secondPayload = $firstPayload;
        $secondPayload['citizen']['name'] = 'Nombre diferente';
        $secondPayload['citizen']['email'] = 'ANA@EXAMPLE.TEST';

        $firstResponse = $this->withToken('test-mcp-api-token')->postJson('/api/v1/reports', $firstPayload);
        $secondResponse = $this->postJson('/api/v1/reports', $secondPayload);

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();
        $this->assertDatabaseCount('citizens', 1);
    }

    public function test_same_name_without_contact_data_does_not_merge_citizens(): void
    {
        $reportType = ReportType::query()->where('slug', 'luminaria-apagada')->firstOrFail();
        $payload = $this->reportPayload($reportType->id);
        $payload['citizen'] = ['name' => 'Ana Ciudadana', 'contact_consent' => false];

        $firstResponse = $this->withToken('test-mcp-api-token')->postJson('/api/v1/reports', $payload);
        $secondResponse = $this->postJson('/api/v1/reports', $payload);

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();
        $this->assertDatabaseCount('citizens', 2);
    }

    public function test_soft_deleted_citizen_is_not_reused_for_a_new_report(): void
    {
        $reportType = ReportType::query()->where('slug', 'luminaria-apagada')->firstOrFail();
        $payload = $this->reportPayload($reportType->id);
        $firstResponse = $this->withToken('test-mcp-api-token')->postJson('/api/v1/reports', $payload);
        $firstReport = Report::query()->where('folio', $firstResponse->json('data.folio'))->firstOrFail();
        $firstCitizenId = $firstReport->citizen_id;
        Citizen::withTrashed()->findOrFail($firstCitizenId)->delete();

        $secondResponse = $this->postJson('/api/v1/reports', $payload);
        $secondResponse->assertCreated();
        $secondReport = Report::query()->where('folio', $secondResponse->json('data.folio'))->firstOrFail();

        $this->assertSame(2, Citizen::withTrashed()->count());
        $this->assertSame(1, Citizen::query()->count());
        $this->assertNotSame($firstCitizenId, $secondReport->citizen_id);
    }

    public function test_invalid_phone_number_is_rejected_before_registration(): void
    {
        $reportType = ReportType::query()->where('slug', 'luminaria-apagada')->firstOrFail();
        $payload = $this->reportPayload($reportType->id);
        $payload['citizen']['phone'] = '+52 123';

        $this->withToken('test-mcp-api-token')
            ->postJson('/api/v1/reports', $payload)
            ->assertBadRequest()
            ->assertJsonStructure(['data' => ['errors' => ['citizen.phone']]]);

        $this->assertDatabaseCount('citizens', 0);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_contact_data_requires_explicit_consent(): void
    {
        $reportType = ReportType::query()->firstOrFail();
        $payload = $this->reportPayload($reportType->id);
        $payload['citizen']['contact_consent'] = false;

        $this->withToken('test-mcp-api-token')
            ->postJson('/api/v1/reports', $payload)
            ->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['errors' => ['citizen.contact_consent']]]);

        $this->assertDatabaseCount('citizens', 0);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_inactive_report_type_is_rejected(): void
    {
        $reportType = ReportType::query()->firstOrFail();
        $reportType->update(['is_active' => false]);

        $this->withToken('test-mcp-api-token')
            ->postJson('/api/v1/reports', $this->reportPayload($reportType->id))
            ->assertBadRequest()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_status_lookup_uses_folio_and_does_not_expose_citizen_contact(): void
    {
        $reportType = ReportType::query()->firstOrFail();
        $createResponse = $this->withToken('test-mcp-api-token')
            ->postJson('/api/v1/reports', $this->reportPayload($reportType->id));
        $folio = $createResponse->json('data.folio');

        $this->withToken('test-mcp-api-token')
            ->getJson('/api/v1/report-status/'.$folio)
            ->assertOk()
            ->assertJsonPath('data.folio', $folio)
            ->assertJsonPath('data.status.code', 'registrado')
            ->assertJsonMissingPath('data.citizen')
            ->assertJsonMissingPath('data.email');
    }

    public function test_unknown_folio_returns_api_json_404(): void
    {
        $this->withToken('test-mcp-api-token')
            ->getJson('/api/v1/report-status/SIR-0000-NOTFOUND')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Recurso no encontrado.');
    }

    public function test_report_search_requires_an_exact_folio_and_returns_minimal_data(): void
    {
        $reportType = ReportType::query()->firstOrFail();
        $created = $this->withToken('test-mcp-api-token')
            ->postJson('/api/v1/reports', $this->reportPayload($reportType->id));
        $folio = $created->json('data.folio');

        $this->withToken('test-mcp-api-token')
            ->getJson('/api/v1/reports?folio='.$folio)
            ->assertOk()
            ->assertJsonCount(1, 'data.reports')
            ->assertJsonPath('data.reports.0.folio', $folio)
            ->assertJsonMissingPath('data.reports.0.citizen')
            ->assertJsonMissingPath('data.reports.0.description')
            ->assertJsonMissingPath('data.reports.0.location');
    }

    public function test_report_search_rejects_missing_or_partial_folio(): void
    {
        $this->withToken('test-mcp-api-token')
            ->getJson('/api/v1/reports')
            ->assertBadRequest()
            ->assertJsonStructure(['data' => ['errors' => ['folio']]]);

        $this->withToken('test-mcp-api-token')
            ->getJson('/api/v1/reports?folio=SIR-2026')
            ->assertBadRequest()
            ->assertJsonStructure(['data' => ['errors' => ['folio']]]);
    }

    public function test_report_search_rejects_broad_filters(): void
    {
        $this->withToken('test-mcp-api-token')
            ->getJson('/api/v1/reports?folio=SIR-2026-AB12CD34&status=registrado')
            ->assertBadRequest()
            ->assertJsonStructure(['data' => ['errors' => ['status']]]);
    }

    private function reportPayload(int $reportTypeId): array
    {
        return [
            'report_type_id' => $reportTypeId,
            'description' => 'Luminaria sin funcionamiento en la esquina.',
            'location' => 'Calle 20, Centro, Champotón',
            'latitude' => 19.3501,
            'longitude' => -90.7202,
            'citizen' => [
                'name' => 'Ana Ciudadana',
                'email' => 'ana@example.test',
                'phone' => '9811234567',
                'contact_consent' => true,
            ],
        ];
    }
}
