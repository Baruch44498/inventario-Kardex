@php
    $usuario = auth()->user();
    $rol = $usuario->role?->codigo;
    $esAdministrador = $rol === 'ADMINISTRADOR';
    $esAlmacen = $rol === 'ALMACEN';
    $esLogistica = $rol === 'COMERCIAL_LOGISTICA';
    $esPlanta = $rol === 'JEFE_PLANTA';
    $proformasEnAlmacen = $esAdministrador || $esAlmacen;

    $comercialActivo =
        request()->routeIs('clientes.*')
        || request()->routeIs('tipos-cliente.*')
        || request()->routeIs('cotizaciones-cliente.*')
        || request()->routeIs('ordenes-operacion.*')
        || (! $proformasEnAlmacen && request()->routeIs('proformas.*'));

    $comprasActivo =
        request()->routeIs('proveedores.*')
        || request()->routeIs('cotizaciones-proveedor.*')
        || request()->routeIs('historial-precios.*')
        || (($esAdministrador || $esLogistica) && request()->routeIs('ordenes-compra.*'))
        || (($esAdministrador || $esLogistica) && request()->routeIs('solicitudes-compra.*'))
        || ((! $esAlmacen) && request()->routeIs('requerimientos-compra.*'))
        || request()->is(
            'modulos/ordenes-compra',
            'modulos/facturas'
        );

    $almacenActivo =
        request()->routeIs('productos.*')
        || request()->routeIs('inventario.*')
        || request()->routeIs('repisas.*')
        || request()->routeIs('movimientos.*')
        || request()->routeIs('alertas.*')
        || request()->routeIs('notas-ingreso.*')
        || request()->routeIs('notas-salida.*')
        || ($esAlmacen && request()->routeIs('ordenes-compra.*'))
        || ($esAlmacen && request()->routeIs('requerimientos-compra.*'))
        || ($proformasEnAlmacen && request()->routeIs('proformas.*'));

    $produccionActivo = request()->is('modulos/produccion')
        || ($esPlanta && request()->routeIs('ordenes-operacion.*'));

    $contabilidadActivo = request()->is(
        'modulos/cuentas-cobrar',
        'modulos/cuentas-pagar'
    ) || ($rol === 'CONTABILIDAD' && request()->routeIs('solicitudes-compra.*', 'ordenes-compra.*'));

    $administracionActiva =
        request()->routeIs('usuarios.*')
        || request()->routeIs('kardex.*')
        || request()->is('modulos/auditoria');
@endphp

<div class="sidebar-overlay" data-sidebar-overlay></div>

