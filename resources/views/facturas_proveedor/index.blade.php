@extends('layouts.app')

@section('title', 'Facturas de proveedor')
@section('page-kicker', 'Compras y Contabilidad')
@section('page-title', 'Facturas de proveedor')

@section('content')
    @php
        $facturas->getCollection()->loadMissing('pagosVigentes');
        $pendientesPagina = $facturas->getCollection()->filter(fn ($factura) => ! $factura->estaAnulada() && $factura->saldoPendiente() > 0)->groupBy('moneda');
        $vencidasPagina = $facturas->getCollection()->filter(fn ($factura) => ! $factura->estaAnulada() && $factura->saldoPendiente() > 0 && $factura->fecha_vencimiento?->lt(today()))->groupBy('moneda');
    @endphp
    <div @class(['supplier-invoice-list', 'supplier-invoice-list--readonly' => ! $puedeRegistrar])>
    <section class="module-header">
        <div>
            <p class="eyebrow">Documentos fiscales de compra</p>
            <h1>Facturas de proveedor</h1>
            <p>{{ $puedeRegistrar ? 'Registra aquí el comprobante de lo que Almacén ya recibió.' : 'Consulta los comprobantes, saldos y recepciones vinculadas a cada orden.' }}</p>
        </div>
        @if ($puedeRegistrar)
            <a href="#pendientes-facturar" class="button button--primary"><x-ui.icon name="invoice" :size="17" /> Registrar factura</a>
        @endif
    </section>

    @unless ($puedeRegistrar)
        <details class="supplier-invoice-list__pending-disclosure" @if (request()->has('pendientes_page')) open @endif><summary>Consultar recepciones pendientes de facturar ({{ $paginasPendientes->total() }})</summary>
    @endunless
    <section id="pendientes-facturar" class="panel supplier-invoice-pending" aria-labelledby="pendientes-facturar-titulo">
        <div class="supplier-invoice-pending__heading">
            <div><h2 id="pendientes-facturar-titulo">Pendientes de facturar</h2><p>Recepciones confirmadas con cantidades aún disponibles para facturar.</p></div>
            <a href="{{ route('ordenes-compra.index') }}" class="button button--ghost button--small">Ver órdenes de compra</a>
        </div>
        @if ($pendientesFacturar->isNotEmpty())
            <div class="table-wrap table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--responsive supplier-invoice-pending__table">
                    <thead><tr><th>Orden de compra</th><th>Proveedor</th><th>Cantidad pendiente</th><th>Última recepción</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($pendientesFacturar as $pendiente)
                            <tr>
                                <td data-label="Orden de compra"><a href="{{ route('ordenes-compra.show', $pendiente['orden']) }}" class="table-primary-link">{{ $pendiente['orden']->codigo }}</a></td>
                                <td data-label="Proveedor">{{ $pendiente['orden']->proveedor?->nombreVisible() }}</td>
                                <td data-label="Cantidad pendiente">
                                    @if ($pendiente['unidades']->count() === 1)
                                        <x-ui.quantity :value="$pendiente['cantidad']" /> {{ $pendiente['unidades']->first() }} · {{ $pendiente['lineas'] }} {{ $pendiente['lineas'] === 1 ? 'partida' : 'partidas' }}
                                    @else
                                        {{ $pendiente['lineas'] }} partidas con unidades distintas
                                    @endif
                                </td>
                                <td data-label="Última recepción">{{ \Illuminate\Support\Carbon::parse($pendiente['ultima_recepcion'])->format('d/m/Y') }}</td>
                                <td data-label="Acción">
                                    @if ($puedeRegistrar)
                                        <a href="{{ route('facturas-proveedor.create', $pendiente['orden']) }}" class="button button--primary button--small">Registrar factura</a>
                                    @else
                                        <a href="{{ route('ordenes-compra.show', $pendiente['orden']) }}" class="button button--ghost button--small">Ver OC</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$paginasPendientes" />
        @else
            <div class="empty-table-state supplier-invoice-pending__empty"><strong>No hay recepciones pendientes de facturar</strong><span>Las órdenes aparecerán aquí cuando Almacén confirme una recepción.</span></div>
        @endif
    </section>

    @unless ($puedeRegistrar)
        </details>
    @endunless

    <section class="summary-strip" aria-label="Resumen de facturas">
        @foreach ([
            ['Documentos activos', 'info', 'invoice', $resumen['registradas']],
            ['Base imponible', 'info', 'banknote', 'S/ '.number_format($resumen['base_soles'], 2, '.', ',')],
            ['Crédito fiscal IGV', 'warning', 'coins', 'S/ '.number_format($resumen['credito_fiscal_soles'], 2, '.', ',')],
        ] as [$titulo, $tono, $icono, $valor])
            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--{{ $tono }}"><x-ui.icon :name="$icono" :size="20" /></span>
                <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
            </article>
        @endforeach
    </section>

    @if ($pendientesPagina->isNotEmpty() || $vencidasPagina->isNotEmpty())
        <section class="supplier-invoice-list__balances" aria-label="Saldos de la página actual">
            @foreach (['PEN', 'USD'] as $moneda)
                @if ($pendientesPagina->has($moneda))
                    <article class="metric-card"><span>Por pagar · {{ $moneda }} · en esta página</span><strong><x-ui.money :value="$pendientesPagina[$moneda]->sum(fn ($factura) => $factura->saldoPendiente())" :currency="$moneda" /></strong></article>
                @endif
                @if ($vencidasPagina->has($moneda))
                    <article class="metric-card supplier-invoice-list__overdue"><span>Vencidas · {{ $moneda }} · en esta página</span><strong><x-ui.money :value="$vencidasPagina[$moneda]->sum(fn ($factura) => $factura->saldoPendiente())" :currency="$moneda" /></strong></article>
                @endif
            @endforeach
        </section>
    @endif

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('facturas-proveedor.index') }}" class="supplier-invoice-filter">
            <label class="form-field supplier-invoice-filter__search">
                <span>Buscar</span>
                <div class="input-with-icon"><span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span><input type="search" name="q" value="{{ request('q') }}" placeholder="Serie, número, OC, RUC o proveedor"></div>
            </label>
            <label class="form-field"><span>Estado</span><select name="estado"><option value="">Todos</option>@foreach (['REGISTRADA' => 'Registrada', 'PARCIAL' => 'Pago parcial', 'PAGADA' => 'Pagada', 'ANULADA' => 'Anulada'] as $valor => $texto)<option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $texto }}</option>@endforeach</select></label>
            <label class="form-field"><span>Moneda</span><select name="moneda"><option value="">Todas</option><option value="PEN" @selected(request('moneda') === 'PEN')>PEN</option><option value="USD" @selected(request('moneda') === 'USD')>USD</option></select></label>
            <label class="form-field"><span>Desde</span><input type="date" name="desde" value="{{ request('desde') }}"></label>
            <label class="form-field"><span>Hasta</span><input type="date" name="hasta" value="{{ request('hasta') }}"></label>
            <div class="filter-actions"><button class="button button--primary" type="submit"><x-ui.icon name="filter" :size="17" /> Filtrar</button><a class="button button--ghost" href="{{ route('facturas-proveedor.index') }}">Limpiar</a></div>
        </form>
    </section>

    <section class="panel" aria-labelledby="supplier-invoice-list-heading">
        <div class="supplier-invoice-list__heading"><h2 id="supplier-invoice-list-heading">Facturas registradas</h2><p>{{ $facturas->total() }} resultado{{ $facturas->total() === 1 ? '' : 's' }} con estos filtros</p></div>
        @if ($facturas->isNotEmpty())
            <div class="table-wrap table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--responsive supplier-invoice-table supplier-invoice-list-table">
                    <thead><tr><th>Documento / OC</th><th>Proveedor</th><th>Emisión</th><th>Vencimiento</th><th class="text-right">Importe</th><th class="text-right">Saldo</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($facturas as $factura)
                            @php($vencida = ! $factura->estaAnulada() && $factura->saldoPendiente() > 0 && $factura->fecha_vencimiento?->lt(today()))
                            <tr class="supplier-invoice-list-row">
                                <td class="supplier-invoice-list-row__document" data-label="Documento / OC"><a href="{{ route('facturas-proveedor.show', $factura) }}" class="table-primary-link" data-suggest-value="{{ $factura->numero }}">{{ $factura->tipo_documento }} {{ $factura->numeroVisible() }}</a><span>OC {{ $factura->ordenCompra?->codigo }}</span></td>
                                <td class="supplier-invoice-list-row__supplier" data-label="Proveedor"><strong>{{ $factura->proveedor?->nombreVisible() }}</strong><span>RUC {{ $factura->proveedor?->ruc }}</span></td>
                                <td data-label="Emisión">{{ $factura->fecha_emision?->format('d/m/Y') }}</td>
                                <td data-label="Vencimiento">{{ $factura->fecha_vencimiento?->format('d/m/Y') ?? 'Sin fecha' }} @if ($vencida)<x-ui.status-badge tone="danger">Vencida</x-ui.status-badge>@endif</td>
                                <td class="text-right supplier-invoice-list-row__total" data-label="Importe"><strong><x-ui.money :value="$factura->total" :currency="$factura->moneda" /></strong><span>{{ $factura->moneda }}</span></td>
                                <td class="text-right" data-label="Saldo"><x-ui.money :value="$factura->saldoPendiente()" :currency="$factura->moneda" /></td>
                                <td data-label="Estado"><span class="badge badge--{{ $factura->estadoClase() }}">{{ $factura->estadoVisible() }}</span></td>
                                <td class="supplier-invoice-list-row__action" data-label="Acción"><a href="{{ route('facturas-proveedor.show', $factura) }}" class="button button--ghost button--small">Ver factura</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$facturas" />
        @else
            <div class="empty-table-state"><span class="empty-state__icon"><x-ui.icon name="invoice" :size="30" /></span><strong>No hay facturas con estos filtros</strong><span>Las facturas aparecerán aquí cuando exista un comprobante registrado con estos filtros.</span></div>
        @endif
    </section>
    </div>
@endsection
