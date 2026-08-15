@extends('layouts.app')

@section('title', 'Movimientos de inventario')
@section('page-kicker', 'Almacén')
@section('page-title', 'Movimientos')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Trazabilidad de inventario</p>
            <h1>Movimientos</h1>
            <p>
                Consulta las entradas, salidas y reversiones que modificaron
                las existencias. Este módulo es de solo lectura.
            </p>
        </div>

        <a href="{{ route('inventario.index') }}" class="button button--ghost">
            <x-ui.icon name="inventory" :size="18" />
            Ver inventario
        </a>
    </section>

    <section class="summary-strip" aria-label="Resumen de movimientos del día">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info">
                <x-ui.icon name="activity" :size="20" />
            </span>
            <div>
                <span>Movimientos hoy</span>
                <strong>{{ number_format((int) ($resumen->total ?? 0)) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success">
                <x-ui.icon name="entry" :size="20" />
            </span>
            <div>
                <span>Entradas hoy</span>
                <strong>{{ number_format((int) ($resumen->entradas ?? 0)) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--danger">
                <x-ui.icon name="exit" :size="20" />
            </span>
            <div>
                <span>Salidas hoy</span>
                <strong>{{ number_format((int) ($resumen->salidas ?? 0)) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--warning">
                <x-ui.icon name="refresh" :size="20" />
            </span>
            <div>
                <span>Reversiones hoy</span>
                <strong>{{ number_format((int) ($resumen->reversiones ?? 0)) }}</strong>
            </div>
        </article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('movimientos.index') }}" class="filter-grid filter-grid--movements">
            <label class="form-field filter-grid__search">
                <span>Buscar</span>
                <span class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="18" /></span>
                    <input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Producto, descripción, repisa u origen"
                    >
                </span>
            </label>

            <label class="form-field">
                <span>Tipo</span>
                <select name="tipo">
                    <option value="">Todos</option>
                    <option value="ENTRADA" @selected(request('tipo') === 'ENTRADA')>
                        Entrada
                    </option>
                    <option value="SALIDA" @selected(request('tipo') === 'SALIDA')>
                        Salida
                    </option>
                    <option value="AJUSTE_COSTO" @selected(request('tipo') === 'AJUSTE_COSTO')>
                        Ajuste de costo
                    </option>
                </select>
            </label>

            <label class="form-field">
                <span>Motivo</span>
                <select name="motivo">
                    <option value="">Todos</option>
                    @foreach ($motivos as $motivo)
                        <option value="{{ $motivo }}" @selected(request('motivo') === $motivo)>
                            {{ str($motivo)->replace('_', ' ')->title() }}
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
                    <x-ui.icon name="filter" :size="17" />
                    Filtrar
                </button>

                <a href="{{ route('movimientos.index') }}" class="button button--ghost">
                    Limpiar
                </a>
            </div>
        </form>
    </section>

    <section class="panel {{ $movimientos->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($movimientos->count() > 0)
        <div class="table-wrap table-wrap--wide table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--actions data-table--movements data-table--responsive}">
                <thead>
                    <tr>
                        <th class="table-sticky--start">Fecha</th>
                        <th>Producto</th>
                        <th class="table-priority--medium">Repisa</th>
                        <th>Tipo</th>
                        <th class="text-right">Cantidad</th>
                        <th class="table-priority--medium">Stock</th>
                        <th class="table-priority--low">Origen</th>
                        <th class="table-priority--low">Usuario</th>
                        <th class="table-sticky--end">Acción</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($movimientos as $movimiento)
                        @php
                            $esEntrada = $movimiento->tipo_movimiento === 'ENTRADA';
                            $esAjusteCosto = $movimiento->tipo_movimiento === 'AJUSTE_COSTO';
                            $origen = str($movimiento->origen_tipo)->replace('_', ' ')->title();
                            $detailsId = 'movimiento-detalles-' . $movimiento->id;
                        @endphp

                        <tr>
                            <td class="table-date table-sticky--start">
                                <strong>{{ \Illuminate\Support\Carbon::parse($movimiento->fecha_movimiento)->format('d/m/Y') }}</strong>
                                <span>{{ \Illuminate\Support\Carbon::parse($movimiento->fecha_movimiento)->format('H:i') }}</span>
                            </td>
                            <td>
                                <a href="{{ route('productos.show', $movimiento->producto_id) }}" class="table-primary-link">{{ $movimiento->producto_codigo }}</a>
                                <span>{{ $movimiento->producto_descripcion }}</span>
                            </td>
                            <td class="table-priority--medium"><span class="location-chip"><x-ui.icon name="shelf" :size="14" />{{ $movimiento->repisa_codigo }}</span></td>
                            <td>
                                <span class="badge badge--{{ $esAjusteCosto ? 'info' : ($esEntrada ? 'success' : 'danger') }}">{{ $esAjusteCosto ? 'AJUSTE DE COSTO' : ($esEntrada ? 'ENTRADA' : 'SALIDA') }}</span>
                                <span>{{ str($movimiento->motivo)->replace('_', ' ')->title() }}</span>
                            </td>
                            <td class="text-right movement-quantity movement-quantity--{{ $esEntrada ? 'in' : 'out' }}">{{ $esAjusteCosto ? '—' : ($esEntrada ? '+' : '−') }}<x-ui.quantity :value="$movimiento->cantidad" /></td>
                            <td class="table-priority--medium"><span class="stock-flow"><x-ui.quantity :value="$movimiento->stock_anterior" /><x-ui.icon name="arrow-right" :size="14" /><strong><x-ui.quantity :value="$movimiento->stock_posterior" /></strong></span></td>
                            <td class="table-priority--low"><span class="origin-chip">{{ $origen }}</span><span>#{{ $movimiento->origen_id }}</span></td>
                            <td class="table-priority--low">{{ $movimiento->usuario }}</td>
                            <td class="table-sticky--end">
                                <div class="table-actions">
                                    <a href="{{ route('movimientos.show', $movimiento->id) }}" class="icon-button" title="Ver movimiento" aria-label="Ver movimiento"><x-ui.icon name="eye" :size="17" /></a>
                                    <x-ui.table-details-toggle :target="$detailsId" label="Ver más datos del movimiento" />
                                </div>
                            </td>
                        </tr>
                        <x-ui.table-row-details :id="$detailsId" :colspan="9">
                            <dl class="table-details-grid">
                                <div class="table-detail--medium"><dt>Repisa</dt><dd>{{ $movimiento->repisa_codigo }}</dd></div>
                                <div class="table-detail--medium"><dt>Flujo de stock</dt><dd><x-ui.quantity :value="$movimiento->stock_anterior" /> → <x-ui.quantity :value="$movimiento->stock_posterior" /></dd></div>
                                <div class="table-detail--low"><dt>Origen</dt><dd>{{ $origen }} #{{ $movimiento->origen_id }}</dd></div>
                                <div class="table-detail--low"><dt>Usuario</dt><dd>{{ $movimiento->usuario }}</dd></div>
                            </dl>
                        </x-ui.table-row-details>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pagination :paginator="$movimientos" />
        @else
            <x-ui.empty-table
                icon="movements"
                title="Aún no hay movimientos"
                description="Las entradas, salidas y reversiones aparecerán aquí cuando se confirmen operaciones de almacén."
                :action-url="route('inventario.index')"
                action-label="Ver inventario"
                action-icon="inventory"
            />
        @endif
    </section>
@endsection
