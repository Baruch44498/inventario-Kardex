@extends('layouts.app')

@section('title', 'Productos')
@section('page-kicker', 'Almacén')
@section('page-title', 'Productos')

@section('content')
    @php
        $hayFiltros = request()->filled('q')
            || request()->filled('estado')
            || request()->filled('unidad')
            || request()->filled('marca');
    @endphp

    <section class="module-header">
        <div>
            <p class="eyebrow">Catálogo maestro</p>
            <h1>Productos</h1>
            <p>
                Administra códigos, descripciones, unidades y marcas sin
                modificar directamente el stock.
            </p>
        </div>

        <a href="{{ route('productos.create') }}" class="button button--primary">
            <x-ui.icon name="plus" :size="18" />
            Nuevo producto
        </a>
    </section>

    <section class="summary-strip summary-strip--three" aria-label="Resumen de productos">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info">
                <x-ui.icon name="products" :size="22" />
            </span>
            <div>
                <span>Total</span>
                <strong>{{ number_format((int) ($resumen->total ?? 0)) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success">
                <x-ui.icon name="check-circle" :size="22" />
            </span>
            <div>
                <span>Activos</span>
                <strong>{{ number_format((int) ($resumen->activos ?? 0)) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--neutral">
                <x-ui.icon name="power" :size="22" />
            </span>
            <div>
                <span>Inactivos</span>
                <strong>{{ number_format((int) ($resumen->inactivos ?? 0)) }}</strong>
            </div>
        </article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('productos.index') }}" class="filter-grid filter-grid--products">
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
                        placeholder="Código, descripción o marca"
                    >
                </div>
            </div>

            <div class="form-field">
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
                    <option value="">Todos</option>
                    <option value="activo" @selected(request('estado') === 'activo')>Activos</option>
                    <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivos</option>
                </select>
            </div>

            <div class="form-field">
                <label for="unidad">Unidad</label>
                <select id="unidad" name="unidad">
                    <option value="">Todas</option>
                    @foreach ($unidades as $unidad)
                        <option
                            value="{{ $unidad->id_unidad_medida }}"
                            @selected((string) request('unidad') === (string) $unidad->id_unidad_medida)
                        >
                            {{ $unidad->codigo }} · {{ $unidad->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-field">
                <label for="marca_busqueda">Marca</label>
                <x-ui.remote-combobox
                    name="marca"
                    search-id="marca_busqueda"
                    value-id="marca"
                    :search-url="route('catalogos.marcas.buscar', ['todos' => 1])"
                    :selected-id="$marcaFiltro?->id_marca"
                    :selected-label="$marcaFiltro?->nombre ?? ''"
                    placeholder="Nombre de marca"
                    empty-text="No se encontró la marca."
                />
            </div>

            <div class="filter-actions">
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="filter" :size="17" />
                    Filtrar
                </button>

                <a href="{{ route('productos.index') }}" class="button button--ghost">
                    Limpiar
                </a>
            </div>
        </form>
    </section>

    <section class="panel {{ $productos->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($productos->count() > 0)
        <div class="table-wrap table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--actions data-table--responsive}">
                <thead>
                    <tr>
                        <th class="table-sticky--start">Código</th>
                        <th>Descripción</th>
                        <th class="table-priority--medium">Unidad</th>
                        <th class="table-priority--low">Marca</th>
                        <th class="text-right">Stock total</th>
                        <th>Estado</th>
                        <th class="text-right table-sticky--end">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($productos as $producto)
                        @php($detailsId = 'producto-detalles-' . $producto->id_producto)
                        <tr>
                            <td class="table-sticky--start">
                                <a href="{{ route('productos.show', $producto->id_producto) }}" class="table-primary-link">
                                    {{ $producto->codigo }}
                                </a>
                            </td>
                            <td>
                                <strong>{{ $producto->descripcion }}</strong>
                                <span>
                                    {{ $producto->cantidad_repisas }}
                                    {{ (int) $producto->cantidad_repisas === 1 ? 'repisa' : 'repisas' }}
                                </span>
                            </td>
                            <td class="table-priority--medium"><span class="badge badge--neutral">{{ $producto->unidad_codigo }}</span></td>
                            <td class="table-priority--low">{{ $producto->marca_nombre ?? 'Sin marca' }}</td>
                            <td class="text-right"><strong><x-ui.quantity :value="$producto->stock_total" /></strong></td>
                            <td>
                                <span class="badge badge--{{ $producto->activo ? 'success' : 'neutral' }}">
                                    {{ $producto->activo ? 'ACTIVO' : 'INACTIVO' }}
                                </span>
                            </td>
                            <td class="text-right table-sticky--end">
                                <div class="table-actions">
                                    <a href="{{ route('productos.show', $producto->id_producto) }}" class="icon-button" title="Ver detalle" aria-label="Ver detalle de {{ $producto->codigo }}">
                                        <x-ui.icon name="eye" :size="17" />
                                    </a>
                                    <a href="{{ route('productos.edit', $producto->id_producto) }}" class="icon-button" title="Editar" aria-label="Editar {{ $producto->codigo }}">
                                        <x-ui.icon name="edit" :size="17" />
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('productos.toggle', $producto->id_producto) }}"
                                        data-confirm="{{ $producto->activo ? '¿Desactivar este producto? No se eliminará su historial.' : '¿Activar nuevamente este producto?' }}"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="icon-button icon-button--{{ $producto->activo ? 'danger' : 'success' }}"
                                            title="{{ $producto->activo ? 'Desactivar' : 'Activar' }}"
                                            aria-label="{{ $producto->activo ? 'Desactivar' : 'Activar' }} {{ $producto->codigo }}"
                                        >
                                            <x-ui.icon name="power" :size="17" />
                                        </button>
                                    </form>
                                    <x-ui.table-details-toggle :target="$detailsId" label="Ver más datos del producto {{ $producto->codigo }}" />
                                </div>
                            </td>
                        </tr>
                        <x-ui.table-row-details :id="$detailsId" :colspan="7">
                            <dl class="table-details-grid">
                                <div class="table-detail--medium"><dt>Unidad</dt><dd>{{ $producto->unidad_codigo }}</dd></div>
                                <div class="table-detail--low"><dt>Marca</dt><dd>{{ $producto->marca_nombre ?? 'Sin marca' }}</dd></div>
                                <div class="table-detail--low"><dt>Ubicaciones</dt><dd>{{ $producto->cantidad_repisas }} repisa(s)</dd></div>
                            </dl>
                        </x-ui.table-row-details>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pagination :paginator="$productos" />
        @else
            <x-ui.empty-table
                icon="products"
                :title="$hayFiltros ? 'No hay coincidencias' : 'Aún no hay productos'"
                :description="$hayFiltros ? 'Ajusta o limpia los filtros para ver otros resultados.' : 'Registra el primer producto para iniciar el catálogo.'"
                :action-url="$hayFiltros ? route('productos.index') : route('productos.create')"
                :action-label="$hayFiltros ? 'Limpiar filtros' : 'Registrar primer producto'"
                :action-icon="$hayFiltros ? 'close' : 'plus'"
            />
        @endif
    </section>
@endsection
