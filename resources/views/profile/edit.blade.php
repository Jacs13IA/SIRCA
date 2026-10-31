<x-app-layout>
    <div class="mb-4"><p class="text-uppercase small fw-semibold text-secondary mb-1">Cuenta</p><h1 class="sirca-page-title mb-0">Mi perfil</h1></div>
    <div class="row g-4">
            <div class="col-12 col-xl-8"><div class="card border-0 shadow-sm rounded-1"><div class="card-body p-4">
                    @include('profile.partials.update-profile-information-form')
                </div></div></div>
            <div class="col-12 col-xl-8"><div class="card border-0 shadow-sm rounded-1"><div class="card-body p-4">
                    @include('profile.partials.update-password-form')
                </div></div></div>
    </div>
</x-app-layout>
