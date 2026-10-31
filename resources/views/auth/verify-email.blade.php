<x-guest-layout>
    <div class="mb-4 text-body-secondary">
        Confirma tu correo electrónico con el enlace que enviamos a tu cuenta institucional. Si no lo recibiste, puedes solicitar otro.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success">
            Enviamos un nuevo enlace de verificación a tu correo.
        </div>
    @endif

    <div class="mt-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    Reenviar verificación
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="btn btn-link">
                Cerrar sesión
            </button>
        </form>
    </div>
</x-guest-layout>
