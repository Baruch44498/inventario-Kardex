<header class="topbar">
    <button
        type="button"
        class="topbar-menu-button"
        data-sidebar-toggle
        aria-label="Abrir menú"
    >
        ☰
    </button>

    <div class="topbar-title">
        <span>@yield('page-kicker', 'Panel administrativo')</span>
        <strong>@yield('page-title', 'Dashboard')</strong>
    </div>

    <div class="user-menu">
        <div class="user-avatar">
            {{ strtoupper(substr(auth()->user()->nombreVisible(), 0, 1)) }}
        </div>

        <div class="user-menu__data">
            <strong>{{ auth()->user()->nombreVisible() }}</strong>
            <span>{{ auth()->user()->role?->nombre ?? 'Sin rol asignado' }}</span>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="button button--ghost button--small">
                Cerrar sesión
            </button>
        </form>
    </div>
</header>
