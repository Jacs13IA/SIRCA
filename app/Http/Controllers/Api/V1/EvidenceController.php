<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UploadEvidenceRequest;
use App\Http\Resources\Api\V1\EvidenceResource;
use App\Models\Report;
use App\Services\EvidenceService;
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
}