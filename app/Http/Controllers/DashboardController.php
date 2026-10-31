<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportStatus;
use App\Services\ReportAccessService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportAccessService $access): View
    {
        $this->authorize('viewAny', Report::class);

        $reports = $access->visibleReports(auth()->user());
        $statusCounts = ReportStatus::query()
            ->whereIn('code', ['registrado', 'asignado', 'en_proceso', 'atendido', 'cerrado'])
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn (ReportStatus $status) => [
                $status->code => (clone $reports)->where('report_status_id', $status->id)->count(),
            ]);

        $recentReports = (clone $reports)
            ->with(['department:id,name', 'reportType:id,name', 'status:id,name'])
            ->latest()
            ->limit(8)
            ->get();

        return view('dashboard', [
            'metrics' => [
                ['label' => 'Total', 'value' => (clone $reports)->count(), 'tone' => ''],
                ['label' => 'Pendientes', 'value' => $statusCounts->get('registrado', 0), 'tone' => 'kpi-gold'],
                ['label' => 'Asignados', 'value' => $statusCounts->get('asignado', 0), 'tone' => ''],
                ['label' => 'En proceso', 'value' => $statusCounts->get('en_proceso', 0), 'tone' => 'kpi-gold'],
                ['label' => 'Atendidos', 'value' => $statusCounts->get('atendido', 0), 'tone' => 'kpi-success'],
                ['label' => 'Cerrados', 'value' => $statusCounts->get('cerrado', 0), 'tone' => 'kpi-success'],
            ],
            'recentReports' => $recentReports,
        ]);
    }
}