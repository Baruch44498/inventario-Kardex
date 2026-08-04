@extends('layouts.app')

@section('title', 'Cotizaciones de proveedores')
@section('page-kicker', 'Compras')
@section('page-title', 'Cotizaciones de proveedores')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Ofertas recibidas</p>
            <h1>Cotizaciones de proveedores</h1>
            <p>Registra los precios ofrecidos aunque luego no se seleccione al proveedor.</p>
        </div>

        <div class="module-header__actions">
            <a href="{{ route('historial-precios.index') }}" class="button button--ghost">
                <x-ui.icon name="banknote" :size="17" /> Historial de precios
            </a>
            <a href="{{ route('cotizaciones-proveedor.create') }}" class="button button--primary">
                <x-ui.icon name="plus" :size="18" /> Nueva cotización
            </a>
        </div>
    </section>

    <section class="summary-strip summary-strip--four">
        @foreach ([
            ['Total', 'quotes', 'neutral', $resumen['total']],
            ['Vigentes', 'check-circle', 'success', $resumen['vigentes']],
            ['Este mes', 'clipboard', 'info', $resumen['este_mes']],
            ['Proveedores', 'suppliers', 'warning', $resumen['proveedores']],
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
        <form method="GET" action="{{ route('cotizaciones-proveedor.index') }}"
            class="supplier-quote-filter">
            <label class="form-field supplier-quote-filter__search">
                <span>Buscar</span>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span>
                    <input type="search" name="q" value="{{ request('q') }}"
                        placeholder="Código, documento, proveedor o RUC">
                </div>
            </label>

            <div class="form-field">
                <label for="filtro_proveedor_busqueda">Proveedor</label>
                <x-ui.remote-combobox
                    name="proveedor_id"
                    search-id="filtro_proveedor_busqueda"
                    value-id="filtro_proveedor_id"
                    :search-url="route('catalogos.proveedores.buscar', ['todos' => 1])"
                    :selected-id="$proveedorFiltro?->id"
                    :selected-label="$proveedorFiltro
                        ? $proveedorFiltro->ruc.' — '.$proveedorFiltro->nombreVisible()
                        : ''"
                    placeholder="RUC o razón social"
                    empty-text="No se encontró el proveedor."
                />
            </div>

            <label class="form-field">
                <span>Moneda</span>
                <select name="moneda">
                    <option value="">Todas</option>
                    <option value="PEN" @selected(request('moneda') === 'PEN')>PEN</option>
                    <option value="USD" @selected(request('moneda') === 'USD')>USD</option>
                </select>
            </label>

            <label class="form-field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    @foreach (['REGISTRADA', 'SELECCIONADA', 'ANULADA'] as $estado)
                        <option value="{{ $estado }}" @selected(request('estado') === $estado)>
                            {{ $estado === 'ANULADA' ? 'INVALIDADA' : $estado }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="form-field">
                <span>Desde</span>
                <input type="date" name="desde" value="{{ request('desde') }}">
            </label>

            <label class="form-field">
                <span>Hasta</span>
                <input type="date" name="hasta" value="{{ request('hasta') }}">
            </label>

            <div class="filter-actions">
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="filter" :size="17" /> Filtrar
                </button>
                <a href="{{ route('cotizaciones-proveedor.index') }}" class="button button--ghost">
                    Limpiar
                </a>
            </div>
        </form>
    </section>

    <section class="panel {{ $cotizaciones->isEmpty() ? 'panel--empty-list' : '' }}">
        @if ($cotizaciones->isNotEmpty())
            <div class="table-wrap table-wrap--responsive">
                <table class="data-table data-table--actions supplier-quote-table">
                    <thead>
                        <tr>
                            <th>Cotización</th>
                            <th>Proveedor</th>
                            <th>Fecha</th>
                            <th>Moneda</th>
                            <th>Productos</th>
                            <th class="text-right">Subtotal</th>
                            <th class="text-right">Total</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cotizaciones as $cotizacion)
                            <tr>
                                <td>
                                    <a href="{{ route('cotizaciones-proveedor.show', $cotizacion->id) }}"
                                        class="table-primary-link">{{ $cotizacion->codigo }}</a>
                                    <span>{{ $cotizacion->numero_documento ?: 'Sin número externo' }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('proveedores.show', $cotizacion->proveedor_id) }}"
                                        class="table-primary-link">
                                        {{ $cotizacion->proveedor?->nombreVisible() }}
                                    </a>
                                    <span>RUC {{ $cotizacion->proveedor?->ruc }}</span>
                                </td>
                                <td>{{ $cotizacion->fecha_cotizacion->format('d/m/Y') }}</td>
                                <td><span class="currency-chip">{{ $cotizacion->moneda }}</span></td>
                                <td>{{ $cotizacion->detalles_count }}</td>
                                <td class="text-right">
                                    {{ $cotizacion->simboloMoneda() }}
                                    {{ number_format((float) $cotizacion->subtotal, 2) }}
                                </td>
                                <td class="text-right">
                                    <strong>
                                        {{ $cotizacion->simboloMoneda() }}
                                        {{ number_format((float) $cotizacion->total, 2) }}
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge badge--{{ $cotizacion->estado === 'ANULADA' ? 'danger' : ($cotizacion->estado === 'SELECCIONADA' ? 'success' : 'info') }}">
                                        {{ $cotizacion->estado === 'ANULADA' ? 'INVALIDADA' : $cotizacion->estado }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('cotizaciones-proveedor.show', $cotizacion->id) }}"
                                        class="button button--ghost button--small">Ver</a>
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
                <strong>No se encontraron cotizaciones</strong>
                <span>Ajusta los filtros o registra la primera oferta.</span>
                <div class="empty-table-state__actions">
                    <a href="{{ route('cotizaciones-proveedor.create') }}"
                        class="button button--primary button--small">
                        <x-ui.icon name="plus" :size="16" /> Nueva cotización
                    </a>
                </div>
            </div>
        @endif
    </section>
@endsection
