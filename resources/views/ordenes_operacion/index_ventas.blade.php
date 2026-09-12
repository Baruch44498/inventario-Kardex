@extends('layouts.app')

@section('title', 'Órdenes de Venta')
@section('page-kicker', 'Ventas')
@section('page-title', 'Órdenes de Venta')

@section('content')
    <section class="module-header operation-index-header">
        <div>
            <p class="eyebrow">OV</p>
            <h1>Órdenes de Venta</h1>
            <p>Consulta las ventas generadas desde cotizaciones cerradas y aprobadas.</p>
        </div>

        @if (auth()->user()->puede('proformas.cotizar'))
            <a href="{{ route('cotizaciones-cliente.create') }}" class="button button--primary">
                <x-ui.icon name="plus" :size="18" /> Nueva cotización
            </a>
        @endif
    </section>

    <section class="summary-strip summary-strip--four operation-summary" aria-label="Resumen de órdenes de venta">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--neutral"><x-ui.icon name="orders" :size="21" /></span>
            <div><span>Total</span><strong>{{ (int) ($resumen->total ?? 0) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="clipboard" :size="21" /></span>
            <div><span>Abiertas</span><strong>{{ (int) ($resumen->abiertas ?? 0) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--warning"><x-ui.icon name="activity" :size="21" /></span>
            <div><span>En proceso</span><strong>{{ (int) ($resumen->en_proceso ?? 0) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success"><x-ui.icon name="check-circle" :size="21" /></span>
            <div><span>Cerradas</span><strong>{{ (int) ($resumen->cerradas ?? 0) }}</strong></div>
        </article>
    </section>

    <section class="panel filter-panel operation-filter-panel">
        <form method="GET" action="{{ route('ordenes-operacion.index') }}" class="operation-filter-grid">
            <label class="form-field operation-filter-grid__search">
                <span>Buscar</span>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Código, descripción, cliente o RUC">
                </div>
            </label>

            <label class="form-field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    @foreach ([
                        'ACTIVAS' => 'Activas',
                        'ABIERTA' => 'Abierta',
                        'EN_PROCESO' => 'En proceso',
                        'CERRADA' => 'Cerrada',
                        'ANULADA' => 'Anulada',
                    ] as $valor => $nombre)
                        <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </label>

            <label class="form-field"><span>Desde</span><input type="date" name="desde" value="{{ request('desde') }}"></label>
            <label class="form-field"><span>Hasta</span><input type="date" name="hasta" value="{{ request('hasta') }}"></label>

            <div class="filter-actions operation-filter-actions">
                <button type="submit" class="button button--primary"><x-ui.icon name="filter" :size="17" /> Filtrar</button>
                <a href="{{ route('ordenes-operacion.index') }}" class="button button--ghost">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="panel {{ $ordenes->isEmpty() ? 'panel--empty-list' : '' }}">
        @if ($ordenes->isNotEmpty())
            <div class="table-wrap table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--actions data-table--responsive">
                    <thead>
                        <tr>
                            <th>Orden de Venta</th>
                            <th>Cliente</th>
                            <th>Cotización</th>
                            <th>Apertura</th>
                            <th class="text-right">Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ordenes as $orden)
                            @php
                                $cotizacion = $orden->cotizacionCliente;
                                $tono = match ($orden->estado) {
                                    'ABIERTA' => 'info',
                                    'EN_PROCESO' => 'warning',
                                    'CERRADA' => 'success',
                                    'ANULADA' => 'danger',
                                    default => 'neutral',
                                };
                            @endphp
                            <tr>
                                <td data-label="Orden"><strong>{{ $orden->codigo_orden }}</strong><span>{{ $orden->descripcion }}</span></td>
                                <td data-label="Cliente"><strong>{{ $orden->cliente?->razon_social ?: 'Sin cliente' }}</strong><span>{{ $orden->cliente?->ruc }}</span></td>
                                <td data-label="Cotización">
                                    @if ($cotizacion)
                                        <a href="{{ route('cotizaciones-cliente.show', $cotizacion) }}">{{ $cotizacion->codigo }}</a>
                                        <span>{{ (int) ($cotizacion->detalles_count ?? 0) }} producto(s)</span>
                                    @else
                                        <span>No disponible</span>
                                    @endif
                                </td>
                                <td data-label="Apertura">{{ $orden->fecha_apertura?->format('d/m/Y') }}</td>
                                <td data-label="Total" class="text-right">
                                    @if ($cotizacion)
                                        <strong><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></strong>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="Estado"><x-ui.status-badge :tone="$tono">{{ str_replace('_', ' ', $orden->estado) }}</x-ui.status-badge></td>
                                <td data-label="Acciones"><a href="{{ route('ordenes-operacion.show', $orden) }}" class="button button--ghost button--small">Ver OV</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $ordenes->links() }}
        @else
            <div class="empty-state">
                <x-ui.icon name="orders" :size="34" />
                <h2>No hay Órdenes de Venta</h2>
                <p>La primera OV aparecerá cuando cierres y apruebes una cotización.</p>
            </div>
        @endif
    </section>
@endsection
