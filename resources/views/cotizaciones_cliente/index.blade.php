@extends('layouts.app')

@section('title', 'Cotizaciones al cliente')
@section('page-kicker', 'Comercial y logística')
@section('page-title', 'Cotizaciones al cliente')

@section('content')
    <x-ui.page-header
        kicker="Logística"
        title="Cotizaciones al cliente"
        description="Consulta cotizaciones creadas desde una proforma de Almacén o directamente por Logística."
    >
        @if (auth()->user()->puede('proformas.cotizar'))
            <x-slot:actions>
                <a href="{{ route('cotizaciones-cliente.create') }}" class="button button--primary">
                    <x-ui.icon name="plus" :size="18" /> Nueva cotización
                </a>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <section class="summary-strip summary-strip--four">
        @foreach ([
            ['Abiertas', 'edit', 'info', $resumen['abiertas']],
            ['Cerradas', 'check-circle', 'success', $resumen['cerradas']],
            ['Convertidas en orden', 'orders', 'accent', $resumen['ordenes']],
            ['Anuladas', 'error', 'danger', $resumen['anuladas']],
        ] as [$titulo, $icono, $tono, $valor])
            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--{{ $tono }}">
                    <x-ui.icon :name="$icono" :size="21" />
                </span>
                <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
            </article>
        @endforeach
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('cotizaciones-cliente.index') }}" class="commercial-filter">
            <label class="form-field commercial-filter__search">
                <span>Buscar</span>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span>
                    <input type="search" name="q" value="{{ request('q') }}"
                        placeholder="Cotización, cliente, proforma u orden">
                </div>
            </label>
            <label class="form-field">
                <span>Origen</span>
                <select name="origen">
                    <option value="">Todos</option>
                    <option value="PROFORMA_ALMACEN" @selected(request('origen') === 'PROFORMA_ALMACEN')>Proforma de Almacén</option>
                    <option value="DIRECTA_LOGISTICA" @selected(request('origen') === 'DIRECTA_LOGISTICA')>Directa de Logística</option>
                </select>
            </label>
            <label class="form-field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    @foreach (App\Models\CotizacionCliente::ESTADOS as $estado)
                        <option value="{{ $estado }}" @selected(request('estado') === $estado)>
                            {{ str_replace('_', ' ', $estado) }}
                        </option>
                    @endforeach
                </select>
            </label>
            <div class="filter-actions">
                <button class="button button--primary" type="submit">
                    <x-ui.icon name="filter" :size="17" /> Filtrar
                </button>
                <a href="{{ route('cotizaciones-cliente.index') }}" class="button button--ghost">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="panel {{ $cotizaciones->isEmpty() ? 'panel--empty-list' : '' }}">
        @if ($cotizaciones->isNotEmpty())
            <div class="table-wrap table-wrap--responsive table-wrap--cards">
                <table class="data-table data-table--actions quote-list-table data-table--cards" role="table">
                    <thead role="rowgroup">
                        <tr role="row">
                            <th role="columnheader" scope="col">Cotización</th>
                            <th role="columnheader" scope="col">Cliente</th>
                            <th role="columnheader" scope="col">Líneas</th>
                            <th role="columnheader" scope="col">Total</th>
                            <th role="columnheader" scope="col">Estado</th>
                            <th role="columnheader" scope="col">Orden</th>
                            <th role="columnheader" scope="col">Acción</th>
                        </tr>
                    </thead>
                    <tbody role="rowgroup">
                        @foreach ($cotizaciones as $cotizacion)
                            <tr role="row">
                                <td class="table-code-cell table-card__wide" role="cell" data-label="Cotización">
                                    <a class="table-primary-link" href="{{ route('cotizaciones-cliente.show', $cotizacion) }}">
                                        {{ $cotizacion->codigo }}
                                    </a>
                                    <span>{{ $cotizacion->fecha_emision?->format('d/m/Y') }}</span>
                                </td>
                                <td class="table-card__wide" role="cell" data-label="Cliente"><strong>{{ $cotizacion->cliente_nombre }}</strong><span>{{ $cotizacion->cliente_documento ?: 'Sin documento' }}</span></td>
                                <td class="quote-list-table__products" role="cell" data-label="Líneas">{{ $cotizacion->detalles_count }}</td>
                                <td class="table-money-cell" role="cell" data-label="Total">
                                    <strong>
                                        <x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" />
                                    </strong>
                                </td>
                                <td class="quote-list-table__status" role="cell" data-label="Estado">
                                    <x-ui.status-badge :tone="$cotizacion->tonoEstadoVisual()">
                                        {{ $cotizacion->estadoVisual() }}
                                    </x-ui.status-badge>
                                </td>
                                <td class="quote-list-table__order" role="cell" data-label="Orden">
                                    @if ($cotizacion->ordenesOperacion->isNotEmpty())
                                        @if (auth()->user()->puede(App\Support\PermisoSistema::ORDENES_VER))
                                            @foreach ($cotizacion->ordenesOperacion as $ordenVinculada)
                                                <a
                                                    class="quote-order-link"
                                                    href="{{ route('ordenes-operacion.show', $ordenVinculada) }}"
                                                    aria-label="Ver orden {{ $ordenVinculada->codigo_orden }}"
                                                >{{ $ordenVinculada->codigo_orden }}</a>
                                            @endforeach
                                        @else
                                            <span class="quote-order-code">
                                                {{ $cotizacion->ordenesOperacion->pluck('codigo_orden')->implode(', ') }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="quote-order-empty" aria-label="Sin orden asociada">—</span>
                                    @endif
                                </td>
                                <td class="quote-list-table__action table-card__wide" role="cell" data-label="Acción">
                                    <a href="{{ route('cotizaciones-cliente.show', $cotizacion) }}" class="button button--ghost button--small">
                                        Ver detalle
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$cotizaciones" />
        @else
            <div class="empty-table-state">
                <span class="empty-state__icon"><x-ui.icon name="quotes" :size="30" /></span>
                <strong>No hay cotizaciones para mostrar</strong>
                <span>Logística puede crear una cotización directa o atender una proforma de Almacén.</span>
            </div>
        @endif
    </section>
@endsection
