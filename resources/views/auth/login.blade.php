<x-guest-layout>
    <div class="mb-4"><h1 class="h4 mb-1">Acceso administrativo</h1><p class="text-body-secondary mb-0">Ingresa con tu cuenta institucional.</p></div>
    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-3">
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mb-3">
            <x-input-label for="password" value="Contraseña" />

            <x-text-input id="password"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="form-check mb-3">
            <label for="remember_me" class="form-check-label">
                <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                Mantener sesión iniciada
            </label>
        </div>

        <div class="d-flex align-items-center justify-content-between gap-3">
            @if (Route::has('password.request'))
                <a class="small" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif

            <x-primary-button>
                Iniciar sesión
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
