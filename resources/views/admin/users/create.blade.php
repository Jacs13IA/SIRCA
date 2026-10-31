<x-app-layout>
    <div class="mb-4"><a class="small text-decoration-none" href="{{ route('admin.users.index') }}">&larr; Usuarios internos</a><h1 class="sirca-page-title mt-3">Agregar usuario</h1></div>
    <section class="card border-0 shadow-sm rounded-1"><div class="card-body p-4">
        <form method="POST" action="{{ route('admin.users.store') }}">@csrf
            @include('admin.users._form', ['user' => null])
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">Crear cuenta</button><a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">Cancelar</a></div>
        </form>
    </div></section>
</x-app-layout>