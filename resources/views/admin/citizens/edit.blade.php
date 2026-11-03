<x-app-layout>
    <div class="mb-4"><a class="small text-decoration-none" href="{{ route('admin.citizens.show', $citizen) }}">&larr; Detalle del ciudadano</a><h1 class="sirca-page-title mt-3">Editar ciudadano</h1></div>
    <section class="card border-0 shadow-sm rounded-1"><div class="card-body p-4">
        <form method="POST" action="{{ route('admin.citizens.update', $citizen) }}">@csrf @method('PUT')
            @include('admin.citizens._form')
            <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">Guardar cambios</button><a class="btn btn-outline-secondary" href="{{ route('admin.citizens.show', $citizen) }}">Cancelar</a></div>
        </form>
    </div></section>
</x-app-layout>