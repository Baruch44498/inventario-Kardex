@extends('layouts.app')

@section('title', 'Cuentas por cobrar')
@section('page-kicker', 'Contabilidad')
@section('page-title', 'Cuentas por cobrar')

@section('content')
    @php
        $monedasVisibles = collect($resumen)->filter(
            fn ($montos, $moneda) => (float) $montos['total'] != 0
                || (float) $montos['cobrado'] != 0
                || (float) $montos['saldo'] != 0
                || request('moneda') === $moneda
        );
    @endphp

    <div class="receivables-page">
        <x-ui.page-header
            class="receivables-header"
            kicker="Control de cobros"
            title="Cuentas por cobrar"
            description="Ventas directas y trabajos con orden principal cerrada. Cada versión aparece una sola vez."
        >
            <x-slot:actions>
                <a href="{{ route('cuentas-cobrar.csv', request()->only(['q', 'origen', 'estado', 'moneda', 'orden'])) }}"
                   class="button button--ghost" data-file-download>Descargar CSV (con filtros)</a>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="panel filter-panel receivables-panel receivables-filter-panel">
            <form method="GET" action="{{ route('cuentas-cobrar.index') }}" class="receivables-filters">
                <label class="form-field receivables-filters__search">
                    <span>Buscar</span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cotización, documento o cliente">
                </label>
                <label class="form-field">
                    <span>Origen</span>
                    <select name="origen">
                        <option value="">Todos</option>
                        <option value="VENTA_DIRECTA" @selected(request('origen') === 'VENTA_DIRECTA')>Venta directa</option>
                        <option value="ORDEN" @selected(request('origen') === 'ORDEN')>Orden cerrada</option>
                    </select>
                </label>
                <label class="form-field">
                    <span>Estado del cobro</span>
                    <select name="estado">
                        <option value="">Todos</option>
                        <option value="PENDIENTE" @selected(request('estado') === 'PENDIENTE')>Pendiente</option>
                        <option value="PARCIAL" @selected(request('estado') === 'PARCIAL')>Parcial</option>
                        <option value="COBRADA" @selected(request('estado') === 'COBRADA')>Cobrada</option>
                    </select>
                </label>
                <label class="form-field">
                    <span>Moneda</span>
                    <select name="moneda">
                        <option value="">Todas</option>
                        <option value="PEN" @selected(request('moneda') === 'PEN')>PEN</option>
                        <option value="USD" @selected(request('moneda') === 'USD')>USD</option>
                    </select>
                </label>
                <label class="form-field">
                    <span>Ordenar por</span>
                    <select name="orden">
                        <option value="emision_desc" @selected(request('orden', 'emision_desc') === 'emision_desc')>Emisión reciente</option>
                        <option value="emision_asc" @selected(request('orden') === 'emision_asc')>Emisión antigua</option>
                        <option value="total_desc" @selected(request('orden') === 'total_desc')>Mayor total</option>
                    </select>
                </label>
                <div class="receivables-filters__actions">
                    <button class="button button--primary" type="submit">Filtrar</button>
                    <a href="{{ route('cuentas-cobrar.index') }}" class="button button--ghost">Limpiar</a>
                </div>
            </form>
        </section>

        <section class="receivables-summary" aria-label="Resumen de cuentas por cobrar filtradas">
            <header class="receivables-summary__heading">
                <h2>Resumen económico</h2>
                <p>Según los filtros aplicados</p>
            </header>
            @forelse ($monedasVisibles as $moneda => $montos)
                <div class="receivables-summary__group">
                    <h3>{{ $moneda }}</h3>
                    <div class="receivables-summary__cards">
                        <article class="metric-card receivables-metric receivables-metric--balance">
                            <span>Por cobrar</span>
                            <strong><x-ui.money :value="$montos['saldo']" :currency="$moneda" /></strong>
                        </article>
                        <article class="metric-card receivables-metric">
                            <span>Cobrado</span>
                            <strong><x-ui.money :value="$montos['cobrado']" :currency="$moneda" /></strong>
                        </article>
                        <article class="metric-card receivables-metric">
                            <span>Total</span>
                            <strong><x-ui.money :value="$montos['total']" :currency="$moneda" /></strong>
                        </article>
                    </div>
                </div>
            @empty
                <p class="receivables-summary__empty">No hay importes para los filtros aplicados.</p>
            @endforelse
        </section>

        <section class="panel receivables-panel receivables-list-panel" aria-label="Cotizaciones por cobrar">
            @if ($cotizaciones->isNotEmpty())
                <div class="table-wrap table-wrap--responsive receivables-table-wrap" data-responsive-table>
                    <table class="data-table data-table--responsive receivables-table">
                        <thead>
                            <tr>
                                <th scope="col">Cotización</th>
                                <th scope="col">Cliente</th>
                                <th scope="col">Origen</th>
                                <th scope="col">Emisión</th>
                                <th scope="col" class="text-right">Total</th>
                                <th scope="col" class="text-right">Cobrado</th>
                                <th scope="col" class="text-right">Saldo</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cotizaciones as $cotizacion)
                                @php
                                    $cobrado = $cotizacion->montoCobrado();
                                    $saldo = $cotizacion->saldoPorCobrar();
                                    $avance = min(100, max(0, round($cobrado / max((float) $cotizacion->total, 0.0001) * 100)));
                                @endphp
                                <tr class="receivables-row" data-receivables-href="{{ route('cuentas-cobrar.show', $cotizacion) }}">
                                    <td class="receivables-row__code" data-label="Cotización">
                                        <a href="{{ route('cuentas-cobrar.show', $cotizacion) }}" class="table-primary-link">{{ $cotizacion->codigo }}</a>
                                    </td>
                                    <td class="receivables-row__client" data-label="Cliente">
                                        <strong>{{ $cotizacion->cliente_nombre }}</strong>
                                        <small>{{ $cotizacion->cliente_documento ?: 'No indicado' }}</small>
                                    </td>
                                    <td data-label="Origen">{{ $cotizacion->proforma_id ? 'Venta directa' : 'Orden '.$cotizacion->ordenOperacion?->codigo_orden }}</td>
                                    <td data-label="Emisión">{{ $cotizacion->fecha_emision?->format('d/m/Y') }}</td>
                                    <td class="text-right" data-label="Total"><x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" /></td>
                                    <td class="text-right" data-label="Cobrado"><x-ui.money :value="$cobrado" :currency="$cotizacion->moneda" /></td>
                                    <td class="text-right receivables-row__balance" data-label="Saldo">
                                        <strong><x-ui.money :value="$saldo" :currency="$cotizacion->moneda" /></strong>
                                        @if ($cobrado > 0 && $saldo > 0)
                                            <progress class="receivables-progress" value="{{ $avance }}" max="100" aria-label="{{ $avance }}% cobrado"></progress>
                                            <small>{{ $avance }}% cobrado</small>
                                        @endif
                                    </td>
                                    <td data-label="Estado">
                                        <x-ui.status-badge :tone="$saldo === 0.0 ? 'success' : ($cobrado > 0 ? 'warning' : 'info')">
                                            {{ $saldo === 0.0 ? 'Cobrado' : ($cobrado > 0 ? 'Cobro parcial' : 'Pendiente') }}
                                        </x-ui.status-badge>
                                    </td>
                                    <td class="receivables-row__action" data-label="Acción">
                                        @if (auth()->user()->puede('contabilidad.registrar_cobros') && $cotizacion->saldoPorCobrar() > 0)
                                            <a href="{{ route('cuentas-cobrar.show', $cotizacion) }}#nuevo-cobro" class="button button--ghost button--small" aria-label="Registrar cobro de {{ $cotizacion->codigo }}">Registrar cobro</a>
                                        @else
                                            <a href="{{ route('cuentas-cobrar.show', $cotizacion) }}" class="button button--ghost button--small" aria-label="Ver cobros de {{ $cotizacion->codigo }}">Ver cobros</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-ui.pagination :paginator="$cotizaciones" />
            @else
                <div class="empty-table-state">
                    <strong>No hay ventas terminadas con estos filtros</strong>
                    <span>La venta directa aparece al cerrar su cotización; los trabajos al cerrar la orden principal.</span>
                </div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/cuentas-cobrar-index.js') }}" defer></script>
@endpush
