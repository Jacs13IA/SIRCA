<?php

namespace Tests\Feature\Api;

use App\Models\Citizen;
use App\Models\Report;
use App\Models\ReportStatus;
use App\Models\ReportType;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ReportStatusSeeder;
use Database\Seeders\ReportTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mcp.api_token' => 'test-mcp-api-token']);
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