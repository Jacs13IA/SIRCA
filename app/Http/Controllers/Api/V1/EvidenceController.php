<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AttachEvidenceReferenceRequest;
use App\Http\Requests\Api\V1\UploadEvidenceRequest;
use App\Http\Resources\Api\V1\EvidenceResource;
use App\Models\Report;
use App\Services\EvidenceService;
use App\Services\StagedEvidenceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class EvidenceController extends Controller
{
    public function store(UploadEvidenceRequest $request, string $folio, EvidenceService $evidenceService): JsonResponse
    {
        $report = Report::query()->where('folio', $folio)->firstOrFail();
        $evidence = $evidenceService->store($report, null, $request->file('evidence'), 'mcp');

        return ApiResponse::success('Evidencia adjuntada.', (new EvidenceResource($evidence))->resolve($request), 201);
    }

    public function storeReference(AttachEvidenceReferenceRequest $request, string $folio, StagedEvidenceService $stagedEvidence): JsonResponse
    {
        $report = Report::query()->where('folio', $folio)->firstOrFail();
        $evidence = $stagedEvidence->attach($request->validated('reference'), $report);

        return ApiResponse::success('Evidencia adjuntada.', (new EvidenceResource($evidence))->resolve($request), 201);
    }
}