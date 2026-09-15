@extends('layouts.app')

@section('title', 'Facturas de proveedor')
@section('page-kicker', 'Compras y Contabilidad')
@section('page-title', 'Facturas de proveedor')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Documentos fiscales de compra</p>
            <h1>Facturas de proveedor</h1>
            <p>Contabilidad consulta los documentos registrados, su base imponible, crédito fiscal, total y las recepciones conciliadas.</p>
        </div>
        @if ($puedeRegistrar)
            <a href="{{ route('ordenes-compra.index') }}" class="button button--primary"><x-ui.icon name="purchase-order" :size="17" /> Elegir Orden de Compra</a>
        @endif
    </section>

    <section class="summary-strip summary-strip--four" aria-label="Resumen de facturas">
        @foreach ([
            ['Documentos activos', 'info', 'invoice', $resumen['registradas']],
            ['Con recepción', 'success', 'entry', $resumen['con_recepcion']],
            ['Base imponible', 'info', 'banknote', 'S/ '.number_format($resumen['base_soles'], 2, '.', ',')],
            ['Crédito fiscal IGV', 'warning', 'coins', 'S/ '.number_format($resumen['credito_fiscal_soles'], 2, '.', ',')],
        ] as [$titulo, $tono, $icono, $valor])
            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--{{ $tono }}"><x-ui.icon :name="$icono" :size="20" /></span>
                <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
            </article>
        @endforeach
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('facturas-proveedor.index') }}" class="supplier-invoice-filter">
            <label class="form-field supplier-invoice-filter__search">
                <span>Buscar</span>
                <div class="input-with-icon"><span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span><input type="search" name="q" value="{{ request('q') }}" placeholder="Serie, número, OC, RUC o proveedor"></div>
            </label>
            <label class="form-field"><span>Estado</span><select name="estado"><option value="">Todos</option>@foreach (['REGISTRADA' => 'Registrada', 'PAGADA' => 'Pagada', 'ANULADA' => 'Anulada'] as $valor => $texto)<option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $texto }}</option>@endforeach</select></label>
            <label class="form-field"><span>Moneda</span><select name="moneda"><option value="">Todas</option><option value="PEN" @selected(request('moneda') === 'PEN')>PEN</option><option value="USD" @selected(request('moneda') === 'USD')>USD</option></select></label>
            <label class="form-field"><span>Desde</span><input type="date" name="desde" value="{{ request('desde') }}"></label>
            <label class="form-field"><span>Hasta</span><input type="date" name="hasta" value="{{ request('hasta') }}"></label>
            <div class="filter-actions"><button class="button button--primary" type="submit"><x-ui.icon name="filter" :size="17" /> Filtrar</button><a class="button button--ghost" href="{{ route('facturas-proveedor.index') }}">Limpiar</a></div>
        </form>
    </section>

    <section class="panel">
        @if ($facturas->isNotEmpty())
            <div class="table-wrap table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--responsive supplier-invoice-table supplier-invoice-list-table">
                    <thead><tr><th class="table-details-heading"><span class="sr-only">Detalles</span></th><th>Documento</th><th>Proveedor</th><th>Emisión</th><th class="text-right">Importe</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($facturas as $factura)
                            @php($conciliacion = $factura->ordenCompra->conciliacionFacturas())
                            @php($detalleFilaId = 'supplier-invoice-details-'.$factura->id)
                            <tr class="supplier-invoice-list-row">
                                <td class="table-details-cell"><x-ui.table-details-toggle :target="$detalleFilaId" :label="'Ver datos secundarios de '.$factura->numeroVisible()" /></td>
                                <td class="supplier-invoice-list-row__document" data-label="Documento"><a href="{{ route('facturas-proveedor.show', $factura) }}" class="table-primary-link">{{ $factura->tipo_documento }} {{ $factura->numeroVisible() }}</a><span>{{ $factura->archivo_original_nombre }}</span></td>
                                <td class="supplier-invoice-list-row__supplier" data-label="Proveedor"><strong>{{ $factura->proveedor?->nombreVisible() }}</strong><span>RUC {{ $factura->proveedor?->ruc }}</span></td>
                                <td data-label="Emisión">{{ $factura->fecha_emision?->format('d/m/Y') }}</td>
                                <td class="text-right supplier-invoice-list-row__total" data-label="Importe"><strong><x-ui.money :value="$factura->total" :currency="$factura->moneda" /></strong><span>{{ $factura->moneda }}</span></td>
                                <td data-label="Estado"><span class="badge badge--{{ $factura->estadoClase() }}">{{ $factura->estadoVisible() }}</span></td>
                                <td class="supplier-invoice-list-row__action" data-label="Acción"><a href="{{ route('facturas-proveedor.show', $factura) }}" class="button button--ghost button--small">Ver factura</a></td>
                            </tr>
                            <x-ui.table-row-details :id="$detalleFilaId" :colspan="7">
                                <dl class="table-details-grid supplier-invoice-list-details">
                                    <div><dt>Orden de Compra</dt><dd><a href="{{ route('ordenes-compra.show', $factura->ordenCompra) }}">{{ $factura->ordenCompra?->codigo }}</a></dd></div>
                                    <div><dt>Base imponible</dt><dd><x-ui.money :value="$factura->subtotal" :currency="$factura->moneda" /></dd></div>
                                    <div><dt>IGV / crédito fiscal</dt><dd><x-ui.money :value="$factura->impuesto" :currency="$factura->moneda" />@unless($factura->permiteCreditoFiscal())<small>Sin crédito fiscal</small>@endunless</dd></div>
                                    <div><dt>Conciliación</dt><dd><span class="badge badge--{{ $conciliacion['clase'] }}">{{ $conciliacion['etiqueta'] }}</span></dd></div>
                                    <div><dt>Recepciones vinculadas</dt><dd>{{ $factura->notas_ingreso_count + $factura->detalles_con_recepcion_count }}</dd></div>
                                </dl>
                            </x-ui.table-row-details>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$facturas" />
        @else
            <div class="empty-table-state"><span class="empty-state__icon"><x-ui.icon name="invoice" :size="30" /></span><strong>No hay facturas con estos filtros</strong><span>Almacén puede registrarlas desde el detalle de una Orden de Compra.</span></div>
        @endif
    </section>
@endsection
