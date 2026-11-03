<x-app-layout>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div><p class="text-uppercase small fw-semibold text-secondary mb-1">Administración</p><h1 class="sirca-page-title mb-1">Ciudadanos</h1><p class="text-body-secondary mb-0">Consulta y administra los registros ciudadanos.</p></div>
        <a class="btn btn-primary" href="{{ route('admin.citizens.create') }}">Registrar ciudadano</a>
    </div>
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <section class="card border-0 shadow-sm rounded-1 mb-4"><div class="card-body">
        <form class="row g-3 align-items-end" method="GET">
            <div class="col-12 col-md-7"><label class="form-label" for="search">Buscar por nombre</label><input class="form-control" id="search" name="search" value="{{ request('search') }}" maxlength="255"></div>
            <div class="col-8 col-md-3"><label class="form-label" for="status">Estado</label><select class="form-select" id="status" name="status"><option value="">Activos</option><option value="inactive" @selected(request('status') === 'inactive')>Dados de baja</option><option value="all" @selected(request('status') === 'all')>Todos</option></select></div>
            <div class="col-4 col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit">Filtrar</button></div>
        </form>
    </div></section>
    <section class="card border-0 shadow-sm rounded-1"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Nombre</th><th>Reportes</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>@forelse ($citizens as $citizen)<tr><td class="fw-semibold">{{ $citizen->name ?: 'Sin nombre proporcionado' }}</td><td>{{ $citizen->reports_count }}</td><td><span class="badge {{ $citizen->trashed() ? 'text-bg-secondary' : 'text-bg-success' }}">{{ $citizen->trashed() ? 'Inactivo' : 'Activo' }}</span></td><td class="text-end"><div class="d-inline-flex gap-2">@if ($citizen->trashed())<form method="POST" action="{{ route('admin.citizens.restore', $citizen) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success" type="submit">Restaurar</button></form>@else<a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.citizens.show', $citizen) }}">Ver</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.citizens.edit', $citizen) }}">Editar</a><form method="POST" action="{{ route('admin.citizens.destroy', $citizen) }}" onsubmit="return confirm('¿Dar de baja a este ciudadano? Se conservarán sus reportes.')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Dar de baja</button></form>@endif</div></td></tr>@empty<tr><td colspan="4" class="text-center text-body-secondary py-5">No se encontraron ciudadanos.</td></tr>@endforelse</tbody>
    </table></div>@if ($citizens->hasPages())<div class="card-footer bg-white">{{ $citizens->links() }}</div>@endif</section>
</x-app-layout>