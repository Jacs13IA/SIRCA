<x-app-layout>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="text-uppercase small fw-semibold text-secondary mb-1">Panel de control</p>
            <h1 class="sirca-page-title mb-1">Resumen de reportes</h1>
            <p class="text-body-secondary mb-0">Seguimiento de incidencias ciudadanas y atención por dirección.</p>
        </div>
        <span class="small text-body-secondary">{{ now()->translatedFormat('l, j \d\e F Y') }}</span>
    </div>

    <div class="row g-3 mb-4">
        @foreach ($metrics as $metric)
            <div class="col-6 col-xl-2">
                <section class="card sirca-kpi h-100 {{ $metric['tone'] }}">
                    <div class="card-body">
                        <div class="small text-body-secondary">{{ $metric['label'] }}</div>
                        <div class="fs-3 fw-semibold mt-2">{{ number_format($metric['value']) }}</div>
                    </div>
                </section>
            </div>
        @endforeach
    </div>

    <section class="card border-0 shadow-sm rounded-1">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <div>
                <h2 class="h6 mb-1">Reportes recientes</h2>
                <p class="small text-body-secondary mb-0">Los últimos reportes dentro de tu ámbito de acceso.</p>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('reports.index') }}">Ver bandeja</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Folio</th>
                        <th scope="col">Incidencia</th>
                        <th scope="col">Dirección</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Fecha</th>
                        <th scope="col"><span class="visually-hidden">Acción</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentReports as $report)
                        <tr>
                            <td class="fw-semibold">{{ $report->folio }}</td>
                            <td>{{ $report->reportType->name }}</td>
                            <td>{{ $report->department->name }}</td>
                            <td><span class="badge text-bg-light border">{{ $report->status->name }}</span></td>
                            <td>{{ $report->created_at->format('d/m/Y') }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('reports.show', $report) }}">Abrir</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-5">No hay reportes para mostrar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-app-layout>
