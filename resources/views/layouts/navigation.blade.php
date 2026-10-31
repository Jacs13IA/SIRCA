<header class="sirca-topbar navbar navbar-expand-md px-3 px-lg-4">
    <x-sirca-brand :href="route('dashboard')" appearance="dark" class="me-auto" />
    <div class="d-flex align-items-center gap-3">
        <span class="d-none d-sm-inline text-body-secondary small">{{ Auth::user()->department?->name ?? Auth::user()->role?->name }}</span>
        <a class="btn btn-light position-relative" href="{{ route('notifications.index') }}" aria-label="Notificaciones">
            Notificaciones
            @if (Auth::user()->unreadNotifications()->count() > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ Auth::user()->unreadNotifications()->count() }}</span>
            @endif
        </a>
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ Auth::user()->name }}
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Mi perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item" type="submit">Cerrar sesión</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
