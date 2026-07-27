<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Página no encontrada | Hidroil</title>
    <link rel="stylesheet" href="{{ asset('css/hidroil-admin.css') }}">
</head>
<body class="error-page">
    <main class="error-card">
        <div class="error-card__code">404</div>
        <h1>Página no encontrada</h1>
        <p>
            La dirección solicitada no existe o el módulo todavía no está
            disponible.
        </p>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
           class="button button--primary">
            Volver
        </a>
    </main>
</body>
</html>
