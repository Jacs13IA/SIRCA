<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignReportRequest;
use App\Http\Requests\ReportIndexRequest;
use App\Http\Requests\StoreReportActivityRequest;
use App\Http\Requests\UpdateReportStatusRequest;
use App\Models\Department;
use App\Models\Report;
use App\Models\ReportStatus;
use App\Models\ReportType;
use App\Models\User;
use App\Services\ReportAccessService;
use App\Services\ReportWorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ReportController extends Controller
{
    public function index(ReportIndexRequest $request, ReportAccessService $access): View
    {
        $filters = $request->validated();
        $reports = $access->visibleReports($request->user())
            ->with(['department:id,name', 'reportType:id,name', 'status:id,name', 'currentAssignment.assignedUser:id,name']);

        if (filled($filters['folio'] ?? null)) {
            $reports->where('folio', 'like', '%'.trim($filters['folio']).'%');
        }

        if (filled($filters['status'] ?? null)) {
            $reports->whereHas('status', fn ($query) => $query->where('code', $filters['status']));
        }

        if (filled($filters['report_type_id'] ?? null)) {
            $reports->where('report_type_id', $filters['report_type_id']);
        }

        if ($request->user()->hasRole('administrator') && filled($filters['department_id'] ?? null)) {
            $reports->where('department_id', $filters['department_id']);
        }

        if (filled($filters['date_from'] ?? null)) {
            $reports->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (filled($filters['date_to'] ?? null)) {
            $reports->whereDate('created_at', '<=', $filters['date_to']);
        }

        return view('reports.index', [
            'reports' => $reports->latest()->paginate(20)->withQueryString(),
            'statuses' => ReportStatus::query()->orderBy('sort_order')->get(),
            'reportTypes' => ReportType::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => $request->user()->hasRole('administrator')
                ? Department::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'filters' => $filters,
        ]);
    }

    public function show(Report $report): View
    {
        $this->authorize('view', $report);
        $report->load([
            'citizen:id,name',
            'department:id,name',
            'reportType:id,name',
            'status:id,name,code,is_final',
            'assignments.assignedUser:id,name,department_id,role_id',
            'activities.user:id,name',
            'activities.fromStatus:id,name',
            'activities.toStatus:id,name',
            'evidences:id,report_id,original_name,mime_type,file_size,created_at',
        ]);

        $operators = User::query()
            ->where('department_id', $report->department_id)
            ->whereHas('role', fn ($query) => $query->where('slug', 'operator'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('reports.show', [
            'report' => $report,
            'operators' => $operators,
            'statuses' => ReportStatus::query()
                ->whereIn('code', ['en_proceso', 'atendido', 'cerrado', 'cancelado'])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function assign(AssignReportRequest $request, Report $report, ReportWorkflowService $workflow): RedirectResponse
    {
        $assignee = User::query()->findOrFail($request->integer('assigned_user_id'));
        $workflow->assign($report, $assignee, $request->user(), $request->validated('notes'));

        return back()->with('status', 'Responsable asignado correctamente.');
    }

    public function updateStatus(UpdateReportStatusRequest $request, Report $report, ReportWorkflowService $workflow): RedirectResponse
    {
        $status = ReportStatus::query()->where('code', $request->validated('report_status_code'))->firstOrFail();
        $workflow->changeStatus($report, $status, $request->user(), $request->validated('description'));

        return back()->with('status', 'El estado del reporte fue actualizado.');
    }

    public function storeActivity(StoreReportActivityRequest $request, Report $report, ReportWorkflowService $workflow): RedirectResponse
    {
        $workflow->addActivity($report, $request->user(), $request->validated('description'));

        return back()->with('status', 'La actividad quedó registrada.');
    }
}