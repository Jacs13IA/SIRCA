<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::query()->withCount(['users', 'reports']);

        if ($search = trim((string) $request->query('search'))) {
            $departments->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%"));
        }

        return view('admin.departments.index', ['departments' => $departments->orderBy('name')->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        $this->authorize('create', Department::class);

        return view('admin.departments.create');
    }

    public function store(StoreDepartmentRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $department = Department::query()->create([...$request->validated(), 'is_active' => true]);
        $auditLog->record($department, $request->user(), 'department.created', [], $department->only(['name', 'slug']));

        return to_route('admin.departments.index')->with('status', 'La dirección se creó correctamente.');
    }

    public function edit(Department $department): View
    {
        $this->authorize('update', $department);

        return view('admin.departments.edit', compact('department'));
    }

    public function update(UpdateDepartmentRequest $request, Department $department, AuditLogService $auditLog): RedirectResponse
    {
        $oldValues = $department->only(['name', 'slug', 'is_active']);
        $department->update($request->validated());
        $auditLog->record($department, $request->user(), 'department.updated', $oldValues, $department->only(['name', 'slug', 'is_active']));

        return to_route('admin.departments.index')->with('status', 'La dirección se actualizó correctamente.');
    }

    public function toggleActive(Request $request, Department $department, AuditLogService $auditLog): RedirectResponse
    {
        $this->authorize('update', $department);

        $oldValues = ['is_active' => $department->is_active];
        $department->update(['is_active' => ! $department->is_active]);
        $auditLog->record($department, $request->user(), 'department.status_changed', $oldValues, ['is_active' => $department->is_active]);

        return back()->with('status', 'El estado de la dirección se actualizó.');
    }
}