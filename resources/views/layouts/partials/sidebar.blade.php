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
        || request()->routeIs('facturas-proveedor.*')
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
        || request()->routeIs('inventarios-periodicos.*')
        || request()->routeIs('repisas.*')
        || request()->routeIs('movimientos.*')
        || request()->routeIs('alertas.*')
        || request()->routeIs('notas-ingreso.*')
        || ($esAlmacen && request()->routeIs('facturas-proveedor.*'))
        || request()->routeIs('notas-salida.*')
        || ($esAlmacen && request()->routeIs('ordenes-compra.*'))
        || ($esAlmacen && request()->routeIs('requerimientos-compra.*'))
        || ($proformasEnAlmacen && request()->routeIs('proformas.*'));

    $produccionActivo = ($esPlanta || $esAdministrador)
        && request()->routeIs('ordenes-operacion.*');

    $contabilidadActivo = request()->is(
        'modulos/cuentas-cobrar',
        'modulos/cuentas-pagar'
    ) || ($rol === 'CONTABILIDAD' && request()->routeIs('solicitudes-compra.*', 'ordenes-compra.*', 'facturas-proveedor.*'));

    $administracionActiva =
        request()->routeIs('usuarios.*')
        || request()->routeIs('empleados.*')
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
        @include('layouts.partials.sidebar._general')
        @include('layouts.partials.sidebar._comercial')
        @include('layouts.partials.sidebar._compras')
        @include('layouts.partials.sidebar._almacen')
        @include('layouts.partials.sidebar._produccion')
        @include('layouts.partials.sidebar._contabilidad')
        @include('layouts.partials.sidebar._administracion')
    </nav>

    <div class="sidebar-footer">
        <span class="status-dot"></span>
        Perfil: {{ $usuario->role?->nombre ?? 'Sin rol' }}
    </div>
</aside>
