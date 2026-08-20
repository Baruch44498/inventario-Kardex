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
            <div class="table-wrap table-wrap--responsive">
                <table class="data-table data-table--actions quote-list-table">
                    <thead>
                        <tr>
                            <th scope="col">Cotización</th>
                            <th scope="col">Cliente</th>
                            <th scope="col">Líneas</th>
                            <th scope="col">Total</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Orden</th>
                            <th scope="col">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cotizaciones as $cotizacion)
                            <tr>
                                <td class="table-code-cell">
                                    <a class="table-primary-link" href="{{ route('cotizaciones-cliente.show', $cotizacion) }}">
                                        {{ $cotizacion->codigo }}
                                    </a>
                                    <span>{{ $cotizacion->fecha_emision?->format('d/m/Y') }}</span>
                                </td>
                                <td><strong>{{ $cotizacion->cliente_nombre }}</strong><span>{{ $cotizacion->cliente_documento ?: 'Sin documento' }}</span></td>
                                <td class="quote-list-table__products">{{ $cotizacion->detalles_count }}</td>
                                <td class="table-money-cell">
                                    <strong>
                                        <x-ui.money :value="$cotizacion->total" :currency="$cotizacion->moneda" />
                                    </strong>
                                </td>
                                <td class="quote-list-table__status">
                                    <x-ui.status-badge :tone="$cotizacion->tonoEstadoVisual()">
                                        {{ $cotizacion->estadoVisual() }}
                                    </x-ui.status-badge>
                                </td>
                                <td class="quote-list-table__order">
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
                                <td class="quote-list-table__action">
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
