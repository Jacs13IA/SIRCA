<x-app-layout>
    <div class="mb-4"><p class="text-uppercase small fw-semibold text-secondary mb-1">Actividad de cuenta</p><h1 class="sirca-page-title mb-1">Notificaciones</h1><p class="text-body-secondary mb-0">Avisos y asignaciones dirigidos a tu cuenta.</p></div>
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <section class="card border-0 shadow-sm rounded-1"><div class="list-group list-group-flush">
        @forelse ($notifications as $notification)
            <article class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-3 {{ $notification->read_at ? '' : 'bg-body-tertiary' }}">
                <div><h2 class="h6 mb-1">{{ $notification->data['title'] ?? 'Aviso del sistema' }}</h2><p class="mb-1">{{ $notification->data['body'] ?? '' }}</p><time class="small text-body-secondary">{{ $notification->created_at->format('d/m/Y H:i') }}</time></div>
                <div class="d-flex gap-2">@if (! empty($notification->data['url']))<a class="btn btn-sm btn-outline-primary" href="{{ $notification->data['url'] }}">Abrir</a>@endif @if (! $notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary" type="submit">Marcar leída</button></form>@endif</div>
            </article>
        @empty
            <div class="list-group-item text-center text-body-secondary py-5">No tienes notificaciones.</div>
        @endforelse
    </div><div class="card-footer bg-white">{{ $notifications->links() }}</div></section>
</x-app-layout>