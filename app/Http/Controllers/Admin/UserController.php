<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()->with(['role:id,name,slug', 'department:id,name']);

        if ($request->query('status') === 'inactive') {
            $users->onlyTrashed();
        } elseif ($request->query('status') === 'all') {
            $users->withTrashed();
        }

        if ($search = trim((string) $request->query('search'))) {
            $users->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($request->filled('role_id')) {
            $users->where('role_id', $request->integer('role_id'));
        }

        if ($request->filled('department_id')) {
            $users->where('department_id', $request->integer('department_id'));
        }

        return view('admin.users.index', [
            'users' => $users->orderBy('name')->paginate(20)->withQueryString(),
            'roles' => Role::query()->whereIn('slug', ['administrator', 'supervisor', 'operator'])->orderBy('id')->get(),
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', $this->formOptions());
    }

    public function store(StoreUserRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $user = User::query()->create($request->validated());
        $user->forceFill(['email_verified_at' => now()])->save();
        $auditLog->record($user, $request->user(), 'user.created', [], [
            'role_id' => $user->role_id,
            'department_id' => $user->department_id,
        ]);

        return to_route('admin.users.index')->with('status', 'La cuenta se creó correctamente.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [...$this->formOptions(), 'user' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user, AuditLogService $auditLog): RedirectResponse
    {
        $oldValues = $user->only(['role_id', 'department_id']);
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);
        $auditLog->record($user, $request->user(), 'user.updated', $oldValues, $user->only(['role_id', 'department_id']));

        return to_route('admin.users.index')->with('status', 'La cuenta se actualizó correctamente.');
    }

    public function destroy(User $user, AuditLogService $auditLog): RedirectResponse
    {
        $this->authorize('delete', $user);
        $auditLog->record($user, request()->user(), 'user.deactivated', [], ['deleted' => true]);
        $user->delete();

        return to_route('admin.users.index')->with('status', 'La cuenta se desactivó conservando su historial.');
    }

    public function restore(User $user, AuditLogService $auditLog): RedirectResponse
    {
        $this->authorize('restore', $user);
        $user->restore();
        $auditLog->record($user, request()->user(), 'user.restored', ['deleted' => true], ['deleted' => false]);

        return to_route('admin.users.index', ['status' => 'all'])->with('status', 'La cuenta volvió a estar activa.');
    }

    private function formOptions(): array
    {
        return [
            'roles' => Role::query()->whereIn('slug', ['administrator', 'supervisor', 'operator'])->orderBy('id')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }
}