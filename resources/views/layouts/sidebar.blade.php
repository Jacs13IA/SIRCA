<aside class="sirca-sidebar p-3">
    <div class="small text-uppercase text-white-50 mb-2 px-2">Operación</div>
    <nav class="nav nav-pills flex-md-column gap-1" aria-label="Navegación principal">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <span class="me-2" aria-hidden="true">▦</span>Resumen
        </a>
        <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
            <span class="me-2" aria-hidden="true">▤</span>Reportes
        </a>
        @if (Auth::user()->role?->slug === 'administrator')
            <div class="small text-uppercase text-white-50 mt-4 mb-2 px-2">Administración</div>
            @if (Route::has('admin.users.index'))
                <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Usuarios</a>
            @endif
            @if (Route::has('admin.departments.index'))
                <a class="nav-link {{ request()->routeIs('admin.departments.*') ? 'active' : '' }}" href="{{ route('admin.departments.index') }}">Direcciones</a>
            @endif
            @if (Route::has('admin.report-types.index'))
                <a class="nav-link {{ request()->routeIs('admin.report-types.*') ? 'active' : '' }}" href="{{ route('admin.report-types.index') }}">Tipos de reporte</a>
            @endif
            @if (Route::has('admin.audit.index'))
                <a class="nav-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}" href="{{ route('admin.audit.index') }}">Auditoría</a>
            @endif
        @endif
    </nav>
    <div class="sirca-sidebar-footer mt-auto pt-4 small text-white-50">
        @if (file_exists(public_path('images/branding/escudo.png')))
            <img class="sirca-sidebar__crest" src="{{ asset('images/branding/escudo.png') }}" alt="Escudo del Ayuntamiento de Champotón">
        @endif
        <span>Sistema municipal de reportes ciudadanos</span>
    </div>
</aside>