<x-guest-layout>
    <div class="mb-4"><h1 class="h4">Recuperar acceso</h1><p class="text-body-secondary mb-0">Indica tu correo institucional para recibir instrucciones.</p></div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-3">
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="d-flex justify-content-end">
            <x-primary-button>
                Enviar instrucciones
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
