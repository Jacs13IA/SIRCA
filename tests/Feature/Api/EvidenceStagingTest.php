<?php

namespace Tests\Feature\Api;

use App\Models\Evidence;
use App\Models\Report;
use App\Models\ReportType;
use App\Models\StagedEvidence;
use App\Services\StagedEvidenceService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\ReportStatusSeeder;
use Database\Seeders\ReportTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceStagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mcp.api_token' => 'test-mcp-api-token']);
        $this->seed([DepartmentSeeder::class, ReportStatusSeeder::class, ReportTypeSeeder::class]);
        Storage::fake('local');
    }

    public function test_private_evidence_can_be_staged_and_gets_a_short_lived_reference(): void
    {
        $response = $this->withHeader('Accept', 'application/json')
            ->post('/evidence-staging', [
                'evidence' => UploadedFile::fake()->create('evidencia.pdf', 32, 'application/pdf'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['reference', 'expires_at']]);

        $reference = $response->json('data.reference');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $reference);

        $staged = StagedEvidence::query()->firstOrFail();
        $this->assertSame(hash('sha256', $reference), $staged->reference_hash);
        $this->assertTrue($staged->expires_at->isFuture());
        Storage::disk('local')->assertExists($staged->file_path);
    }

    public function test_mcp_can_attach_a_staged_reference_once_and_audits_the_evidence(): void
    {
        $report = $this->createReport();
        $reference = $this->stageEvidence();

        $this->withToken('test-mcp-api-token')
            ->postJson("/api/v1/reports/{$report->folio}/evidence/reference", ['reference' => $reference])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.file_path');

        $evidence = Evidence::query()->firstOrFail();
        Storage::disk('local')->assertExists($evidence->file_path);
        $this->assertSame('mcp', $report->activities()->where('type', 'evidence')->firstOrFail()->metadata['source']);
        $this->assertNotNull(StagedEvidence::query()->firstOrFail()->consumed_at);

        $this->withToken('test-mcp-api-token')
            ->postJson("/api/v1/reports/{$report->folio}/evidence/reference", ['reference' => $reference])
            ->assertBadRequest();

        $this->assertDatabaseCount('evidences', 1);
    }

    public function test_staging_rejects_unsupported_file_types(): void
    {
        $this->withHeader('Accept', 'application/json')
            ->post('/evidence-staging', [
                'evidence' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
            ])
            ->assertBadRequest()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('staged_evidences', 0);
    }

    public function test_expired_reference_cannot_be_attached_and_purge_removes_its_file_and_record(): void
    {
        $report = $this->createReport();
        $reference = $this->stageEvidence();
        $staged = StagedEvidence::query()->firstOrFail();
        $path = $staged->file_path;
        $staged->update(['expires_at' => now()->subMinute()]);

        $this->withToken('test-mcp-api-token')
            ->postJson("/api/v1/reports/{$report->folio}/evidence/reference", ['reference' => $reference])
            ->assertBadRequest();

        $this->assertSame(1, app(StagedEvidenceService::class)->purgeExpired());
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('staged_evidences', ['id' => $staged->id]);
    }

    private function createReport(): Report
    {
        $reportType = ReportType::query()->firstOrFail();
        $response = $this->withToken('test-mcp-api-token')->postJson('/api/v1/reports', [
            'report_type_id' => $reportType->id,
            'description' => 'Reporte para probar evidencia temporal.',
            'location' => 'Centro, Champotón',
            'citizen' => ['name' => 'Ciudadanía de prueba'],
        ]);

        $response->assertCreated();

        return Report::query()->where('folio', $response->json('data.folio'))->firstOrFail();
    }

    private function stageEvidence(): string
    {
        $response = $this->withHeader('Accept', 'application/json')
            ->post('/evidence-staging', [
                'evidence' => UploadedFile::fake()->create('evidencia.pdf', 32, 'application/pdf'),
            ]);

        $response->assertCreated();

        return $response->json('data.reference');
    }
}