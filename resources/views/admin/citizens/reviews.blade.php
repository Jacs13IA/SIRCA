<x-app-layout>
    <div class="mb-4"><p class="text-uppercase small fw-semibold text-secondary mb-1">Administración</p><h1 class="sirca-page-title mb-1">Revisar coincidencias</h1><p class="text-body-secondary mb-0">Confirma si los reportes corresponden a un ciudadano existente.</p></div>
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @forelse ($reviews as $review)
        <section class="card border-0 shadow-sm rounded-1 mb-3">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between gap-2"><div><h2 class="h6 mb-1">{{ $review->citizen?->name ?: 'Sin nombre proporcionado' }}</h2><span class="small text-body-secondary">Caso #{{ $review->id }} · {{ $review->created_at->format('d/m/Y H:i') }}</span><div class="small text-body-secondary">Correo {{ $contactHints->get($review->citizen_id)['email'] ?? 'no registrado' }} · Teléfono {{ $contactHints->get($review->citizen_id)['phone'] ?? 'no registrado' }}</div></div><span class="badge text-bg-warning">{{ match ($review->reason) {'conflicting_contacts' => 'Contactos en conflicto', 'ambiguous_match' => 'Varias coincidencias', default => 'Coincidencia parcial'} }}</span></div>
            <div class="card-body">
                <p class="small text-body-secondary">Candidatos existentes</p>
                <ul class="mb-4">@foreach ($review->candidate_citizen_ids as $candidateId)<li>{{ $candidates->get($candidateId)?->name ?: 'Ciudadano #'.$candidateId }} (ID {{ $candidateId }}) <span class="small text-body-secondary">Correo {{ $contactHints->get($candidateId)['email'] ?? 'no registrado' }} · Teléfono {{ $contactHints->get($candidateId)['phone'] ?? 'no registrado' }}</span></li>@endforeach</ul>
                <div class="d-flex flex-wrap gap-3 align-items-end">
                    <form class="d-flex flex-wrap gap-2 align-items-end" method="POST" action="{{ route('admin.citizen-match-reviews.resolve', $review) }}">@csrf @method('PATCH')
                        <input type="hidden" name="resolution" value="same_person">
                        <div><label class="form-label" for="canonical-{{ $review->id }}">Fusionar con</label><select class="form-select" id="canonical-{{ $review->id }}" name="canonical_citizen_id" required><option value="">Selecciona ciudadano</option>@foreach ($review->candidate_citizen_ids as $candidateId)@if ($candidate = $candidates->get($candidateId))<option value="{{ $candidateId }}">{{ $candidate->name ?: 'Sin nombre' }} · #{{ $candidateId }}</option>@endif @endforeach</select></div>
                        <button class="btn btn-primary" type="submit">Misma persona</button>
                    </form>
                    <form method="POST" action="{{ route('admin.citizen-match-reviews.resolve', $review) }}">@csrf @method('PATCH')<input type="hidden" name="resolution" value="distinct"><button class="btn btn-outline-secondary" type="submit">Personas distintas</button></form>
                </div>
                @error('canonical_citizen_id')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </div>
        </section>
    @empty
        <section class="card border-0 shadow-sm rounded-1"><div class="card-body text-center text-body-secondary py-5">No hay coincidencias pendientes de revisión.</div></section>
    @endforelse
    @if ($reviews->hasPages())<div class="mt-3">{{ $reviews->links() }}</div>@endif
</x-app-layout>