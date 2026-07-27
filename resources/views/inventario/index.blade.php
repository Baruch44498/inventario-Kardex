@extends('layouts.app')

@section('title', 'Inventario')
@section('page-kicker', 'Almacén')
@section('page-title', 'Inventario')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Control de existencias</p>
            <h1>Inventario por repisa</h1>
            <p>
                Consulta stock actual, límites y costo promedio. Las existencias
                solo cambian mediante entradas, salidas y reversas.
            </p>
        </div>

        <a href="{{ route('repisas.index') }}" class="button button--ghost">
            <x-ui.icon name="shelf" :size="18" />
            Administrar repisas
        </a>
    </section>

    <section class="summary-strip" aria-label="Resumen del inventario">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info">
                <x-ui.icon name="inventory" :size="22" />
            </span>
            <div>
                <span>Ubicaciones</span>
                <strong>{{ number_format((int) ($resumen->ubicaciones ?? 0)) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--warning">
                <x-ui.icon name="warning" :size="22" />
            </span>
            <div>
                <span>Bajo mínimo</span>
                <strong>{{ number_format((int) ($resumen->bajo_minimo ?? 0)) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--danger">
                <x-ui.icon name="box-off" :size="22" />
            </span>
            <div>
                <span>Sin stock</span>
                <strong>{{ number_format((int) ($resumen->sin_stock ?? 0)) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success">
                <x-ui.icon name="banknote" :size="22" />
            </span>
            <div>
                <span>Valor estimado</span>
                <strong>
                    S/ {{ number_format((float) ($resumen->valor_total ?? 0), 2, '.', ',') }}
                </strong>
            </div>
        </article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('inventario.index') }}" class="filter-grid filter-grid--inventory">
            <div class="form-field filter-grid__search">
                <label for="q">Buscar</label>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol">
                        <x-ui.icon name="search" :size="18" />
                    </span>
                    <input
                        id="q"
                        name="q"
                        type="search"
                        value="{{ request('q') }}"
                        placeholder="Producto, descripción o repisa"
                    >
                </div>
            </div>

            <div class="form-field">
                <label for="repisa">Repisa</label>
                <select id="repisa" name="repisa">
                    <option value="">Todas</option>
                    @foreach ($repisas as $repisa)
                        <option
                            value="{{ $repisa->id_repisa }}"
                            @selected((string) request('repisa') === (string) $repisa->id_repisa)
                        >
                            {{ $repisa->codigo }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-field">
                <label for="estado_stock">Estado del stock</label>
                <select id="estado_stock" name="estado_stock">
                    <option value="">Todos</option>
                    <option value="NORMAL" @selected(request('estado_stock') === 'NORMAL')>
                        Normal
                    </option>
                    <option value="BAJO_MINIMO" @selected(request('estado_stock') === 'BAJO_MINIMO')>
                        Bajo mínimo
                    </option>
                    <option value="SIN_STOCK" @selected(request('estado_stock') === 'SIN_STOCK')>
                        Sin stock
                    </option>
                    <option value="SOBRE_MAXIMO" @selected(request('estado_stock') === 'SOBRE_MAXIMO')>
                        Sobre máximo
                    </option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="filter" :size="17" />
                    Filtrar
                </button>

                <a href="{{ route('inventario.index') }}" class="button button--ghost">
                    Limpiar
                </a>
            </div>
        </form>
    </section>

    <section class="panel {{ $inventarios->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($inventarios->count() > 0)
        <div class="table-wrap table-wrap--wide table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--actions data-table--wide data-table--responsive}">
                <thead>
                    <tr>
                        <th class="table-sticky--start">Producto</th>
                        <th class="table-priority--medium">Repisa</th>
                        <th class="text-right">Stock actual</th>
                        <th class="text-right table-priority--medium">Mínimo</th>
                        <th class="text-right table-priority--low">Máximo</th>
                        <th class="text-right table-priority--low">Costo promedio</th>
                        <th class="text-right table-priority--low">Valor</th>
                        <th>Estado</th>
                        <th class="text-right table-sticky--end">Acción</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($inventarios as $item)
                        @php
                            $badge = match ($item->estado_stock) {
                                'SIN_STOCK' => 'danger',
                                'BAJO_MINIMO' => 'warning',
                                'SOBRE_MAXIMO' => 'info',
                                default => 'success',
                            };

                            $label = match ($item->estado_stock) {
                                'SIN_STOCK' => 'SIN STOCK',
                                'BAJO_MINIMO' => 'BAJO MÍNIMO',
                                'SOBRE_MAXIMO' => 'SOBRE MÁXIMO',
                                default => 'NORMAL',
                            };
                            $detailsId = 'inventario-detalles-' . $item->id_inventario;
                        @endphp

                        <tr>
                            <td class="table-sticky--start">
                                <a href="{{ route('productos.show', $item->id_producto) }}" class="table-primary-link">
                                    {{ $item->producto_codigo }}
                                </a>
                                <span>{{ $item->producto_descripcion }}</span>
                            </td>
                            <td class="table-priority--medium">
                                <span class="location-chip"><x-ui.icon name="shelf" :size="15" />{{ $item->repisa_codigo }}</span>
                            </td>
                            <td class="text-right">
                                <strong><x-ui.quantity :value="$item->stock_actual" /></strong>
                                <span>{{ $item->unidad_codigo }}</span>
                            </td>
                            <td class="text-right table-priority--medium"><x-ui.quantity :value="$item->stock_minimo" /></td>
                            <td class="text-right table-priority--low">
                                @if ($item->stock_maximo === null) — @else <x-ui.quantity :value="$item->stock_maximo" /> @endif
                            </td>
                            <td class="text-right table-priority--low">S/ {{ number_format((float) $item->costo_promedio_soles, 4, '.', ',') }}</td>
                            <td class="text-right table-priority--low">S/ {{ number_format((float) $item->valor_total, 2, '.', ',') }}</td>
                            <td><span class="badge badge--{{ $badge }}">{{ $label }}</span></td>
                            <td class="text-right table-sticky--end">
                                <div class="table-actions">
                                    <a href="{{ route('inventario.edit', $item->id_inventario) }}" class="icon-button" title="Editar límites" aria-label="Editar límites de {{ $item->producto_codigo }} en {{ $item->repisa_codigo }}">
                                        <x-ui.icon name="settings" :size="17" />
                                    </a>
                                    <x-ui.table-details-toggle :target="$detailsId" label="Ver más datos de {{ $item->producto_codigo }}" />
                                </div>
                            </td>
                        </tr>
                        <x-ui.table-row-details :id="$detailsId" :colspan="9">
                            <dl class="table-details-grid">
                                <div class="table-detail--medium"><dt>Repisa</dt><dd>{{ $item->repisa_codigo }}</dd></div>
                                <div class="table-detail--medium"><dt>Stock mínimo</dt><dd><x-ui.quantity :value="$item->stock_minimo" /></dd></div>
                                <div class="table-detail--low"><dt>Stock máximo</dt><dd>@if ($item->stock_maximo === null) — @else <x-ui.quantity :value="$item->stock_maximo" /> @endif</dd></div>
                                <div class="table-detail--low"><dt>Costo promedio</dt><dd>S/ {{ number_format((float) $item->costo_promedio_soles, 4, '.', ',') }}</dd></div>
                                <div class="table-detail--low"><dt>Valor</dt><dd>S/ {{ number_format((float) $item->valor_total, 2, '.', ',') }}</dd></div>
                            </dl>
                        </x-ui.table-row-details>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pagination :paginator="$inventarios" />
        @else
            @php
                $hayFiltros = request()->filled('q')
                    || request()->filled('repisa')
                    || request()->filled('estado_stock');
            @endphp
            <x-ui.empty-table
                icon="inventory"
                :title="$hayFiltros ? 'No hay coincidencias' : 'Aún no hay existencias'"
                :description="$hayFiltros ? 'Ajusta o limpia los filtros para consultar otros registros.' : 'Crea una repisa y registra una entrada para generar inventario.'"
                :action-url="$hayFiltros ? route('inventario.index') : route('repisas.create')"
                :action-label="$hayFiltros ? 'Limpiar filtros' : 'Registrar una repisa'"
                :action-icon="$hayFiltros ? 'close' : 'plus'"
                :secondary-url="$hayFiltros ? null : route('productos.index')"
                :secondary-label="$hayFiltros ? null : 'Ver productos'"
            />
        @endif
    </section>
@endsection
