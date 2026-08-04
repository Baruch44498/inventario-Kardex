<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Panel administrativo') | Hidroil</title>

    <link rel="stylesheet" href="{{ asset('css/hidroil-admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/hidroil-design-system.css') }}">
    @stack('head')
</head>
<body class="app-page">
    <div class="page-progress" data-page-progress aria-hidden="true">
        <span></span>
    </div>

    <div class="app-shell">
        @include('layouts.partials.sidebar')

        <div class="app-main">
            @include('layouts.partials.navbar')

            <main class="content" id="main-content">
                @include('layouts.partials.flash')
                @yield('content')
            </main>
        </div>
    </div>

    <x-ui.confirmation-modal
        name="confirm"
        title="Confirmar acción"
        message="Revisa la información antes de continuar."
        confirm-label="Confirmar"
    />

    <x-ui.confirmation-modal
        name="discard"
        title="¿Descartar cambios?"
        message="Vas a perder la información que ingresaste en este formulario."
        tone="danger"
        confirm-label="Descartar cambios"
        cancel-label="Seguir editando"
    />

    <script src="{{ asset('js/remote-combobox.js') }}" defer></script>
    <script src="{{ asset('js/hidroil-ui.js') }}" defer></script>
    @stack('scripts')
</body>
</html>

