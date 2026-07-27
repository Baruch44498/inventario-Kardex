@php
    $rol = auth()->user()->role?->codigo;

    $puedeAlmacen = in_array(
        $rol,
        ['ADMINISTRADOR', 'ALMACEN'],
        true
    );

    $puedeCompras = in_array(
        $rol,
        ['ADMINISTRADOR', 'JEFE_COMPRAS'],
        true
    );

    $almacenActivo =
        request()->routeIs('productos.*')
        || request()->routeIs('inventario.*')
        || request()->routeIs('repisas.*')
        || request()->routeIs('movimientos.*')
        || request()->routeIs('alertas.*')
        || request()->routeIs('notas-ingreso.*')
        || request()->routeIs('notas-salida.*')
        || request()->routeIs('ordenes-operacion.*')
        || request()->is(
            'modulos/entradas',
            'modulos/salidas'
        );

    $comprasActivo = request()->is(
        'modulos/proveedores',
        'modulos/requisiciones',
        'modulos/cotizaciones',
        'modulos/solicitudes-compra',
        'modulos/ordenes-compra',
        'modulos/facturas'
    );
@endphp

<div class="sidebar-overlay" data-sidebar-overlay></div>

<aside class="sidebar" data-sidebar>
    <a href="{{ route('dashboard') }}" class="sidebar-brand">
        <span class="sidebar-brand__logo-wrap">
            <img
                src="{{ asset('images/logo-hidroil.png') }}"
                alt="Hidroil S.A.C."
                class="sidebar-brand__logo"
            >
        </span>

        <span class="sidebar-brand__copy">
            <strong>HIDROIL</strong>
            <small>Gestión administrativa</small>
        </span>
    </a>

    <nav class="sidebar-nav" aria-label="Navegación principal">
        <p class="sidebar-nav__heading">General</p>

        <a
            href="{{ route('dashboard') }}"
            class="sidebar-link {{ request()->routeIs('dashboard') ? 'sidebar-link--active' : '' }}"
        >
            <span class="sidebar-link__icon">
                <x-ui.icon name="dashboard" :size="16" />
            </span>
            <span>Dashboard</span>
        </a>

        @if ($puedeAlmacen)
            <details
                class="sidebar-group"
                data-sidebar-group="almacen"
                data-active="{{ $almacenActivo ? 'true' : 'false' }}"
                @if ($almacenActivo || $rol === 'ALMACEN') open @endif
            >
                <summary class="sidebar-group__summary">
                    <span>Almacén</span>
                    <span class="sidebar-group__chevron">
                        <x-ui.icon name="chevron-down" :size="15" />
                    </span>
                </summary>

                <div class="sidebar-group__content">
                    <a
                        href="{{ route('productos.index') }}"
                        class="sidebar-link {{ request()->routeIs('productos.*') ? 'sidebar-link--active' : '' }}"
                    >
                        <span class="sidebar-link__icon">
                            <x-ui.icon name="products" :size="16" />
                        </span>
                        <span>Productos</span>
                    </a>

                    <a
                        href="{{ route('inventario.index') }}"
                        class="sidebar-link {{ request()->routeIs('inventario.*') ? 'sidebar-link--active' : '' }}"
                    >
                        <span class="sidebar-link__icon">
                            <x-ui.icon name="inventory" :size="16" />
                        </span>
                        <span>Inventario</span>
                    </a>

                    <a
                        href="{{ route('repisas.index') }}"
                        class="sidebar-link {{ request()->routeIs('repisas.*') ? 'sidebar-link--active' : '' }}"
                    >
                        <span class="sidebar-link__icon">
                            <x-ui.icon name="shelf" :size="16" />
                        </span>
                        <span>Repisas</span>
                    </a>

                    <a
                        href="{{ route('movimientos.index') }}"
                        class="sidebar-link {{ request()->routeIs('movimientos.*') ? 'sidebar-link--active' : '' }}"
                    >
                        <span class="sidebar-link__icon">
                            <x-ui.icon name="movements" :size="16" />
                        </span>
                        <span>Movimientos</span>
                    </a>

                    <a
                        href="{{ route('notas-ingreso.index') }}"
                        class="sidebar-link {{ request()->routeIs('notas-ingreso.*') ? 'sidebar-link--active' : '' }}"
                    >
                        <span class="sidebar-link__icon">
                            <x-ui.icon name="entry" :size="16" />
                        </span>
                        <span>Notas de ingreso</span>
                    </a>

                    <a
                        href="{{ route('notas-salida.index') }}"
                        class="sidebar-link {{ request()->routeIs('notas-salida.*') ? 'sidebar-link--active' : '' }}"
                    >
                        <span class="sidebar-link__icon">
                            <x-ui.icon name="exit" :size="16" />
                        </span>
                        <span>Notas de salida</span>
                    </a>

                    <a
                        href="{{ route('alertas.index') }}"
                        class="sidebar-link {{ request()->routeIs('alertas.*') ? 'sidebar-link--active' : '' }}"
                    >
                        <span class="sidebar-link__icon">
                            <x-ui.icon name="alerts" :size="16" />
                        </span>
                        <span>Alertas de stock</span>
                    </a>

                    <a
                        href="{{ route('ordenes-operacion.index') }}"
                        class="sidebar-link {{ request()->routeIs('ordenes-operacion.*') ? 'sidebar-link--active' : '' }}"
                    >
                        <span class="sidebar-link__icon">
                            <x-ui.icon name="orders" :size="16" />
                        </span>
                        <span>Órdenes de operación</span>
                    </a>
                </div>
            </details>
        @endif

        @if ($puedeCompras)
            <details
                class="sidebar-group"
                data-sidebar-group="compras"
                data-active="{{ $comprasActivo ? 'true' : 'false' }}"
                @if ($comprasActivo || $rol === 'JEFE_COMPRAS') open @endif
            >
                <summary class="sidebar-group__summary">
                    <span>Compras</span>
                    <span class="sidebar-group__chevron">
                        <x-ui.icon name="chevron-down" :size="15" />
                    </span>
                </summary>

                <div class="sidebar-group__content">
                    @foreach ([
                        'proveedores' => ['suppliers', 'Proveedores'],
                        'requisiciones' => ['requisitions', 'Requisiciones'],
                        'cotizaciones' => ['quotes', 'Cotizaciones'],
                        'solicitudes-compra' => ['purchase-request', 'Solicitudes de compra'],
                        'ordenes-compra' => ['purchase-order', 'Órdenes de compra'],
                        'facturas' => ['invoice', 'Facturas'],
                    ] as $slug => [$icono, $nombre])
                        <a
                            href="{{ route('modulos.show', $slug) }}"
                            class="sidebar-link {{ request()->is("modulos/{$slug}") ? 'sidebar-link--active' : '' }}"
                        >
                            <span class="sidebar-link__icon">
                                <x-ui.icon :name="$icono" :size="16" />
                            </span>
                            <span>{{ $nombre }}</span>
                        </a>
                    @endforeach
                </div>
            </details>
        @endif

        @if ($rol === 'ADMINISTRADOR')
            <p class="sidebar-nav__heading">Administración</p>

            <a
                href="{{ route('modulos.show', 'usuarios') }}"
                class="sidebar-link {{ request()->is('modulos/usuarios') ? 'sidebar-link--active' : '' }}"
            >
                <span class="sidebar-link__icon">
                    <x-ui.icon name="users" :size="16" />
                </span>
                <span>Usuarios y roles</span>
            </a>
        @endif
    </nav>

    <div class="sidebar-footer">
        <span class="status-dot"></span>
        Sistema operativo
    </div>
</aside>
