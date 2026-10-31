<?php

namespace Tests\Feature\Api;

use App\Models\Evidence;
use App\Models\Report;
use App\Models\ReportType;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ReportStatusSeeder;
use Database\Seeders\ReportTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mcp.api_token' => 'test-mcp-api-token']);
        $this->seed([DepartmentSeeder::class, ReportStatusSeeder::class, ReportTypeSeeder::class]);
        Storage::fake('local');
    }

    public function test_mcp_can_upload_private_pdf_evidence_and_records_source(): void
    {
        $report = $this->createReport();

        $response = $this->withToken('test-mcp-api-token')
            ->withHeader('Accept', 'application/json')
            ->post(route('api.v1.reports.evidence.store', $report->folio), [
                'evidence' => UploadedFile::fake()->create('evidencia.pdf', 32, 'application/pdf'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.original_name', 'evidencia.pdf')
            ->assertJsonMissingPath('data.file_path');

        $evidence = Evidence::query()->firstOrFail();
        Storage::disk('local')->assertExists($evidence->file_path);
        $this->assertNull($evidence->uploaded_by_user_id);

        $activity = $report->activities()->where('type', 'evidence')->firstOrFail();
        $this->assertSame('mcp', $activity->metadata['source']);

        $audit = $report->auditLogs()->where('event', 'report.evidence_uploaded')->firstOrFail();
        $this->assertNull($audit->actor_user_id);
        $this->assertSame('mcp', $audit->new_values['source']);
    }

    public function test_mcp_cannot_upload_unsupported_evidence(): void
    {
        $report = $this->createReport();

        $this->withToken('test-mcp-api-token')
            ->withHeader('Accept', 'application/json')
            ->post(route('api.v1.reports.evidence.store', $report->folio), [
                'evidence' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
            ])
            ->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['data' => ['errors' => ['evidence']]]);

        $this->assertDatabaseCount('evidences', 0);
    }

    private function createReport(): Report
    {
        $reportType = ReportType::query()->firstOrFail();

        $response = $this->withToken('test-mcp-api-token')->postJson('/api/v1/reports', [
            'report_type_id' => $reportType->id,
            'description' => 'Reporte para probar evidencia.',
            'location' => 'Centro, Champotón',
            'citizen' => ['name' => 'Ciudadanía de prueba'],
        ]);

        $response->assertCreated();

        return Report::query()->where('folio', $response->json('data.folio'))->firstOrFail();
    }
}