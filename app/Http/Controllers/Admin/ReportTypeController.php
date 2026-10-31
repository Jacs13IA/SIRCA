<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportTypeRequest;
use App\Http\Requests\UpdateReportTypeRequest;
use App\Models\Department;
use App\Models\ReportType;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportTypeController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ReportType::class);

        $types = ReportType::query()->with('department:id,name')->withCount('reports');

        if ($request->filled('department_id')) {
            $types->where('department_id', $request->integer('department_id'));
        }

        if ($request->query('active') !== null && in_array($request->query('active'), ['0', '1'], true)) {
            $types->where('is_active', (bool) $request->query('active'));
        }

        return view('admin.report-types.index', [
            'reportTypes' => $types->orderBy('name')->paginate(20)->withQueryString(),
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ReportType::class);

        return view('admin.report-types.create', ['departments' => Department::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(StoreReportTypeRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $reportType = ReportType::query()->create([...$request->validated(), 'is_active' => true]);
        $auditLog->record($reportType, $request->user(), 'report_type.created', [], $reportType->only(['department_id', 'name', 'slug']));

        return to_route('admin.report-types.index')->with('status', 'El tipo de reporte se creó correctamente.');
    }

    public function edit(ReportType $report_type): View
    {
        $this->authorize('update', $report_type);

        return view('admin.report-types.edit', [
            'reportType' => $report_type,
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateReportTypeRequest $request, ReportType $report_type, AuditLogService $auditLog): RedirectResponse
    {
        $oldValues = $report_type->only(['department_id', 'name', 'slug', 'is_active']);
        $report_type->update($request->validated());
        $auditLog->record($report_type, $request->user(), 'report_type.updated', $oldValues, $report_type->only(['department_id', 'name', 'slug', 'is_active']));

        return to_route('admin.report-types.index')->with('status', 'El tipo de reporte se actualizó correctamente.');
    }

    public function toggleActive(Request $request, ReportType $report_type, AuditLogService $auditLog): RedirectResponse
    {
        $this->authorize('update', $report_type);
        $oldValues = ['is_active' => $report_type->is_active];
        $report_type->update(['is_active' => ! $report_type->is_active]);
        $auditLog->record($report_type, $request->user(), 'report_type.status_changed', $oldValues, ['is_active' => $report_type->is_active]);

        return back()->with('status', 'El tipo de reporte se actualizó.');
    }
}