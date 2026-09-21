        <p class="sidebar-nav__heading">General</p>

        <a
            href="{{ route('dashboard') }}"
            class="sidebar-link {{ request()->routeIs('dashboard') ? 'sidebar-link--active' : '' }}"
            @if (request()->routeIs('dashboard')) aria-current="page" @endif
        >
            <span class="sidebar-link__icon"><x-ui.icon name="dashboard" :size="16" /></span>
            <span>Dashboard</span>
        </a>
