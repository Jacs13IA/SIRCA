<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#3f0b20">
        <title>{{ config('app.name', 'SIRCA') }} | Atención ciudadana</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="citizen-chat-page">
        <div
            class="citizen-chat"
            data-citizen-chat
            data-message-url="{{ route('chat.messages.store') }}"
            data-evidence-url="{{ route('evidence-staging.store') }}"
            data-evidence-requested="{{ session('chat.foundry.evidence_requested', false) ? 'true' : 'false' }}"
        >
            <header class="citizen-chat__header">
                <div class="container citizen-chat__header-inner">
                    <x-sirca-brand :href="route('chat.home')" appearance="dark" />
                    <a class="btn btn-light citizen-chat__login" href="{{ route('login') }}">Iniciar sesión</a>
                </div>
            </header>

            <main class="container citizen-chat__main">
                <div class="citizen-chat__intro">
                    <p class="citizen-chat__eyebrow">ATENCIÓN CIUDADANA · CHAMPOTÓN</p>
                    <h1>¿Qué sucede en tu colonia?</h1>
                    <p>Cuéntanos sobre la situación para orientarte.</p>
                </div>

                <section class="citizen-chat__panel" aria-label="Conversación con atención ciudadana">
                    <div class="citizen-chat__messages" aria-live="polite" aria-relevant="additions">
                        <article class="citizen-chat__message">
                            <span class="citizen-chat__avatar" aria-hidden="true">S</span>
                            <div>
                                <p class="citizen-chat__sender">SIRCA</p>
                                <div class="citizen-chat__bubble">
                                    <p>Hola. Describe la incidencia y el lugar donde ocurre. También puedes agregar una fotografía o compartir tu ubicación.</p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <form class="citizen-chat__composer" data-chat-form>
                        <label class="visually-hidden" for="citizen-message">Describe la incidencia</label>
                        <textarea
                            class="form-control citizen-chat__textarea"
                            id="citizen-message"
                            name="message"
                            rows="2"
                            maxlength="2000"
                            placeholder="Escribe tu mensaje..."
                            data-chat-message
                        ></textarea>

                        <div class="citizen-chat__composer-actions">
                            <div class="citizen-chat__tools">
                                <input
                                    class="visually-hidden"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                                    disabled
                                    data-evidence-input
                                    aria-label="Seleccionar evidencia"
                                >
                                <button class="btn btn-outline-secondary" type="button" data-evidence-trigger disabled aria-describedby="evidence-hint">
                                    Adjuntar evidencia
                                </button>
                                <button class="btn btn-outline-secondary" type="button" data-location-trigger>
                                    Compartir ubicación
                                </button>
                            </div>
                            <button class="btn btn-primary citizen-chat__send" type="submit" data-chat-send disabled>
                                Enviar
                            </button>
                        </div>

                        <div class="citizen-chat__attachments" data-evidence-preview hidden>
                            <img class="citizen-chat__preview-image" alt="Vista previa de la evidencia" data-evidence-image hidden>
                            <div class="citizen-chat__file-details">
                                <span class="citizen-chat__file-name" data-evidence-name></span>
                                <span class="citizen-chat__file-size" data-evidence-size></span>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-evidence-remove aria-label="Quitar evidencia seleccionada">
                                Quitar
                            </button>
                        </div>

                        <div class="citizen-chat__feedback" aria-live="polite">
                            <p class="citizen-chat__status" id="evidence-hint" data-evidence-status>El agente solicitará evidencia si la necesita.</p>
                            <p class="citizen-chat__status" data-location-status>Ubicación no compartida.</p>
                            <p class="citizen-chat__status citizen-chat__status--notice" data-chat-status hidden></p>
                        </div>
                    </form>
                </section>
                <p class="citizen-chat__footnote">Sistema Inteligente de Reportes Ciudadanos · Ayuntamiento de Champotón</p>
            </main>
        </div>
    </body>
</html>