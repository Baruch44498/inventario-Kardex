@extends('layouts.app')

@section('title', 'Órdenes de compra')
@section('page-kicker', 'Compras')
@section('page-title', 'Órdenes de compra')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Compras autorizadas</p>
            <h1>Órdenes de compra</h1>
            <p>Seguimiento desde la aprobación de Compras hasta la recepción completa en Almacén.</p>
        </div>
        @if ($puedeGestionarCompras)
            <a href="{{ route('solicitudes-compra.index') }}" class="button button--primary"><x-ui.icon name="check-circle" :size="17" /> Ver compras aprobadas</a>
        @elseif ($puedeRegistrarIngreso)
            <a href="{{ route('notas-ingreso.create', ['motivo_ingreso' => 'COMPRA']) }}" class="button button--primary"><x-ui.icon name="entry" :size="17" /> Registrar recepción</a>
        @endif
    </section>

    <section class="summary-strip summary-strip--four">
        @foreach ([
            ['Por recibir', 'info', 'purchase-order', $resumen['recepcion']],
            ['Recepción parcial', 'warning', 'inventory', $resumen['parciales']],
            ['Recibidas', 'success', 'check-circle', $resumen['recibidas']],
            ['Anuladas', 'danger', 'error', $resumen['anuladas']],
        ] as [$titulo, $tono, $icono, $valor])
            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--{{ $tono }}"><x-ui.icon :name="$icono" :size="20" /></span>
                <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
            </article>
        @endforeach
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('ordenes-compra.index') }}" class="purchase-order-filter">
            <label class="form-field purchase-order-filter__search">
                <span>Buscar</span>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="OC, documento, RUC o proveedor">
                </div>
            </label>
            <label class="form-field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    @foreach (['APROBADA' => 'Aprobada para recepción', 'PARCIALMENTE_RECIBIDA' => 'Recepción parcial', 'RECIBIDA' => 'Recibida completamente', 'ANULADA' => 'Anulada'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-field">
                <span>Origen</span>
                <select name="origen">
                    <option value="">Todos</option>
                    @foreach (['REQUERIMIENTO' => 'Desde requerimiento', 'COMPRA_DIRECTA' => 'Compra directa', 'REGULARIZACION' => 'Regularización', 'URGENTE' => 'Compra urgente', 'REPOSICION' => 'Reposición directa'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected(request('origen') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-field"><span>Desde</span><input type="date" name="desde" value="{{ request('desde') }}"></label>
            <label class="form-field"><span>Hasta</span><input type="date" name="hasta" value="{{ request('hasta') }}"></label>
            <div class="filter-actions">
                <button class="button button--primary" type="submit"><x-ui.icon name="filter" :size="17" /> Filtrar</button>
                <a class="button button--ghost" href="{{ route('ordenes-compra.index') }}">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="panel">
        @if ($ordenes->isNotEmpty())
            <div class="table-wrap">
                <table class="data-table purchase-order-table">
                    <thead><tr><th>Orden</th><th>Origen</th><th>Proveedor</th><th>Emisión</th><th>Entrega requerida</th><th>Productos</th><th>Moneda</th><th class="text-right">Total</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($ordenes as $orden)
                            <tr>
                                <td><strong>{{ $orden->codigo }}</strong><span>Solicitud {{ $orden->solicitudCompra?->codigo }}</span></td>
                                <td><span class="badge badge--{{ $orden->origenClase() }}">{{ $orden->origenVisible() }}</span></td>
                                <td><strong>{{ $orden->proveedor?->nombreVisible() }}</strong><span>{{ $orden->proveedor?->ruc }}</span></td>
                                <td>{{ $orden->fecha_emision?->format('d/m/Y') }}</td>
                                <td>{{ $orden->fecha_entrega_requerida?->format('d/m/Y') ?? 'No especificada' }}</td>
                                <td>{{ $orden->detalles_count }}</td>
                                <td><span class="currency-chip">{{ $orden->moneda }}</span></td>
                                <td class="text-right"><strong><x-ui.money :value="$orden->total" :currency="$orden->moneda" /></strong></td>
                                <td><span class="badge badge--{{ $orden->estadoClase() }}">{{ $orden->estadoVisible() }}</span></td>
                                <td><a href="{{ route('ordenes-compra.show', $orden) }}" class="button button--ghost button--small">Ver orden</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$ordenes" />
        @else
            <div class="empty-table-state">
                <span class="empty-state__icon"><x-ui.icon name="purchase-order" :size="30" /></span>
                <strong>No hay órdenes con estos filtros</strong>
                <span>Las órdenes se generan cuando Compras aprueba una cotización de proveedor.</span>
            </div>
        @endif
    </section>
@endsection
