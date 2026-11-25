@extends('layouts.app')

@section('title', 'Cuentas por pagar')
@section('page-kicker', 'Contabilidad')
@section('page-title', 'Cuentas por pagar')

@section('content')
    @php
        $puedeRegistrarPago = auth()->user()->puede('contabilidad.registrar_pagos');
        $monedasVisibles = collect($resumen)->filter(
            fn ($saldos, $moneda) => array_sum($saldos) > 0 || request('moneda') === $moneda
        );
        $grupos = [
            ['vencido', 'Vencido', 'VENCIDA', 'danger', 'Pago atrasado'],
            ['proximo', 'Próximos 7 días', 'POR_VENCER', 'warning', 'Pago próximo'],
            ['posterior', 'Posterior', null, null, 'Después de 7 días'],
            ['sin_fecha', 'Sin fecha', 'SIN_VENCIMIENTO', null, 'Sin vencimiento registrado'],
        ];
    @endphp

    <div class="payables-page">
        <x-ui.page-header
            class="payables-header"
            kicker="Contabilidad"
            title="Cuentas por pagar"
            description="Saldo y vencimiento de cada factura. Los pagos se registran en la moneda del documento."
        >
            <x-slot:actions>
                <a href="{{ route('cuentas-pagar.csv', request()->only(['q', 'estado', 'moneda', 'orden'])) }}"
                   class="button button--ghost" data-file-download>Descargar CSV (con filtros)</a>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="panel payables-panel payables-filter-panel">
            <form method="GET" action="{{ route('cuentas-pagar.index') }}" class="payables-filters">
                <label class="form-field payables-filters__search">
                    <span>Buscar</span>
                    <span class="payables-filters__search-control"><x-ui.icon name="search" :size="17" /><input type="search" name="q" value="{{ request('q') }}" placeholder="Documento, RUC o proveedor"></span>
                </label>
                <label class="form-field"><span>Estado / vencimiento</span><select name="estado">
                    <option value="">Todos</option>
                    @foreach (['PENDIENTE' => 'Pendiente', 'PARCIAL' => 'Pago parcial', 'PAGADA' => 'Pagada', 'VENCIDA' => 'Vencida', 'POR_VENCER' => 'Próximos 7 días', 'SIN_VENCIMIENTO' => 'Sin vencimiento'] as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select></label>
                <label class="form-field"><span>Moneda</span><select name="moneda"><option value="">Todas</option><option value="PEN" @selected(request('moneda') === 'PEN')>PEN</option><option value="USD" @selected(request('moneda') === 'USD')>USD</option></select></label>
                <label class="form-field"><span>Ordenar por</span><select name="orden">
                    <option value="vencimiento" @selected(request('orden', 'vencimiento') === 'vencimiento')>Vencimiento</option>
                    <option value="saldo_desc" @selected(request('orden') === 'saldo_desc')>Mayor saldo</option>
                    <option value="proveedor" @selected(request('orden') === 'proveedor')>Proveedor</option>
                </select></label>
                <div class="payables-filters__actions"><button class="button button--primary" type="submit">Filtrar</button><a class="button button--ghost" href="{{ route('cuentas-pagar.index') }}">Limpiar</a></div>
            </form>
        </section>

        <section class="payables-summary" aria-label="Saldos pendientes por vencimiento">
            <header class="payables-summary__heading"><h2>Saldos pendientes por vencimiento</h2><p>Estos saldos incluyen solo importes por pagar; el listado puede mostrar facturas pagadas según el filtro.</p></header>
            @forelse ($monedasVisibles as $moneda => $saldos)
                <div class="payables-summary__group">
                    <h3>{{ $moneda }}</h3>
                    <div class="payables-summary__cards">
                        @foreach ($grupos as [$grupo, $etiqueta, $filtro, $tono, $ayuda])
                            @php
                                $importe = $saldos[$grupo];
                                $clase = 'metric-card metric-card--summary'.($tono ? ' metric-card--'.$tono : '');
                            @endphp
                            @if ($importe > 0 && $filtro)
                                <a href="{{ route('cuentas-pagar.index', array_merge(request()->except('page', 'estado'), ['estado' => $filtro, 'moneda' => $moneda])) }}"
                                   class="{{ $clase }}" @if (request('estado') === $filtro && request('moneda') === $moneda) aria-current="page" @endif>
                                    <span>{{ $etiqueta }}</span><strong><x-ui.money :value="$importe" :currency="$moneda" /></strong><small>{{ $ayuda }} · Ver facturas</small>
                                </a>
                            @else
                                <article class="{{ $clase }}"><span>{{ $etiqueta }}</span><strong><x-ui.money :value="$importe" :currency="$moneda" /></strong><small>{{ $ayuda }}</small></article>
                            @endif
                        @endforeach
                        @if (array_sum($saldos) > 0)
                            <a href="{{ route('cuentas-pagar.index', array_merge(request()->except('page'), ['moneda' => $moneda])) }}#facturas-por-pagar"
                               class="metric-card metric-card--summary metric-card--highlight"
                               @if (request('moneda') === $moneda) aria-current="page" @endif>
                                <span>Total por pagar</span><strong><x-ui.money :value="array_sum($saldos)" :currency="$moneda" /></strong><small>Ver la lista filtrada de {{ $moneda }}</small>
                            </a>
                        @else
                            <article class="metric-card metric-card--summary metric-card--highlight"><span>Total por pagar</span><strong><x-ui.money :value="array_sum($saldos)" :currency="$moneda" /></strong></article>
                        @endif
                    </div>
                </div>
            @empty
                <p class="payables-summary__empty">No hay saldos pendientes para los filtros aplicados.</p>
            @endforelse
        </section>

        <section class="panel payables-panel payables-list-panel" id="facturas-por-pagar" aria-labelledby="payables-list-heading">
            <header class="payables-summary__heading"><h2 id="payables-list-heading">Facturas por pagar</h2><p>{{ $facturas->total() }} resultado{{ $facturas->total() === 1 ? '' : 's' }} con estos filtros</p></header>
            @if ($facturas->isNotEmpty())
                <div class="table-wrap table-wrap--responsive payables-table-wrap" data-responsive-table>
                    <table class="data-table data-table--responsive payables-table">
                        <thead><tr><th>Documento</th><th>Proveedor</th><th>Emisión</th><th>Vencimiento</th><th class="text-right">Total</th><th class="text-right">Pagado</th><th class="text-right">Saldo</th><th>Estado</th><th>Acción</th></tr></thead>
                        <tbody>
                        @foreach ($facturas as $factura)
                            @php
                                $saldo = $factura->saldoPendiente();
                                $pagado = $factura->montoPagado();
                                $vencida = $saldo > 0 && $factura->fecha_vencimiento?->isBefore(today());
                                $avance = (float) $factura->total > 0 ? min(100, max(0, round($pagado / (float) $factura->total * 100))) : 0;
                            @endphp
                            <tr class="payables-row">
                                <td class="payables-row__document" data-label="Documento"><a class="table-primary-link" href="{{ route('facturas-proveedor.show', $factura) }}">{{ $factura->tipo_documento }} {{ $factura->numeroVisible() }}</a><small>OC {{ $factura->ordenCompra?->codigo }}</small></td>
                                <td class="payables-row__supplier" data-label="Proveedor"><strong>{{ $factura->proveedor?->nombreVisible() }}</strong><small>RUC {{ $factura->proveedor?->ruc }}</small></td>
                                <td data-label="Emisión">{{ $factura->fecha_emision?->format('d/m/Y') }}</td>
                                <td data-label="Vencimiento">
                                    {{ $factura->fecha_vencimiento?->format('d/m/Y') ?? 'Sin fecha' }}
                                    @if ($vencida)
                                        <small class="payables-row__deadline payables-row__deadline--danger">Vencida hace {{ (int) $factura->fecha_vencimiento->diffInDays(today()) }} días</small>
                                    @elseif ($saldo > 0 && $factura->fecha_vencimiento)
                                        <small class="payables-row__deadline">{{ $factura->fecha_vencimiento->isSameDay(today()) ? 'Vence hoy' : 'Vence en '.(int) today()->diffInDays($factura->fecha_vencimiento).' días' }}</small>
                                    @endif
                                </td>
                                <td class="text-right" data-label="Total"><x-ui.money :value="$factura->total" :currency="$factura->moneda" /></td>
                                <td class="text-right" data-label="Pagado"><x-ui.money :value="$pagado" :currency="$factura->moneda" /></td>
                                <td class="text-right payables-row__balance" data-label="Saldo"><strong><x-ui.money :value="$saldo" :currency="$factura->moneda" /></strong>
                                    @if ($pagado > 0 && $saldo > 0)
                                        <progress class="progress-bar" value="{{ $avance }}" max="100" aria-label="{{ $avance }}% pagado"></progress><small>{{ $avance }}% pagado</small>
                                    @endif
                                </td>
                                <td data-label="Estado"><x-ui.status-badge :tone="$vencida ? 'danger' : $factura->estadoClase()">{{ $vencida ? 'Vencida' : $factura->estadoVisible() }}</x-ui.status-badge></td>
                                <td class="payables-row__action" data-label="Acción">
                                    @if ($puedeRegistrarPago && $saldo > 0 && ! $factura->estaAnulada())
                                        <a href="{{ route('facturas-proveedor.show', $factura) }}#pagos-factura" class="button button--primary button--small" aria-label="Registrar pago de {{ $factura->numeroVisible() }}">Registrar pago</a>
                                    @else
                                        <a href="{{ route('facturas-proveedor.show', $factura) }}" class="button button--ghost button--small" aria-label="Ver factura {{ $factura->numeroVisible() }}">Ver factura</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <x-ui.pagination :paginator="$facturas" />
            @else
                <div class="empty-table-state"><strong>No hay facturas con estos filtros</strong><span>Las facturas registradas desde Compras aparecerán aquí.</span></div>
            @endif
        </section>
    </div>
@endsection