<aside class="sidebar" data-sidebar id="sidebar-navigation">
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
            <small>{{ $usuario->role?->nombre ?? 'Gestión administrativa' }}</small>
        </span>
    </a>

    <nav class="sidebar-nav" aria-label="Navegación principal">
        <p class="sidebar-nav__heading">General</p>

        <a
            href="{{ route('dashboard') }}"
            class="sidebar-link {{ request()->routeIs('dashboard') ? 'sidebar-link--active' : '' }}"
            @if (request()->routeIs('dashboard')) aria-current="page" @endif
        >
            <span class="sidebar-link__icon"><x-ui.icon name="dashboard" :size="16" /></span>
            <span>Dashboard</span>
        </a>

        @if ($usuario->puedeAlguno(
            'clientes.gestionar',
            'proformas.cotizar',
            'ordenes.crear_comercial'
        ))
            <details class="sidebar-group" data-sidebar-group="comercial"
                data-active="{{ $comercialActivo ? 'true' : 'false' }}"
                @if ($comercialActivo || $esLogistica) open @endif>
                <summary class="sidebar-group__summary">
                    <span>Comercial y logística</span>
                    <span class="sidebar-group__chevron"><x-ui.icon name="chevron-down" :size="15" /></span>
                </summary>

                <div class="sidebar-group__content">
                    @if ($usuario->puede('clientes.gestionar'))
                        <a href="{{ route('clientes.index') }}"
                            class="sidebar-link {{ request()->routeIs('clientes.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="users" :size="16" /></span>
                            <span>Clientes</span>
                        </a>
                        <a href="{{ route('tipos-cliente.index') }}"
                            class="sidebar-link {{ request()->routeIs('tipos-cliente.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="tag" :size="16" /></span>
                            <span>Tipos de cliente</span>
                        </a>
                    @endif

                    @if ($usuario->puede('proformas.cotizar'))
                        <a href="{{ route('cotizaciones-cliente.index') }}"
                            class="sidebar-link {{ request()->routeIs('cotizaciones-cliente.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="quotes" :size="16" /></span>
                            <span>Cotizaciones al cliente</span>
                        </a>

                        @if (! $esAdministrador)
                            <a href="{{ route('proformas.index') }}"
                                class="sidebar-link {{ request()->routeIs('proformas.*') ? 'sidebar-link--active' : '' }}">
                                <span class="sidebar-link__icon"><x-ui.icon name="clipboard" :size="16" /></span>
                                <span>Proformas recibidas</span>
                            </a>
                        @endif
                    @endif

                    @if ($usuario->puede('ordenes.crear_comercial') || $esAdministrador)
                        <a href="{{ route('ordenes-operacion.index') }}"
                            class="sidebar-link {{ request()->routeIs('ordenes-operacion.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="orders" :size="16" /></span>
                            <span>Órdenes OM, OS y OP</span>
                        </a>
                    @endif
                </div>
            </details>
        @endif

        @if ($usuario->puedeAlguno('proveedores.gestionar', 'compras.gestionar', 'requerimientos.compra.gestionar'))
            <details class="sidebar-group" data-sidebar-group="compras"
                data-active="{{ $comprasActivo ? 'true' : 'false' }}"
                @if ($comprasActivo || $esLogistica) open @endif>
                <summary class="sidebar-group__summary">
                    <span>Compras y proveedores</span>
                    <span class="sidebar-group__chevron"><x-ui.icon name="chevron-down" :size="15" /></span>
                </summary>

                <div class="sidebar-group__content">
                    @if ($usuario->puede('proveedores.gestionar'))
                        <a href="{{ route('proveedores.index') }}"
                            class="sidebar-link {{ request()->routeIs('proveedores.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="suppliers" :size="16" /></span>
                            <span>Proveedores</span>
                        </a>
                    @endif

                    @if ($usuario->puede('requerimientos.compra.gestionar'))
                        <a href="{{ route('requerimientos-compra.index') }}"
                            class="sidebar-link {{ request()->routeIs('requerimientos-compra.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="requisitions" :size="16" /></span>
                            <span>Requerimientos de compra</span>
                        </a>
                    @endif

                    @if ($usuario->puede('compras.gestionar'))
                        <a href="{{ route('cotizaciones-proveedor.index') }}"
                            class="sidebar-link {{ request()->routeIs('cotizaciones-proveedor.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="quotes" :size="16" /></span>
                            <span>Cotizaciones de proveedores</span>
                        </a>
                        <a href="{{ route('historial-precios.index') }}"
                            class="sidebar-link {{ request()->routeIs('historial-precios.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="banknote" :size="16" /></span>
                            <span>Historial de precios</span>
                        </a>
                        <a href="{{ route('solicitudes-compra.index') }}"
                            class="sidebar-link {{ request()->routeIs('solicitudes-compra.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="clipboard" :size="16" /></span>
                            <span>Compras aprobadas</span>
                        </a>

                        <a href="{{ route('ordenes-compra.index') }}"
                            class="sidebar-link {{ request()->routeIs('ordenes-compra.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="purchase-order" :size="16" /></span>
                            <span>Órdenes de compra</span>
                        </a>

                        @foreach (['facturas' => ['invoice', 'Facturas de proveedor']] as $slug => [$icono, $nombre])
                            <a href="{{ route('modulos.show', $slug) }}"
                                class="sidebar-link {{ request()->is("modulos/{$slug}") ? 'sidebar-link--active' : '' }}">
                                <span class="sidebar-link__icon"><x-ui.icon :name="$icono" :size="16" /></span>
                                <span>{{ $nombre }}</span>
                            </a>
                        @endforeach
                    @endif
                </div>
            </details>
        @endif

        @if ($usuario->puedeAlguno(
            'productos.ver',
            'inventario.ver',
            'repisas.ver',
            'movimientos.ver',
            'ingresos.ver',
            'salidas.listar',
            'alertas.ver',
            'requerimientos.compra.crear',
            'proformas.crear'
        ))
            <details class="sidebar-group" data-sidebar-group="almacen"
                data-active="{{ $almacenActivo ? 'true' : 'false' }}"
                @if ($almacenActivo || $esAlmacen) open @endif>
                <summary class="sidebar-group__summary">
                    <span>Almacén</span>
                    <span class="sidebar-group__chevron"><x-ui.icon name="chevron-down" :size="15" /></span>
                </summary>

                <div class="sidebar-group__content">
                    @foreach ([
                        ['productos.ver', 'productos.index', 'productos.*', 'products', 'Productos'],
                        ['inventario.ver', 'inventario.index', 'inventario.*', 'inventory', 'Inventario'],
                        ['repisas.ver', 'repisas.index', 'repisas.*', 'shelf', 'Repisas'],
                        ['movimientos.ver', 'movimientos.index', 'movimientos.*', 'movements', 'Movimientos'],
                        ['ingresos.ver', 'notas-ingreso.index', 'notas-ingreso.*', 'entry', 'Notas de ingreso'],
                        ['salidas.listar', 'notas-salida.index', 'notas-salida.*', 'exit', 'Notas de salida'],
                        ['alertas.ver', 'alertas.index', 'alertas.*', 'alerts', 'Alertas de stock'],
                    ] as [$permiso, $ruta, $patron, $icono, $nombre])
                        @if ($usuario->puede($permiso))
                            <a href="{{ route($ruta) }}"
                                class="sidebar-link {{ request()->routeIs($patron) ? 'sidebar-link--active' : '' }}">
                                <span class="sidebar-link__icon"><x-ui.icon :name="$icono" :size="16" /></span>
                                <span>{{ $nombre }}</span>
                            </a>
                        @endif
                    @endforeach

                    @if ($esAlmacen && $usuario->puede('ingresos.ver'))
                        <a href="{{ route('ordenes-compra.index') }}"
                            class="sidebar-link {{ request()->routeIs('ordenes-compra.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="purchase-order" :size="16" /></span>
                            <span>Órdenes por recibir</span>
                        </a>
                    @endif

                    @if ($esAlmacen && $usuario->puede('requerimientos.compra.crear'))
                        <a href="{{ route('requerimientos-compra.index') }}"
                            class="sidebar-link {{ request()->routeIs('requerimientos-compra.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="requisitions" :size="16" /></span>
                            <span>Requerimientos de compra</span>
                        </a>
                    @endif

                    @if ($proformasEnAlmacen && $usuario->puede('proformas.crear'))
                        <a href="{{ route('proformas.index') }}"
                            class="sidebar-link {{ request()->routeIs('proformas.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="quotes" :size="16" /></span>
                            <span>Proformas de venta directa</span>
                        </a>
                    @endif

                    @if ($esAlmacen)
                        <a href="{{ route('ordenes-operacion.index') }}"
                            class="sidebar-link {{ request()->routeIs('ordenes-operacion.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="orders" :size="16" /></span>
                            <span>Órdenes por atender</span>
                        </a>
                    @endif
                </div>
            </details>
        @endif

        @if ($usuario->puede('produccion.ver'))
            <details class="sidebar-group" data-sidebar-group="produccion"
                data-active="{{ $produccionActivo ? 'true' : 'false' }}"
                @if ($produccionActivo || $esPlanta) open @endif>
                <summary class="sidebar-group__summary">
                    <span>Control de planta</span>
                    <span class="sidebar-group__chevron"><x-ui.icon name="chevron-down" :size="15" /></span>
                </summary>

                <div class="sidebar-group__content">
                    @if ($esPlanta)
                        <a href="{{ route('ordenes-operacion.index') }}"
                            class="sidebar-link {{ request()->routeIs('ordenes-operacion.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="orders" :size="16" /></span>
                            <span>Órdenes activas</span>
                        </a>
                    @endif
                    <a href="{{ route('modulos.show', 'produccion') }}"
                        class="sidebar-link {{ request()->is('modulos/produccion') ? 'sidebar-link--active' : '' }}">
                        <span class="sidebar-link__icon"><x-ui.icon name="activity" :size="16" /></span>
                        <span>Avance de producción</span>
                    </a>
                </div>
            </details>
        @endif

        @if ($usuario->puede('contabilidad.ver'))
            <details class="sidebar-group" data-sidebar-group="contabilidad"
                data-active="{{ $contabilidadActivo ? 'true' : 'false' }}"
                @if ($contabilidadActivo || $rol === 'CONTABILIDAD') open @endif>
                <summary class="sidebar-group__summary">
                    <span>Contabilidad</span>
                    <span class="sidebar-group__chevron"><x-ui.icon name="chevron-down" :size="15" /></span>
                </summary>
                <div class="sidebar-group__content">
                    <a href="{{ route('solicitudes-compra.index') }}"
                        class="sidebar-link {{ request()->routeIs('solicitudes-compra.*') ? 'sidebar-link--active' : '' }}">
                        <span class="sidebar-link__icon"><x-ui.icon name="clipboard" :size="16" /></span>
                        <span>Cotizaciones por pagar</span>
                    </a>
                    @if ($rol === 'CONTABILIDAD')
                        <a href="{{ route('ordenes-compra.index') }}"
                            class="sidebar-link {{ request()->routeIs('ordenes-compra.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="purchase-order" :size="16" /></span>
                            <span>Órdenes de compra</span>
                        </a>
                    @endif
                    <a href="{{ route('modulos.show', 'cuentas-cobrar') }}"
                        class="sidebar-link {{ request()->is('modulos/cuentas-cobrar') ? 'sidebar-link--active' : '' }}">
                        <span class="sidebar-link__icon"><x-ui.icon name="invoice" :size="16" /></span>
                        <span>Cuentas por cobrar</span>
                    </a>
                    <a href="{{ route('modulos.show', 'cuentas-pagar') }}"
                        class="sidebar-link {{ request()->is('modulos/cuentas-pagar') ? 'sidebar-link--active' : '' }}">
                        <span class="sidebar-link__icon"><x-ui.icon name="coins" :size="16" /></span>
                        <span>Cuentas por pagar</span>
                    </a>
                </div>
            </details>
        @endif

        @if ($usuario->puedeAlguno('usuarios.gestionar', 'kardex.ver', 'auditoria.ver'))
            <details class="sidebar-group" data-sidebar-group="administracion"
                data-active="{{ $administracionActiva ? 'true' : 'false' }}"
                @if ($administracionActiva) open @endif>
                <summary class="sidebar-group__summary">
                    <span>Administración del sistema</span>
                    <span class="sidebar-group__chevron"><x-ui.icon name="chevron-down" :size="15" /></span>
                </summary>
                <div class="sidebar-group__content">
                    @if ($usuario->puede('usuarios.gestionar'))
                        <a href="{{ route('usuarios.index') }}"
                            class="sidebar-link {{ request()->routeIs('usuarios.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="users" :size="16" /></span>
                            <span>Usuarios y permisos</span>
                        </a>
                    @endif
                    @if ($usuario->puede('kardex.ver'))
                        <a href="{{ route('kardex.index') }}"
                            class="sidebar-link {{ request()->routeIs('kardex.*') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="coins" :size="16" /></span>
                            <span>Kardex valorizado</span>
                        </a>
                    @endif
                    @if ($usuario->puede('auditoria.ver'))
                        <a href="{{ route('modulos.show', 'auditoria') }}"
                            class="sidebar-link {{ request()->is('modulos/auditoria') ? 'sidebar-link--active' : '' }}">
                            <span class="sidebar-link__icon"><x-ui.icon name="clipboard" :size="16" /></span>
                            <span>Auditoría</span>
                        </a>
                    @endif
                </div>
            </details>
        @endif
    </nav>

    <div class="sidebar-footer">
        <span class="status-dot"></span>
        Perfil: {{ $usuario->role?->nombre ?? 'Sin rol' }}
    </div>
</aside>
