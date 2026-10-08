@extends('layouts.app')

@section('title', 'Cuentas por cobrar')
@section('page-kicker', 'Contabilidad')
@section('page-title', 'Cuentas por cobrar')

@section('content')
    <section class="module-header">
        <div><p class="eyebrow">Control de cobros</p><h1>Cuentas por cobrar</h1><p>Ventas directas con cotización cerrada y trabajos con orden principal cerrada. Cada versión comercial aparece una sola vez.</p></div>
        <a href="{{ route('cuentas-cobrar.csv', request()->only(['q', 'origen', 'moneda', 'estado'])) }}" class="button button--ghost" data-file-download>Descargar saldos CSV</a>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('cuentas-cobrar.index') }}" class="supplier-invoice-filter">
            <label class="form-field supplier-invoice-filter__search"><span>Buscar</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Cotización, documento o cliente"></label>
            <label class="form-field"><span>Origen</span><select name="origen"><option value="">Todos</option><option value="VENTA_DIRECTA" @selected(request('origen') === 'VENTA_DIRECTA')>Venta directa</option><option value="ORDEN" @selected(request('origen') === 'ORDEN')>Orden cerrada</option></select></label>
            <label class="form-field"><span>Estado del cobro</span><select name="estado"><option value="">Todos</option><option value="PENDIENTE" @selected(request('estado') === 'PENDIENTE')>Pendiente</option><option value="PARCIAL" @selected(request('estado') === 'PARCIAL')>Parcial</option><option value="COBRADA" @selected(request('estado') === 'COBRADA')>Cobrada</option></select></label>
            <label class="form-field"><span>Moneda</span><select name="moneda"><option value="">Todas</option><option value="PEN" @selected(request('moneda') === 'PEN')>PEN</option><option value="USD" @selected(request('moneda') === 'USD')>USD</option></select></label>
            <div class="filter-actions"><button class="button button--primary" type="submit">Filtrar</button><a href="{{ route('cuentas-cobrar.index') }}" class="button button--ghost">Limpiar</a></div>
        </form>
    </section>

    <section class="panel" aria-label="Resumen de cuentas por cobrar filtradas">
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th scope="col">Moneda</th><th scope="col" class="text-right">Total</th><th scope="col" class="text-right">Cobrado</th><th scope="col" class="text-right">Por cobrar</th></tr></thead>
                <tbody>
                    @foreach ($resumen as $moneda => $montos)
                        <tr>
                            <th scope="row">{{ $moneda }}</th>
                            <td class="text-right"><x-ui.money :value="$montos['total']" :currency="$moneda" /></td>
                            <td class="text-right"><x-ui.money :value="$montos['cobrado']" :currency="$moneda" /></td>
                            <td class="text-right"><strong><x-ui.money :value="$montos['saldo']" :currency="$moneda" /></strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        @if ($cotizaciones->isNotEmpty())
            <div class="table-wrap table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--responsive supplier-invoice-list-table">
                    <thead><tr><th class="table-details-heading"><span class="sr-only">Detalles</span></th><th>Cotización</th><th>Cliente</th><th>Origen</th><th class="text-right">Total</th><th class="text-right">Saldo</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($cotizaciones as $cotizacion)
                            @php($detalleId = 'receivable-details-'.$cotizacion->id)
                            @php($cobrado = $cotizacion->montoCobrado())
                            <tr class="supplier-invoice-list-row">
                                <td class="table-details-cell"><x-ui.table-details-toggle :target="$detalleId" :label="'Ver datos de '.$cotizacion->codigo" /></td>
                                <td class="supplier-invoice-list-row__document" data-label="Cotización"><a href="{{ route('cuentas-cobrar.show', $cotizacion) }}" class="table-primary-link">{{ $cotizacion->codigo }}</a></td>
                                <td class="supplier-invoice-list-row__supplier" data-label="Cliente">{{ $cotizacion->cliente_nombre }}</td>
                                <td data-label="Origen">{{ $cotizacion->proforma_id ? 'Venta directa' : 'Orden '.$cotizacion->ordenOperacion?->codigo_orden }}</td>
                                <td class="text-right" data-label="Total"><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></td>
                                <td class="text-right" data-label="Saldo"><strong><x-ui.money :value="$cotizacion->saldoPorCobrar()" :currency="$cotizacion->moneda" /></strong></td>
                                <td data-label="Estado"><span class="badge badge--{{ $cotizacion->saldoPorCobrar() === 0.0 ? 'success' : ($cobrado > 0 ? 'warning' : 'info') }}">{{ $cotizacion->saldoPorCobrar() === 0.0 ? 'Cobrado' : ($cobrado > 0 ? 'Cobro parcial' : 'Pendiente') }}</span></td>
                                <td data-label="Acción">
                                    @if (auth()->user()->puede('contabilidad.registrar_cobros') && $cotizacion->saldoPorCobrar() > 0)
                                        <a href="{{ route('cuentas-cobrar.show', $cotizacion) }}#nuevo-cobro" class="button button--ghost button--small" aria-label="Registrar cobro de {{ $cotizacion->codigo }}">Registrar cobro</a>
                                    @else
                                        <a href="{{ route('cuentas-cobrar.show', $cotizacion) }}" class="button button--ghost button--small" aria-label="Ver cobros de {{ $cotizacion->codigo }}">Ver cobros</a>
                                    @endif
                                </td>
                            </tr>
                            <x-ui.table-row-details :id="$detalleId" :colspan="8">
                                <dl class="table-details-grid">
                                    <div><dt>Emisión</dt><dd>{{ $cotizacion->fecha_emision?->format('d/m/Y') }}</dd></div>
                                    <div><dt>Cobrado</dt><dd><x-ui.money :value="$cobrado" :currency="$cotizacion->moneda" /></dd></div>
                                    <div><dt>Documento del cliente</dt><dd>{{ $cotizacion->cliente_documento ?: 'No indicado' }}</dd></div>
                                </dl>
                            </x-ui.table-row-details>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$cotizaciones" />
        @else
            <div class="empty-table-state"><strong>No hay ventas terminadas con estos filtros</strong><span>La venta directa aparece al cerrar su cotización; los trabajos al cerrar la orden principal.</span></div>
        @endif
    </section>
@endsection
