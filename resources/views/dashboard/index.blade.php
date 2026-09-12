@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-kicker', 'Panel administrativo')
@section('page-title', 'Dashboard')

@section('content')
    <section class="welcome-panel role-welcome-panel">
        <div>
            <p class="eyebrow">{{ $perfil['perfil'] }}</p>
            <h1>Bienvenido, {{ auth()->user()->nombreVisible() }}</h1>
            <p>{{ $perfil['descripcion'] }}</p>
        </div>

        <div class="role-welcome-panel__meta">
            <span class="role-profile-chip">
                {{ $perfil['nombre'] }}
            </span>
            <div class="welcome-panel__date">
                <span>Fecha</span>
                <strong>{{ now()->translatedFormat('d M Y') }}</strong>
            </div>
        </div>
    </section>

    @if ($modo === 'administrador')
        <section class="metric-grid" aria-label="Resumen general del sistema">
            @foreach ([
                ['Usuarios activos', 'users', 'info', $resumen['usuarios_activos'], 'Accesos habilitados'],
                ['Clientes activos', 'users', 'success', $resumen['clientes_activos'], 'Catálogo comercial'],
                ['Proveedores activos', 'suppliers', 'success', $resumen['proveedores_activos'], 'Catálogo de compras'],
                ['Productos activos', 'products', 'info', $resumen['productos_activos'], 'Catálogo de Almacén'],
                ['Cotizaciones abiertas', 'quotes', 'warning', $resumen['cotizaciones_abiertas'], 'Pendientes de cierre'],
                ['Facturas pendientes', 'invoice', 'warning', $resumen['facturas_pendientes'], 'Pendientes de pago'],
            ] as [$titulo, $icono, $tono, $valor, $detalle])
                <article class="metric-card metric-card--{{ $tono }}">
                    <div class="metric-card__top">
                        <span class="metric-card__label">{{ $titulo }}</span>
                        <span class="metric-card__icon"><x-ui.icon :name="$icono" :size="22" /></span>
                    </div>
                    <strong>{{ number_format($valor) }}</strong>
                    <small>{{ $detalle }}</small>
                </article>
            @endforeach
        </section>

        <section class="dashboard-grid admin-area-grid" aria-label="Módulos organizados por área">
            <article class="panel admin-area-card">
                <header class="panel__header">
                    <div><p class="eyebrow">Área comercial</p><h2>Ventas</h2></div>
                </header>
                <div class="admin-area-links">
                    <a href="{{ route('clientes.index') }}" class="role-quick-card"><span><x-ui.icon name="users" :size="22" /></span><div><strong>Clientes</strong><small>Datos, tipos, direcciones y vehículos.</small></div></a>
                    <a href="{{ route('cotizaciones-cliente.index') }}" class="role-quick-card"><span><x-ui.icon name="quotes" :size="22" /></span><div><strong>Cotizaciones al cliente</strong><small>Propuestas comerciales y seguimiento.</small></div></a>
                    <a href="{{ route('ordenes-operacion.index') }}" class="role-quick-card"><span><x-ui.icon name="orders" :size="22" /></span><div><strong>Órdenes de Venta</strong><small>Ventas aprobadas listas para despacho.</small></div></a>
                </div>
            </article>

            <article class="panel admin-area-card">
                <header class="panel__header">
                    <div><p class="eyebrow">Área de abastecimiento</p><h2>Compras y proveedores</h2></div>
                </header>
                <div class="admin-area-links">
                    <a href="{{ route('proveedores.index') }}" class="role-quick-card"><span><x-ui.icon name="suppliers" :size="22" /></span><div><strong>Proveedores</strong><small>Catálogo y datos comerciales.</small></div></a>
                    <a href="{{ route('requerimientos-compra.index') }}" class="role-quick-card"><span><x-ui.icon name="requisitions" :size="22" /></span><div><strong>Requerimientos de compra</strong><small>Necesidades enviadas por Almacén.</small></div></a>
                    <a href="{{ route('cotizaciones-proveedor.index') }}" class="role-quick-card"><span><x-ui.icon name="quotes" :size="22" /></span><div><strong>Cotizaciones de proveedores</strong><small>Precios, IGV, moneda y descuentos.</small></div></a>
                    <a href="{{ route('historial-precios.index') }}" class="role-quick-card"><span><x-ui.icon name="banknote" :size="22" /></span><div><strong>Historial de precios</strong><small>Comparación por producto y proveedor.</small></div></a>
                    <a href="{{ route('ordenes-compra.index') }}" class="role-quick-card"><span><x-ui.icon name="purchase-order" :size="22" /></span><div><strong>Órdenes de compra</strong><small>Compras autorizadas y seguimiento.</small></div></a>
                </div>
            </article>

            <article class="panel admin-area-card">
                <header class="panel__header">
                    <div><p class="eyebrow">Área operativa</p><h2>Almacén</h2></div>
                </header>
                <div class="admin-area-links">
                    <a href="{{ route('productos.index') }}" class="role-quick-card"><span><x-ui.icon name="products" :size="22" /></span><div><strong>Productos</strong><small>Catálogo, unidades y marcas.</small></div></a>
                    <a href="{{ route('inventario.index') }}" class="role-quick-card"><span><x-ui.icon name="inventory" :size="22" /></span><div><strong>Inventario</strong><small>Existencias y niveles de stock.</small></div></a>
                    <a href="{{ route('notas-ingreso.index') }}" class="role-quick-card"><span><x-ui.icon name="entry" :size="22" /></span><div><strong>Notas de ingreso</strong><small>Entradas confirmadas al inventario.</small></div></a>
                    <a href="{{ route('notas-salida.index') }}" class="role-quick-card"><span><x-ui.icon name="exit" :size="22" /></span><div><strong>Notas de salida</strong><small>Despachos y consumos registrados.</small></div></a>
                    <a href="{{ route('kardex.index') }}" class="role-quick-card"><span><x-ui.icon name="movements" :size="22" /></span><div><strong>Kardex</strong><small>Trazabilidad de entradas y salidas.</small></div></a>
                    <a href="{{ route('alertas.index') }}" class="role-quick-card"><span><x-ui.icon name="alerts" :size="22" /></span><div><strong>Alertas de stock</strong><small>Faltantes y niveles mínimos.</small></div></a>
                </div>
            </article>

            <article class="panel admin-area-card">
                <header class="panel__header">
                    <div><p class="eyebrow">Área financiera</p><h2>Contabilidad</h2></div>
                </header>
                <div class="admin-area-links">
                    <a href="{{ route('facturas-proveedor.index') }}" class="role-quick-card"><span><x-ui.icon name="coins" :size="22" /></span><div><strong>Facturas por pagar</strong><small>Documentos fiscales y recepción de proveedores.</small></div></a>
                    <a href="{{ route('modulos.show', 'cuentas-pagar') }}" class="role-quick-card"><span><x-ui.icon name="invoice" :size="22" /></span><div><strong>Cuentas por pagar</strong><small>Seguimiento de obligaciones con proveedores.</small></div></a>
                </div>
            </article>

            <article class="panel admin-area-card">
                <header class="panel__header">
                    <div><p class="eyebrow">Supervisión general</p><h2>Administración del sistema</h2></div>
                </header>
                <div class="admin-area-links">
                    <a href="{{ route('usuarios.index') }}" class="role-quick-card"><span><x-ui.icon name="users" :size="22" /></span><div><strong>Usuarios y permisos</strong><small>Roles definitivos y accesos.</small></div></a>
                    <a href="{{ route('empleados.index') }}" class="role-quick-card"><span><x-ui.icon name="users" :size="22" /></span><div><strong>Empleados</strong><small>Personal relacionado con las operaciones del sistema.</small></div></a>
                </div>
            </article>
        </section>
    @elseif ($modo === 'almacen')
        <section class="metric-grid" aria-label="Indicadores de almacén">
            @foreach ([
                ['Productos activos', 'productos', 'success', $resumen['productos_activos'], 'Catálogo disponible'],
                ['Bajo mínimo', 'warning', 'warning', $resumen['inventarios_bajo_minimo'], 'Requieren revisión'],
                ['Sin stock', 'box-off', 'danger', $resumen['sin_stock'], 'Atención prioritaria'],
                ['Ingresos hoy', 'entry', 'info', $resumen['ingresos_hoy'], 'Recepciones confirmadas'],
                ['Alertas abiertas', 'bell', 'warning', $resumen['alertas_abiertas'], 'Activas o atendidas'],
                ['Movimientos hoy', 'movements', 'info', $resumen['movimientos_hoy'], 'Entradas y salidas'],
            ] as [$titulo, $icono, $tono, $valor, $detalle])
                <article class="metric-card metric-card--{{ $tono }}">
                    <div class="metric-card__top">
                        <span class="metric-card__label">{{ $titulo }}</span>
                        <span class="metric-card__icon">
                            <x-ui.icon :name="$icono" :size="22" />
                        </span>
                    </div>
                    <strong>{{ number_format($valor) }}</strong>
                    <small>{{ $detalle }}</small>
                </article>
            @endforeach
        </section>

        <section class="dashboard-grid">
            <article class="panel {{ $movimientosRecientes->isEmpty() ? 'panel--empty-list' : '' }}">
                <header class="panel__header">
                    <div>
                        <p class="eyebrow">Actividad</p>
                        <h2>Movimientos recientes</h2>
                    </div>
                    @if (auth()->user()->puede('movimientos.ver'))
                        <a href="{{ route('movimientos.index') }}" class="text-link">
                            Ver módulo
                        </a>
                    @endif
                </header>

                @if ($movimientosRecientes->isNotEmpty())
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Producto</th>
                                    <th>Repisa</th>
                                    <th>Tipo</th>
                                    <th class="text-right">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($movimientosRecientes as $movimiento)
                                    <tr>
                                        <td>{{ $movimiento->fecha_movimiento?->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <strong>{{ $movimiento->producto?->codigo ?? '—' }}</strong>
                                            <span>{{ $movimiento->producto?->descripcion ?? '' }}</span>
                                        </td>
                                        <td>{{ $movimiento->repisa?->codigo ?? '—' }}</td>
                                        <td>
                                            <span class="badge badge--{{ $movimiento->tipo_movimiento === 'ENTRADA' ? 'success' : 'danger' }}">
                                                {{ $movimiento->tipo_movimiento }}
                                            </span>
                                        </td>
                                        <td class="text-right">
                                            <x-ui.quantity :value="$movimiento->cantidad" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty-table-state">
                        <span class="empty-state__icon">
                            <x-ui.icon name="movements" :size="28" />
                        </span>
                        <strong>Sin movimientos recientes</strong>
                        <span>Las entradas y salidas aparecerán aquí.</span>
                    </div>
                @endif
            </article>

            <article class="panel">
                <header class="panel__header">
                    <div>
                        <p class="eyebrow">Reposición</p>
                        <h2>Alertas abiertas</h2>
                    </div>
                    @if (auth()->user()->puede('alertas.ver'))
                        <a href="{{ route('alertas.index') }}" class="text-link">
                            Ver módulo
                        </a>
                    @endif
                </header>

                <div class="alert-list">
                    @forelse ($alertasRecientes as $alerta)
                        <div class="alert-item">
                            <div class="alert-item__marker alert-item__marker--{{ $alerta->nivel === 'CRITICA' ? 'danger' : 'warning' }}"></div>
                            <div class="alert-item__content">
                                <strong>{{ $alerta->producto?->codigo ?? 'Producto' }}</strong>
                                <span>
                                    Repisa {{ $alerta->repisa?->codigo ?? '—' }}
                                    · Stock <x-ui.quantity :value="$alerta->stock_actual" />
                                </span>
                                <small>{{ $alerta->tipo_alerta }}</small>
                            </div>
                            <span class="badge badge--{{ $alerta->estado === 'ACTIVA' ? 'danger' : 'warning' }}">
                                {{ $alerta->estado }}
                            </span>
                        </div>
                    @empty
                        <div class="empty-card">
                            <span class="empty-state__icon empty-state__icon--success">
                                <x-ui.icon name="check-circle" :size="34" />
                            </span>
                            <strong>Sin alertas abiertas</strong>
                            <span>El inventario no presenta condiciones pendientes.</span>
                        </div>
                    @endforelse
                </div>
            </article>
        </section>
    @elseif ($modo === 'comercial')
        <section class="metric-grid metric-grid--four" aria-label="Indicadores comerciales">
            @foreach ([
                ['Clientes activos', 'users', 'success', $resumen['clientes_activos'], 'Catálogo disponible'],
                ['Cotizaciones abiertas', 'quotes', 'warning', $resumen['cotizaciones_abiertas'], 'Pendientes de cierre'],
                ['Proveedores activos', 'suppliers', 'info', $resumen['proveedores_activos'], 'Catálogo de abastecimiento'],
                ['Compras aprobadas', 'purchase-order', 'success', $resumen['compras_aprobadas'], 'Convertidas en orden'],
            ] as [$titulo, $icono, $tono, $valor, $detalle])
                <article class="metric-card metric-card--{{ $tono }}">
                    <div class="metric-card__top">
                        <span class="metric-card__label">{{ $titulo }}</span>
                        <span class="metric-card__icon"><x-ui.icon :name="$icono" :size="22" /></span>
                    </div>
                    <strong>{{ number_format($valor) }}</strong>
                    <small>{{ $detalle }}</small>
                </article>
            @endforeach
        </section>

        <section class="role-quick-grid" aria-label="Accesos de ventas y compras">
            <a href="{{ route('clientes.index') }}" class="role-quick-card"><span><x-ui.icon name="users" :size="22" /></span><div><strong>Clientes</strong><small>Datos comerciales y direcciones.</small></div></a>
            <a href="{{ route('cotizaciones-cliente.index') }}" class="role-quick-card"><span><x-ui.icon name="quotes" :size="22" /></span><div><strong>Cotizaciones al cliente</strong><small>Registrar propuestas y darles seguimiento.</small></div></a>
            <a href="{{ route('ordenes-operacion.index') }}" class="role-quick-card"><span><x-ui.icon name="orders" :size="22" /></span><div><strong>Órdenes de Venta</strong><small>Consultar ventas aprobadas y su despacho.</small></div></a>
            <a href="{{ route('proveedores.index') }}" class="role-quick-card"><span><x-ui.icon name="suppliers" :size="22" /></span><div><strong>Proveedores</strong><small>Catálogo y datos de abastecimiento.</small></div></a>
            <a href="{{ route('requerimientos-compra.index') }}" class="role-quick-card"><span><x-ui.icon name="requisitions" :size="22" /></span><div><strong>Requerimientos</strong><small>Necesidades enviadas por Almacén.</small></div></a>
            <a href="{{ route('cotizaciones-proveedor.index') }}" class="role-quick-card"><span><x-ui.icon name="quotes" :size="22" /></span><div><strong>Cotizaciones de proveedores</strong><small>Comparar propuestas recibidas.</small></div></a>
            <a href="{{ route('solicitudes-compra.index') }}" class="role-quick-card"><span><x-ui.icon name="clipboard" :size="22" /></span><div><strong>Compras aprobadas</strong><small>Consultar decisiones y seguimiento.</small></div></a>
            <a href="{{ route('ordenes-compra.index') }}" class="role-quick-card"><span><x-ui.icon name="purchase-order" :size="22" /></span><div><strong>Órdenes de compra</strong><small>Emitir y controlar recepciones.</small></div></a>
        </section>
    @elseif ($modo === 'contabilidad')
        <section class="metric-grid metric-grid--four" aria-label="Indicadores contables">
            @foreach ([
                ['Facturas pendientes', 'invoice', 'warning', $resumen['facturas_pendientes'], 'Documentos registrados'],
                ['Facturas vencidas', 'alerts', 'danger', $resumen['facturas_vencidas'], 'Requieren atención'],
                ['Con recepción', 'entry', 'success', $resumen['facturas_con_recepcion'], 'Ingreso confirmado'],
                ['Pendiente en soles', 'coins', 'info', 'S/ '.number_format($resumen['total_pendiente_soles'], 2), 'Total documental'],
            ] as [$titulo, $icono, $tono, $valor, $detalle])
                <article class="metric-card metric-card--{{ $tono }}">
                    <div class="metric-card__top">
                        <span class="metric-card__label">{{ $titulo }}</span>
                        <span class="metric-card__icon">
                            <x-ui.icon :name="$icono" :size="22" />
                        </span>
                    </div>
                    <strong>{{ is_numeric($valor) ? number_format($valor) : $valor }}</strong>
                    <small>{{ $detalle }}</small>
                </article>
            @endforeach
        </section>

        <section class="role-quick-grid">
            <a href="{{ route('solicitudes-compra.index', ['estado' => 'CONVERTIDA']) }}" class="role-quick-card">
                <span><x-ui.icon name="clipboard" :size="24" /></span>
                <div>
                    <strong>Compras aprobadas</strong>
                    <small>Consultar decisiones que originaron órdenes de compra.</small>
                </div>
            </a>
            <a href="{{ route('facturas-proveedor.index') }}" class="role-quick-card">
                <span><x-ui.icon name="invoice" :size="24" /></span>
                <div><strong>Facturas de proveedores</strong><small>Revisar documentos, vencimientos y recepción.</small></div>
            </a>
            <a href="{{ route('ordenes-compra.index') }}" class="role-quick-card">
                <span><x-ui.icon name="purchase-order" :size="24" /></span>
                <div><strong>Órdenes de compra</strong><small>Consultar las compras emitidas por Logística / Compras.</small></div>
            </a>
            <a href="{{ route('modulos.show', 'cuentas-pagar') }}" class="role-quick-card">
                <span><x-ui.icon name="coins" :size="24" /></span>
                <div>
                    <strong>Cuentas por pagar</strong>
                    <small>Facturas de proveedores aprobadas.</small>
                </div>
            </a>
        </section>
    @else
        <section class="placeholder-panel">
            <div class="placeholder-panel__icon">!</div>
            <h1>Perfil sin configuración</h1>
            <p>Solicita al administrador que asigne un rol activo.</p>
        </section>
    @endif
@endsection
