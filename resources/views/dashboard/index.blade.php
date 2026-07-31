@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-kicker', 'Resumen del perfil')
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

    @if ($modo === 'almacen')
        <section class="metric-grid" aria-label="Indicadores de almacén">
            @foreach ([
                ['Productos activos', 'productos', 'success', $resumen['productos_activos'], 'Catálogo disponible'],
                ['Bajo mínimo', 'warning', 'warning', $resumen['inventarios_bajo_minimo'], 'Requieren revisión'],
                ['Sin stock', 'box-off', 'danger', $resumen['sin_stock'], 'Atención prioritaria'],
                ['Órdenes en curso', 'orders', 'info', $resumen['ordenes_en_curso'], 'Abiertas o en proceso'],
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
    @elseif ($modo === 'ordenes')
        <section class="metric-grid metric-grid--four" aria-label="Indicadores de órdenes">
            @foreach ([
                ['Órdenes abiertas', 'orders', 'info', $resumen['abiertas'], 'Pendientes de iniciar'],
                ['En proceso', 'activity', 'warning', $resumen['en_proceso'], 'Ejecución activa'],
                ['Cerradas este mes', 'check-circle', 'success', $resumen['cerradas_mes'], 'Trabajo finalizado'],
                ['Salidas hoy', 'exit', 'info', $resumen['salidas_hoy'], 'Materiales entregados'],
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

        <section class="panel role-order-panel {{ $ordenesRecientes->isEmpty() ? 'panel--empty-list' : '' }}">
            <header class="panel__header">
                <div>
                    <p class="eyebrow">Operación</p>
                    <h2>Órdenes activas</h2>
                </div>
                <a href="{{ route('ordenes-operacion.index') }}" class="text-link">
                    Ver órdenes
                </a>
            </header>

            @if ($ordenesRecientes->isNotEmpty())
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Orden</th>
                                <th>Tipo</th>
                                <th>Cliente</th>
                                <th>Apertura</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ordenesRecientes as $orden)
                                <tr>
                                    <td>
                                        <a href="{{ route('ordenes-operacion.show', $orden->id) }}" class="table-primary-link">
                                            {{ $orden->codigo_orden }}
                                        </a>
                                    </td>
                                    <td>{{ $orden->tipoOrden?->codigo ?? '—' }}</td>
                                    <td>{{ $orden->cliente?->razon_social ?? 'Sin cliente' }}</td>
                                    <td>{{ $orden->fecha_apertura?->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="badge badge--{{ $orden->estado === 'ABIERTA' ? 'info' : 'warning' }}">
                                            {{ str_replace('_', ' ', $orden->estado) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-table-state">
                    <span class="empty-state__icon">
                        <x-ui.icon name="orders" :size="28" />
                    </span>
                    <strong>Sin órdenes activas</strong>
                    <span>No existen órdenes abiertas o en proceso.</span>
                </div>
            @endif
        </section>
    @elseif ($modo === 'contabilidad')
        <section class="metric-grid metric-grid--three" aria-label="Indicadores contables">
            @foreach ([
                ['Órdenes cerradas', 'check-circle', 'success', $resumen['ordenes_cerradas'], 'Disponibles para el puente'],
                ['Salidas confirmadas', 'exit', 'info', $resumen['salidas_confirmadas'], 'Despachos registrados'],
                ['Cerradas hoy', 'clipboard', 'warning', $resumen['documentos_hoy'], 'Documentos del día'],
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

        <section class="role-quick-grid">
            <a href="{{ route('modulos.show', 'cuentas-cobrar') }}" class="role-quick-card">
                <span><x-ui.icon name="invoice" :size="24" /></span>
                <div>
                    <strong>Cuentas por cobrar</strong>
                    <small>Órdenes y ventas finalizadas.</small>
                </div>
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
