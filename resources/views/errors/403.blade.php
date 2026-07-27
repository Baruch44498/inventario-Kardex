<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso denegado | Hidroil</title>
    <link rel="stylesheet" href="{{ asset('css/hidroil-admin.css') }}">
</head>
<body class="error-page">
    <main class="error-card">
        <div class="error-card__code">403</div>
        <h1>Acceso denegado</h1>
        <p>
            No tienes permisos para ingresar a este módulo.
            Contacta al administrador si consideras que se trata de un error.
        </p>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
           class="button button--primary">
            Volver
        </a>
    </main>
</body>
</html>
