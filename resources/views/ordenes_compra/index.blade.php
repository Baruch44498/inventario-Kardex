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
            ['Pendientes de recepción', 'info', 'purchase-order', $resumen['recepcion']],
            ['Entregas atrasadas', 'danger', 'warning', $resumen['atrasadas']],
            ['Vencen hoy', 'warning', 'calendar', $resumen['vence_hoy']],
            ['Recepción parcial', 'warning', 'inventory', $resumen['parciales']],
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
                <span>Situación</span>
                <select name="situacion">
                    <option value="">Todas</option>
                    @foreach (['PENDIENTE' => 'Pendientes de recepción', 'ATRASADA' => 'Entrega atrasada', 'VENCE_HOY' => 'Vence hoy', 'EN_PLAZO' => 'En plazo', 'SIN_FECHA' => 'Sin fecha acordada', 'PARCIAL' => 'Recepción parcial', 'RECIBIDA' => 'Recibida completamente', 'ANULADA' => 'Anulada'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected(request('situacion') === $valor)>{{ $texto }}</option>
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
            <div class="table-wrap table-wrap--responsive">
                <table class="data-table data-table--responsive purchase-order-table purchase-order-list-table">
                    <thead>
                        <tr>
                            <th class="table-details-heading"><span class="sr-only">Detalles</span></th>
                            <th>Orden</th>
                            <th>Proveedor</th>
                            <th>Entrega</th>
                            <th class="text-right">Total</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ordenes as $orden)
                            @php($detalleFilaId = 'purchase-order-details-'.$orden->id)
                            <tr class="purchase-order-list-row">
                                <td class="table-details-cell">
                                    <x-ui.table-details-toggle
                                        :target="$detalleFilaId"
                                        :label="'Ver datos secundarios de '.$orden->codigo"
                                    />
                                </td>
                                <td class="purchase-order-list-row__order" data-label="Orden">
                                    <a href="{{ route('ordenes-compra.show', $orden) }}" class="table-primary-link">{{ $orden->codigo }}</a>
                                    <span>Solicitud {{ $orden->solicitudCompra?->codigo }}</span>
                                </td>
                                <td class="purchase-order-list-row__supplier" data-label="Proveedor">
                                    <strong>{{ $orden->proveedor?->nombreVisible() }}</strong>
                                    <span>RUC {{ $orden->proveedor?->ruc }}</span>
                                </td>
                                <td data-label="Entrega">
                                    <strong>{{ $orden->fecha_entrega_requerida?->format('d/m/Y') ?? 'No especificada' }}</strong>
                                    @if ($orden->permiteRecepcion())
                                        <span class="badge badge--{{ $orden->situacionEntregaClase() }}">{{ $orden->situacionEntregaVisible() }}</span>
                                        <span>{{ $orden->detallePlazoEntrega() }}</span>
                                    @endif
                                </td>
                                <td class="text-right purchase-order-list-row__total" data-label="Total">
                                    <strong><x-ui.money :value="$orden->total" :currency="$orden->moneda" /></strong>
                                    <span>{{ $orden->moneda }}</span>
                                </td>
                                <td data-label="Estado">
                                    <span class="badge badge--{{ $orden->estadoClase() }}">{{ $orden->estadoVisible() }}</span>
                                </td>
                                <td class="purchase-order-list-row__action" data-label="Acción">
                                    <div class="table-actions">
                                        @if ($puedeRegistrarIngreso && $orden->permiteRecepcion())
                                            <a href="{{ route('notas-ingreso.create', ['motivo_ingreso' => 'COMPRA', 'orden_compra_id' => $orden->id]) }}" class="button button--primary button--small">Recibir</a>
                                        @endif
                                        <a href="{{ route('ordenes-compra.show', $orden) }}" class="button button--ghost button--small">Ver orden</a>
                                    </div>
                                </td>
                            </tr>
                            <x-ui.table-row-details :id="$detalleFilaId" :colspan="7">
                                <dl class="table-details-grid purchase-order-list-details">
                                    <div>
                                        <dt>Origen</dt>
                                        <dd><span class="badge badge--{{ $orden->origenClase() }}">{{ $orden->origenVisible() }}</span></dd>
                                    </div>
                                    <div>
                                        <dt>Emisión</dt>
                                        <dd>{{ $orden->fecha_emision?->format('d/m/Y') }}</dd>
                                    </div>
                                    <div>
                                        <dt>Productos</dt>
                                        <dd>{{ $orden->detalles_count }} {{ $orden->detalles_count === 1 ? 'producto' : 'productos' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Saldo por recibir</dt>
                                        <dd>{{ $orden->detalles_pendientes_count }} {{ $orden->detalles_pendientes_count === 1 ? 'línea pendiente' : 'líneas pendientes' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Moneda</dt>
                                        <dd>{{ $orden->moneda }}</dd>
                                    </div>
                                </dl>
                            </x-ui.table-row-details>
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
