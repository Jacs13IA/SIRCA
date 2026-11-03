<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCitizenRequest;
use App\Http\Requests\UpdateCitizenRequest;
use App\Models\Citizen;
use App\Services\CitizenAdministrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CitizenController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Citizen::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:inactive,all'],
        ]);

        $citizens = Citizen::query()->withCount('reports');

        if (($filters['status'] ?? null) === 'inactive') {
            $citizens->onlyTrashed();
        } elseif (($filters['status'] ?? null) === 'all') {
            $citizens->withTrashed();
        }

        if ($search = trim($filters['search'] ?? '')) {
            $citizens->where('name', 'like', "%{$search}%");
        }

        return view('admin.citizens.index', [
            'citizens' => $citizens->orderBy('name')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Citizen::class);

        return view('admin.citizens.create');
    }

    public function store(StoreCitizenRequest $request, CitizenAdministrationService $citizens): RedirectResponse
    {
        $citizens->create($request->validated(), $request->user());

        return to_route('admin.citizens.index')->with('status', 'El ciudadano se registró correctamente.');
    }

    public function show(Citizen $citizen): View
    {
        $this->authorize('view', $citizen);

        return view('admin.citizens.show', [
            'citizen' => $citizen,
            'reports' => $citizen->reports()->with('status')->latest()->paginate(10),
        ]);
    }

    public function edit(Citizen $citizen): View
    {
        $this->authorize('update', $citizen);

        return view('admin.citizens.edit', compact('citizen'));
    }

    public function update(UpdateCitizenRequest $request, Citizen $citizen, CitizenAdministrationService $citizens): RedirectResponse
    {
        $citizens->update($citizen, $request->validated(), $request->user());

        return to_route('admin.citizens.show', $citizen)->with('status', 'Los datos del ciudadano se actualizaron correctamente.');
    }

    public function destroy(Citizen $citizen, Request $request, CitizenAdministrationService $citizens): RedirectResponse
    {
        $this->authorize('delete', $citizen);
        $citizens->deactivate($citizen, $request->user());

        return to_route('admin.citizens.index')->with('status', 'El ciudadano se dio de baja conservando el historial de reportes.');
    }

    public function restore(Citizen $citizen, Request $request, CitizenAdministrationService $citizens): RedirectResponse
    {
        $this->authorize('restore', $citizen);
        $citizens->restore($citizen, $request->user());

        return to_route('admin.citizens.index', ['status' => 'all'])->with('status', 'El ciudadano volvió a estar activo.');
    }
}
