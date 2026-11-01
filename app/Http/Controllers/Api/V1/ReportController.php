<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterReportRequest;
use App\Http\Requests\Api\V1\SearchReportsRequest;
use App\Http\Resources\Api\V1\ReportResource;
use App\Http\Resources\Api\V1\ReportStatusResource;
use App\Models\Report;
use App\Services\ReportRegistrationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(RegisterReportRequest $request, ReportRegistrationService $registration): JsonResponse
    {
        $report = $registration->register($request->validated());

        return ApiResponse::success('Reporte registrado.', (new ReportResource($report))->resolve($request), 201);
    }

    public function index(SearchReportsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $paginated = Report::query()
            ->with(['reportType:id,name', 'status:id,code,name'])
            ->where('folio', $filters['folio'])
            ->paginate(1);
        $items = $paginated->getCollection()
            ->map(fn (Report $report) => (new ReportStatusResource($report))->resolve($request))
            ->all();

        return ApiResponse::success('Reportes encontrados.', [
            'reports' => $items,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function show(Request $request, string $folio): JsonResponse
    {
        $report = $this->findByFolio($folio);

        return ApiResponse::success('Detalle del reporte.', (new ReportResource($report))->resolve($request));
    }

    public function status(Request $request, string $folio): JsonResponse
    {
        $report = $this->findByFolio($folio);

        return ApiResponse::success('Estado del reporte.', (new ReportStatusResource($report))->resolve($request));
    }

    private function findByFolio(string $folio): Report
    {
        return Report::query()
            ->with(['department:id,name', 'reportType:id,name', 'status:id,code,name', 'citizen:id,name'])
            ->where('folio', $folio)
            ->firstOrFail();
    }
}