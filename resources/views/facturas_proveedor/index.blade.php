@extends('layouts.app')

@section('title', 'Facturas de proveedor')
@section('page-kicker', 'Compras y Contabilidad')
@section('page-title', 'Facturas de proveedor')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Documentos fiscales de compra</p>
            <h1>Facturas de proveedor</h1>
            <p>Almacén registra el documento físico; Contabilidad consulta base imponible, crédito fiscal, total y recepción vinculada.</p>
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
            <div class="table-wrap table-wrap--wide">
                <table class="data-table supplier-invoice-table">
                    <thead><tr><th>Documento</th><th>Proveedor</th><th>Orden</th><th>Emisión</th><th class="text-right">Base</th><th class="text-right">IGV / crédito fiscal</th><th class="text-right">Total</th><th>Recepción</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($facturas as $factura)
                            @php($conciliacion = $factura->ordenCompra->conciliacionFacturas())
                            <tr>
                                <td><strong>{{ $factura->tipo_documento }} {{ $factura->numeroVisible() }}</strong><span>{{ $factura->archivo_original_nombre }}</span></td>
                                <td><strong>{{ $factura->proveedor?->nombreVisible() }}</strong><span>{{ $factura->proveedor?->ruc }}</span></td>
                                <td><a href="{{ route('ordenes-compra.show', $factura->ordenCompra) }}" class="table-primary-link">{{ $factura->ordenCompra?->codigo }}</a></td>
                                <td>{{ $factura->fecha_emision?->format('d/m/Y') }}</td>
                                <td class="text-right"><x-ui.money :value="$factura->subtotal" :currency="$factura->moneda" /></td>
                                <td class="text-right"><x-ui.money :value="$factura->impuesto" :currency="$factura->moneda" />@unless($factura->permiteCreditoFiscal())<small>Sin crédito fiscal</small>@endunless</td>
                                <td class="text-right"><strong><x-ui.money :value="$factura->total" :currency="$factura->moneda" /></strong></td>
                                <td><span class="badge badge--{{ $conciliacion['clase'] }}">{{ $conciliacion['etiqueta'] }}</span><small>{{ $factura->notas_ingreso_count }} nota(s)</small></td>
                                <td><span class="badge badge--{{ $factura->estadoClase() }}">{{ $factura->estadoVisible() }}</span></td>
                                <td><a href="{{ route('facturas-proveedor.show', $factura) }}" class="button button--ghost button--small">Ver factura</a></td>
                            </tr>
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
