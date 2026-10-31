<x-app-layout>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="text-uppercase small fw-semibold text-secondary mb-1">Atención ciudadana</p>
            <h1 class="sirca-page-title mb-1">Bandeja de reportes</h1>
            <p class="text-body-secondary mb-0">Consulta y seguimiento según tu ámbito de acceso.</p>
        </div>
        <span class="small text-body-secondary">{{ $reports->total() }} resultados</span>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="status">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    <section class="card border-0 shadow-sm rounded-1 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.index') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="folio">Folio</label>
                    <input class="form-control" id="folio" name="folio" value="{{ $filters['folio'] ?? '' }}" placeholder="Buscar folio">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="status">Estado</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->code }}" @selected(($filters['status'] ?? '') === $status->code)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="report_type_id">Tipo</label>
                    <select class="form-select" id="report_type_id" name="report_type_id">
                        <option value="">Todos</option>
                        @foreach ($reportTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) ($filters['report_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($departments->isNotEmpty())
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="department_id">Dirección</label>
                        <select class="form-select" id="department_id" name="department_id">
                            <option value="">Todas</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-6 col-md-2">
                    <label class="form-label" for="date_from">Desde</label>
                    <input class="form-control" id="date_from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="date_to">Hasta</label>
                    <input class="form-control" id="date_to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Filtrar</button>
                    <a class="btn btn-outline-secondary" href="{{ route('reports.index') }}">Limpiar</a>
                </div>
            </form>
        </div>
    </section>

    <section class="card border-0 shadow-sm rounded-1">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Folio</th>
                        <th scope="col">Incidencia</th>
                        <th scope="col">Dirección</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Responsable</th>
                        <th scope="col">Registro</th>
                        <th scope="col"><span class="visually-hidden">Acción</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr>
                            <td class="fw-semibold text-nowrap">{{ $report->folio }}</td>
                            <td>
                                <div class="fw-medium">{{ $report->reportType->name }}</div>
                                <div class="small text-body-secondary text-truncate" style="max-width: 24rem">{{ $report->description }}</div>
                            </td>
                            <td>{{ $report->department->name }}</td>
                            <td><span class="badge text-bg-light border">{{ $report->status->name }}</span></td>
                            <td>{{ $report->currentAssignment?->assignedUser?->name ?? 'Sin asignar' }}</td>
                            <td class="text-nowrap">{{ $report->created_at->format('d/m/Y') }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('reports.show', $report) }}">Revisar</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-body-secondary py-5">No hay reportes que coincidan con los filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($reports->hasPages())
            <div class="card-footer bg-white">{{ $reports->links() }}</div>
        @endif
    </section>
</x-app-layout>