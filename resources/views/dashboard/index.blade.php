@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-kicker', 'Resumen del sistema')
@section('page-title', 'Dashboard')

@section('content')
    <section class="welcome-panel">
        <div>
            <p class="eyebrow">Sesión activa</p>
            <h1>Bienvenido, {{ auth()->user()->username ?? auth()->user()->name ?? 'Usuario' }}</h1>
            <p>
                Consulta el estado general del almacén y los movimientos
                recientes desde un único panel.
            </p>
        </div>

        <div class="welcome-panel__date">
            <span>Fecha</span>
            <strong>{{ now()->translatedFormat('d M Y') }}</strong>
        </div>
    </section>

    <section class="metric-grid" aria-label="Indicadores principales">
        <article class="metric-card metric-card--success">
            <div class="metric-card__top">
                <span class="metric-card__label">Productos activos</span>

                <span class="metric-card__icon">
                    <x-ui.icon name="products" :size="22" />
                </span>
            </div>

            <strong>{{ number_format($resumen['productos_activos']) }}</strong>
            <small>Catálogo disponible</small>
        </article>

        <article class="metric-card metric-card--warning">
            <div class="metric-card__top">
                <span class="metric-card__label">Bajo mínimo</span>

                <span class="metric-card__icon">
                    <x-ui.icon name="warning" :size="22" />
                </span>
            </div>

            <strong>{{ number_format($resumen['inventarios_bajo_minimo']) }}</strong>
            <small>Requieren revisión</small>
        </article>

        <article class="metric-card metric-card--danger">
            <div class="metric-card__top">
                <span class="metric-card__label">Sin stock</span>

                <span class="metric-card__icon">
                    <x-ui.icon name="box-off" :size="22" />
                </span>
            </div>

            <strong>{{ number_format($resumen['sin_stock']) }}</strong>
            <small>Atención prioritaria</small>
        </article>

        <a href="{{ route('ordenes-operacion.index', ['estado' => 'EN_PROCESO']) }}" class="metric-card metric-card--info metric-card--link">
            <div class="metric-card__top">
                <span class="metric-card__label">Órdenes en curso</span>

                <span class="metric-card__icon">
                    <x-ui.icon name="clipboard" :size="22" />
                </span>
            </div>

            <strong>{{ number_format($resumen['ordenes_en_curso']) }}</strong>
            <small>Abiertas o en proceso</small>
        </a>

        <article class="metric-card metric-card--warning">
            <div class="metric-card__top">
                <span class="metric-card__label">Alertas abiertas</span>

                <span class="metric-card__icon">
                    <x-ui.icon name="bell" :size="22" />
                </span>
            </div>

            <strong>{{ number_format($resumen['alertas_abiertas']) }}</strong>
            <small>Activas o atendidas</small>
        </article>

        <article class="metric-card metric-card--info">
            <div class="metric-card__top">
                <span class="metric-card__label">Movimientos hoy</span>

                <span class="metric-card__icon">
                    <x-ui.icon name="movements" :size="22" />
                </span>
            </div>

            <strong>{{ number_format($resumen['movimientos_hoy']) }}</strong>
            <small>Entradas y salidas</small>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="panel {{ $movimientosRecientes->isEmpty() ? 'panel--empty-list' : '' }}">
            <header class="panel__header">
                <div>
                    <p class="eyebrow">Actividad</p>
                    <h2>Movimientos recientes</h2>
                </div>

                <a
                    href="{{ in_array(auth()->user()->role?->codigo, ['ADMINISTRADOR', 'ALMACEN'], true) ? route('movimientos.index') : route('modulos.show', 'movimientos') }}"
                    class="text-link"
                >
                    Ver módulo
                </a>
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
                                    <td>
                                        {{ $movimiento->fecha_movimiento?->format('d/m/Y H:i') }}
                                    </td>

                                    <td>
                                        <strong>
                                            {{ $movimiento->producto?->codigo ?? '—' }}
                                        </strong>

                                        <span>
                                            {{ $movimiento->producto?->descripcion ?? '' }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ $movimiento->repisa?->codigo ?? '—' }}
                                    </td>

                                    <td>
                                        <span
                                            class="badge badge--{{ $movimiento->tipo_movimiento === 'ENTRADA' ? 'success' : 'danger' }}"
                                        >
                                            {{ $movimiento->tipo_movimiento }}
                                        </span>
                                    </td>

                                    <td class="text-right">
                                        {{ rtrim(rtrim($movimiento->cantidad, '0'), '.') }}
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

                    <span>
                        Las entradas y salidas aparecerán aquí.
                    </span>
                </div>
            @endif
        </article>

        <article class="panel">
            <header class="panel__header">
                <div>
                    <p class="eyebrow">Reposición</p>
                    <h2>Alertas abiertas</h2>
                </div>

                <a
                    href="{{ in_array(auth()->user()->role?->codigo, ['ADMINISTRADOR', 'ALMACEN'], true) ? route('alertas.index') : route('modulos.show', 'alertas') }}"
                    class="text-link"
                >
                    Ver módulo
                </a>
            </header>

            <div class="alert-list">
                @forelse ($alertasRecientes as $alerta)
                    <div class="alert-item">
                        <div
                            class="alert-item__marker alert-item__marker--{{ $alerta->nivel === 'CRITICA' ? 'danger' : 'warning' }}"
                        ></div>

                        <div class="alert-item__content">
                            <strong>
                                {{ $alerta->producto?->codigo ?? 'Producto' }}
                            </strong>

                            <span>
                                Repisa {{ $alerta->repisa?->codigo ?? '—' }}
                                · Stock {{ rtrim(rtrim($alerta->stock_actual, '0'), '.') }}
                            </span>

                            <small>{{ $alerta->tipo_alerta }}</small>
                        </div>

                        <span
                            class="badge badge--{{ $alerta->estado === 'ACTIVA' ? 'danger' : 'warning' }}"
                        >
                            {{ $alerta->estado }}
                        </span>
                    </div>
                @empty
                    <div class="empty-card">
                        <span class="empty-state__icon empty-state__icon--success">
                            <x-ui.icon name="check-circle" :size="34" />
                        </span>

                        <strong>Sin alertas abiertas</strong>

                        <span>
                            El inventario no presenta condiciones pendientes.
                        </span>
                    </div>
                @endforelse
            </div>
        </article>
    </section>
@endsection
