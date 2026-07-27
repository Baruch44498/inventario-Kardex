@extends('layouts.app')

@section('title', 'Repisas')
@section('page-kicker', 'Almacén')
@section('page-title', 'Repisas')

@section('content')
    @php
        $hayFiltros = request()->filled('q') || request()->filled('estado');
    @endphp

    <section class="module-header">
        <div>
            <p class="eyebrow">Ubicaciones físicas</p>
            <h1>Repisas</h1>
            <p>Administra las ubicaciones internas del único almacén de HIDROIL.</p>
        </div>

        <a href="{{ route('repisas.create') }}" class="button button--primary">
            <x-ui.icon name="plus" :size="18" />
            Nueva repisa
        </a>
    </section>

    <section class="summary-strip" aria-label="Resumen de repisas">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info">
                <x-ui.icon name="shelf" :size="22" />
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
                <span>Activas</span>
                <strong>{{ number_format((int) ($resumen->activas ?? 0)) }}</strong>
            </div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--neutral">
                <x-ui.icon name="power" :size="22" />
            </span>
            <div>
                <span>Inactivas</span>
                <strong>{{ number_format((int) ($resumen->inactivas ?? 0)) }}</strong>
            </div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--warning">
                <x-ui.icon name="products" :size="22" />
            </span>
            <div>
                <span>Con productos</span>
                <strong>{{ number_format((int) ($resumen->con_productos ?? 0)) }}</strong>
            </div>
        </article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('repisas.index') }}" class="filter-grid filter-grid--compact">
            <div class="form-field filter-grid__search">
                <label for="q">Buscar</label>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="18" /></span>
                    <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Código o descripción">
                </div>
            </div>
            <div class="form-field">
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
                    <option value="">Todos</option>
                    <option value="activo" @selected(request('estado') === 'activo')>Activas</option>
                    <option value="inactivo" @selected(request('estado') === 'inactivo')>Inactivas</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="filter" :size="17" />
                    Filtrar
                </button>
                <a href="{{ route('repisas.index') }}" class="button button--ghost">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="panel {{ $repisas->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($repisas->count() > 0)
        <div class="table-wrap table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--actions data-table--responsive}">
                <thead>
                    <tr>
                        <th class="table-sticky--start">Código</th>
                        <th class="table-priority--medium">Descripción</th>
                        <th class="text-right table-priority--low">Productos ubicados</th>
                        <th class="text-right table-priority--medium">Stock total</th>
                        <th>Estado</th>
                        <th class="text-right table-sticky--end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($repisas as $repisa)
                        @php($detailsId = 'repisa-detalles-' . $repisa->id_repisa)
                        <tr>
                            <td class="table-sticky--start">
                                <span class="location-chip">
                                    <x-ui.icon name="shelf" :size="15" />
                                    {{ $repisa->codigo }}
                                </span>
                            </td>
                            <td class="table-priority--medium">{{ $repisa->descripcion ?? 'Sin descripción' }}</td>
                            <td class="text-right table-priority--low">{{ number_format((int) $repisa->productos_ubicados) }}</td>
                            <td class="text-right table-priority--medium"><x-ui.quantity :value="$repisa->stock_total" /></td>
                            <td>
                                <span class="badge badge--{{ $repisa->estado ? 'success' : 'neutral' }}">
                                    {{ $repisa->estado ? 'ACTIVA' : 'INACTIVA' }}
                                </span>
                            </td>
                            <td class="text-right table-sticky--end">
                                <div class="table-actions">
                                    <a href="{{ route('repisas.edit', $repisa->id_repisa) }}" class="icon-button" title="Editar" aria-label="Editar repisa {{ $repisa->codigo }}">
                                        <x-ui.icon name="edit" :size="17" />
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('repisas.toggle', $repisa->id_repisa) }}"
                                        data-confirm="{{ $repisa->estado ? '¿Desactivar esta repisa? Sus inventarios no se eliminarán.' : '¿Activar nuevamente esta repisa?' }}"
                                    >
                                        @csrf
                                        @method('PATCH')
                                        <button
                                            type="submit"
                                            class="icon-button icon-button--{{ $repisa->estado ? 'danger' : 'success' }}"
                                            title="{{ $repisa->estado ? 'Desactivar' : 'Activar' }}"
                                            aria-label="{{ $repisa->estado ? 'Desactivar' : 'Activar' }} repisa {{ $repisa->codigo }}"
                                        >
                                            <x-ui.icon name="power" :size="17" />
                                        </button>
                                    </form>
                                    <x-ui.table-details-toggle :target="$detailsId" label="Ver más datos de la repisa {{ $repisa->codigo }}" />
                                </div>
                            </td>
                        </tr>
                        <x-ui.table-row-details :id="$detailsId" :colspan="6">
                            <dl class="table-details-grid">
                                <div class="table-detail--medium"><dt>Descripción</dt><dd>{{ $repisa->descripcion ?? 'Sin descripción' }}</dd></div>
                                <div class="table-detail--low"><dt>Productos ubicados</dt><dd>{{ number_format((int) $repisa->productos_ubicados) }}</dd></div>
                                <div class="table-detail--medium"><dt>Stock total</dt><dd><x-ui.quantity :value="$repisa->stock_total" /></dd></div>
                            </dl>
                        </x-ui.table-row-details>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-ui.pagination :paginator="$repisas" />
        @else
            <x-ui.empty-table
                icon="shelf"
                :title="$hayFiltros ? 'No hay coincidencias' : 'Aún no hay repisas'"
                :description="$hayFiltros ? 'Ajusta o limpia los filtros para ver otras ubicaciones.' : 'Registra la primera ubicación física del almacén.'"
                :action-url="$hayFiltros ? route('repisas.index') : route('repisas.create')"
                :action-label="$hayFiltros ? 'Limpiar filtros' : 'Registrar primera repisa'"
                :action-icon="$hayFiltros ? 'close' : 'plus'"
            />
        @endif
    </section>
@endsection
