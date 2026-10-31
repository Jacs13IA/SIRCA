<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ReportTypeResource;
use App\Models\ReportType;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReportTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = ReportType::query()
            ->with('department:id,name')
            ->where('is_active', true)
            ->whereHas('department', fn ($query) => $query->where('is_active', true))
            ->orderBy('department_id')
            ->orderBy('name')
            ->get();

        $data = $types->map(fn (ReportType $type) => (new ReportTypeResource($type))->resolve())->all();

        return ApiResponse::success('Tipos de reporte disponibles.', ['report_types' => $data]);
    }
}