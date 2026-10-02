@extends('layouts.app')

@section('title', 'Cuentas por pagar')
@section('page-kicker', 'Contabilidad')
@section('page-title', 'Cuentas por pagar')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Facturas de proveedor</p>
            <h1>Cuentas por pagar</h1>
            <p>Saldo y vencimiento de cada factura. Los pagos se registran en la moneda del documento.</p>
        </div>
        <a href="{{ route('cuentas-pagar.csv', request()->only(['q', 'estado', 'moneda'])) }}" class="button button--ghost" data-file-download>Descargar saldos CSV</a>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('cuentas-pagar.index') }}" class="supplier-invoice-filter">
            <label class="form-field supplier-invoice-filter__search"><span>Buscar</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Documento, RUC o proveedor"></label>
            <label class="form-field"><span>Estado / vencimiento</span><select name="estado">
                <option value="">Todos</option>
                @foreach (['PENDIENTE' => 'Pendiente', 'PARCIAL' => 'Pago parcial', 'PAGADA' => 'Pagada', 'VENCIDA' => 'Vencida', 'POR_VENCER' => 'Próximos 7 días', 'SIN_VENCIMIENTO' => 'Sin vencimiento'] as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select></label>
            <label class="form-field"><span>Moneda</span><select name="moneda"><option value="">Todas</option><option value="PEN" @selected(request('moneda') === 'PEN')>PEN</option><option value="USD" @selected(request('moneda') === 'USD')>USD</option></select></label>
            <div class="filter-actions"><button class="button button--primary" type="submit">Filtrar</button><a class="button button--ghost" href="{{ route('cuentas-pagar.index') }}">Limpiar</a></div>
        </form>
    </section>

    <section class="panel" aria-label="Saldos pendientes por vencimiento">
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Moneda</th><th scope="col" class="text-right">Vencido</th><th scope="col" class="text-right">Próximos 7 días</th><th scope="col" class="text-right">Posterior</th><th scope="col" class="text-right">Sin fecha</th><th scope="col" class="text-right">Saldo filtrado</th></tr></thead>
                <tbody>
                    @foreach ($resumen as $moneda => $saldos)
                        <tr>
                            <th scope="row">{{ $moneda }}</th>
                            @foreach (['vencido', 'proximo', 'posterior', 'sin_fecha'] as $grupo)
                                <td class="text-right"><x-ui.money :value="$saldos[$grupo]" :currency="$moneda" /></td>
                            @endforeach
                            <td class="text-right"><strong><x-ui.money :value="array_sum($saldos)" :currency="$moneda" /></strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        @if ($facturas->isNotEmpty())
            <div class="table-wrap table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--responsive supplier-invoice-list-table">
                    <thead><tr><th class="table-details-heading"><span class="sr-only">Detalles</span></th><th>Documento</th><th>Proveedor</th><th>Vencimiento</th><th class="text-right">Total</th><th class="text-right">Saldo</th><th>Estado</th></tr></thead>
                    <tbody>
                    @foreach ($facturas as $factura)
                        @php($detalleId = 'payable-details-'.$factura->id)
                        @php($vencida = $factura->saldoPendiente() > 0 && $factura->fecha_vencimiento?->isBefore(today()))
                        <tr class="supplier-invoice-list-row">
                            <td class="table-details-cell"><x-ui.table-details-toggle :target="$detalleId" :label="'Ver datos de '.$factura->numeroVisible()" /></td>
                            <td class="supplier-invoice-list-row__document" data-label="Documento"><a class="table-primary-link" href="{{ route('facturas-proveedor.show', $factura) }}">{{ $factura->tipo_documento }} {{ $factura->numeroVisible() }}</a></td>
                            <td class="supplier-invoice-list-row__supplier" data-label="Proveedor">{{ $factura->proveedor?->nombreVisible() }}</td>
                            <td data-label="Vencimiento">{{ $factura->fecha_vencimiento?->format('d/m/Y') ?? 'Sin fecha' }}</td>
                            <td class="text-right" data-label="Total"><x-ui.money :value="$factura->total" :currency="$factura->moneda" /></td>
                            <td class="text-right" data-label="Saldo"><strong><x-ui.money :value="$factura->saldoPendiente()" :currency="$factura->moneda" /></strong></td>
                            <td data-label="Estado"><span class="badge badge--{{ $vencida ? 'danger' : $factura->estadoClase() }}">{{ $vencida ? 'Vencida' : $factura->estadoVisible() }}</span></td>
                        </tr>
                        <x-ui.table-row-details :id="$detalleId" :colspan="7">
                            <dl class="table-details-grid">
                                <div><dt>Orden de Compra</dt><dd>{{ $factura->ordenCompra?->codigo }}</dd></div>
                                <div><dt>Emisión</dt><dd>{{ $factura->fecha_emision?->format('d/m/Y') }}</dd></div>
                                <div><dt>Pagado</dt><dd><x-ui.money :value="$factura->montoPagado()" :currency="$factura->moneda" /></dd></div>
                                <div><dt>Acción</dt><dd><a href="{{ route('facturas-proveedor.show', $factura) }}">Ver pagos</a></dd></div>
                            </dl>
                        </x-ui.table-row-details>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$facturas" />
        @else
            <div class="empty-table-state"><strong>No hay facturas con estos filtros</strong><span>Las facturas registradas desde Compras aparecerán aquí.</span></div>
        @endif
    </section>
@endsection
