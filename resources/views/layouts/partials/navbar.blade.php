@php
    $usuario = auth()->user();
    $nombreUsuario = $usuario->username ?: ($usuario->name ?: $usuario->email);
    $rolUsuario = $usuario->role?->nombre ?? 'Sin rol asignado';
    $inicialUsuario = strtoupper(substr($nombreUsuario, 0, 1));
@endphp

<header class="topbar">
    <button
        type="button"
        class="topbar-menu-button"
        data-sidebar-toggle
        aria-label="Abrir menú"
    >
        <x-ui.icon name="menu" :size="21" />
    </button>

    <div class="topbar-title">
        <span>@yield('page-kicker', 'Panel administrativo')</span>
        <strong>@yield('page-title', 'Dashboard')</strong>
    </div>

    <div class="user-menu">
        <div class="user-avatar" aria-hidden="true">
            {{ $inicialUsuario }}
        </div>

        <div class="user-menu__data">
            <strong>{{ $nombreUsuario }}</strong>
            <span>{{ $rolUsuario }}</span>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="button button--ghost button--small">
                Cerrar sesión
            </button>
        </form>
    </div>
</header>
