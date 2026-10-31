<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="auth-backdrop min-vh-100 d-flex align-items-center justify-content-center py-5 px-3">
            <section class="card auth-panel">
                <header class="card-header border-0 text-center pt-4">
                    <x-sirca-brand :href="route('login')" appearance="color" class="justify-content-center" />
                </header>
                <div class="card-body p-4 p-md-5">{{ $slot }}</div>
            </section>
        </main>
    </body>
</html>
