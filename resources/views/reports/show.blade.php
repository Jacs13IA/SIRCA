<x-app-layout>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a class="small text-decoration-none" href="{{ route('reports.index') }}">&larr; Volver a reportes</a>
            <p class="text-uppercase small fw-semibold text-secondary mt-3 mb-1">{{ $report->department->name }}</p>
            <h1 class="sirca-page-title mb-1">{{ $report->folio }}</h1>
            <p class="text-body-secondary mb-0">{{ $report->reportType->name }} · {{ $report->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <span class="badge text-bg-light border fs-6">{{ $report->status->name }}</span>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <section class="card border-0 shadow-sm rounded-1 mb-4">
                <div class="card-header bg-white py-3"><h2 class="h6 mb-0">Datos del reporte</h2></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3 text-body-secondary">Descripción</dt>
                        <dd class="col-sm-9">{{ $report->description }}</dd>
                        <dt class="col-sm-3 text-body-secondary">Ubicación</dt>
                        <dd class="col-sm-9">{{ $report->location }}</dd>
                        <dt class="col-sm-3 text-body-secondary">Ciudadano</dt>
                        <dd class="col-sm-9">{{ $report->citizen->name ?: 'Sin nombre proporcionado' }}</dd>
                        @if ($report->latitude && $report->longitude)
                            <dt class="col-sm-3 text-body-secondary">Coordenadas</dt>
                            <dd class="col-sm-9">{{ $report->latitude }}, {{ $report->longitude }}</dd>
                        @endif
                    </dl>
                </div>
            </section>

            <section class="card border-0 shadow-sm rounded-1 mb-4">
                <div class="card-header bg-white py-3"><h2 class="h6 mb-0">Seguimiento</h2></div>
                <div class="card-body">
                    @forelse ($report->activities->sortByDesc('created_at') as $activity)
                        <article class="border-start border-3 ps-3 pb-3 mb-3">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <strong>{{ $activity->user?->name ?? 'Sistema' }}</strong>
                                <time class="small text-body-secondary">{{ $activity->created_at->format('d/m/Y H:i') }}</time>
                            </div>
                            <p class="mb-1">{{ $activity->description }}</p>
                            @if ($activity->fromStatus || $activity->toStatus)
                                <span class="small text-body-secondary">{{ $activity->fromStatus?->name ?? 'Inicio' }} → {{ $activity->toStatus?->name }}</span>
                            @endif
                        </article>
                    @empty
                        <p class="text-body-secondary mb-0">Aún no hay actividades registradas.</p>
                    @endforelse

                    @can('addActivity', $report)
                        <form class="border-top pt-3 mt-3" method="POST" action="{{ route('reports.activities.store', $report) }}">
                            @csrf
                            <label class="form-label" for="activity-description">Agregar actividad</label>
                            <textarea class="form-control mb-2" id="activity-description" name="description" rows="3" maxlength="3000" required></textarea>
                            <button class="btn btn-outline-primary" type="submit">Registrar actividad</button>
                        </form>
                    @endcan
                </div>
            </section>

            <section class="card border-0 shadow-sm rounded-1">
                <div class="card-header bg-white py-3"><h2 class="h6 mb-0">Evidencias</h2></div>
                <div class="list-group list-group-flush">
                    @forelse ($report->evidences as $evidence)
                        <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
                            <div class="min-w-0"><div class="text-truncate">{{ $evidence->original_name }}</div><small class="text-body-secondary">{{ number_format($evidence->file_size / 1024, 0) }} KB · {{ $evidence->created_at->format('d/m/Y H:i') }}</small></div>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('reports.evidence.download', [$report, $evidence]) }}">Descargar</a>
                        </div>
                    @empty
                        <div class="list-group-item text-body-secondary">No hay evidencias adjuntas.</div>
                    @endforelse
                </div>
                @can('uploadEvidence', $report)
                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('reports.evidence.store', $report) }}" enctype="multipart/form-data">
                            @csrf
                            <label class="form-label" for="evidence">Adjuntar archivo</label>
                            <input class="form-control mb-2" id="evidence" name="evidence" type="file" accept=".jpg,.jpeg,.png,.pdf" required>
                            <div class="form-text mb-3">JPG, PNG o PDF. Máximo 10 MB.</div>
                            <button class="btn btn-outline-primary" type="submit">Subir evidencia</button>
                        </form>
                    </div>
                @endcan
            </section>
        </div>

        <div class="col-12 col-xl-4">
            @can('assign', $report)
                <section class="card border-0 shadow-sm rounded-1 mb-4">
                    <div class="card-header bg-white py-3"><h2 class="h6 mb-0">Asignación</h2></div>
                    <div class="card-body">
                        <p class="small text-body-secondary">Responsable actual: <strong class="text-body">{{ $report->currentAssignment?->assignedUser?->name ?? 'Sin asignar' }}</strong></p>
                        <form method="POST" action="{{ route('reports.assign', $report) }}">
                            @csrf
                            <label class="form-label" for="assigned_user_id">Operador</label>
                            <select class="form-select mb-3" id="assigned_user_id" name="assigned_user_id" required>
                                <option value="">Selecciona operador</option>
                                @foreach ($operators as $operator)
                                    <option value="{{ $operator->id }}">{{ $operator->name }}</option>
                                @endforeach
                            </select>
                            <label class="form-label" for="assignment-notes">Nota (opcional)</label>
                            <textarea class="form-control mb-3" id="assignment-notes" name="notes" rows="2" maxlength="2000"></textarea>
                            <button class="btn btn-primary w-100" type="submit">Asignar responsable</button>
                        </form>
                    </div>
                </section>
            @endcan

            @can('updateStatus', $report)
                <section class="card border-0 shadow-sm rounded-1">
                    <div class="card-header bg-white py-3"><h2 class="h6 mb-0">Actualizar estado</h2></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('reports.status.update', $report) }}">
                            @csrf
                            @method('PATCH')
                            <label class="form-label" for="report_status_code">Nuevo estado</label>
                            <select class="form-select mb-3" id="report_status_code" name="report_status_code" required>
                                <option value="">Selecciona estado</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->code }}">{{ $status->name }}</option>
                                @endforeach
                            </select>
                            <label class="form-label" for="status-description">Nota de seguimiento</label>
                            <textarea class="form-control mb-3" id="status-description" name="description" rows="3" maxlength="3000" required></textarea>
                            <button class="btn btn-primary w-100" type="submit">Guardar cambio</button>
                        </form>
                    </div>
                </section>
            @endcan
        </div>
    </div>
</x-app-layout>