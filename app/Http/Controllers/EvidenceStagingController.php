<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStagedEvidenceRequest;
use App\Services\StagedEvidenceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class EvidenceStagingController extends Controller
{
    public function store(StoreStagedEvidenceRequest $request, StagedEvidenceService $stagedEvidence): JsonResponse
    {
        $staged = $stagedEvidence->stage($request->file('evidence'));

        return ApiResponse::success('Evidencia temporal recibida.', $staged, 201);
    }
}