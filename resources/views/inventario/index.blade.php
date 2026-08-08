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
                Consulta stock físico, reservas, disponibilidad y costo promedio. Las reservas
                no mueven Kardex; las existencias solo cambian mediante entradas, salidas y reversas.
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
            <span class="summary-strip__icon summary-strip__icon--info">
                <x-ui.icon name="orders" :size="22" />
            </span>
            <div>
                <span>Con reserva</span>
                <strong>{{ number_format((int) ($resumen->productos_reservados ?? 0)) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--warning">
                <x-ui.icon name="warning" :size="22" />
            </span>
            <div>
                <span>Requieren compra</span>
                <strong>{{ number_format((int) ($resumen->requieren_abastecimiento ?? 0)) }}</strong>
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
                <label for="repisa_busqueda">Repisa</label>
                <x-ui.remote-combobox
                    name="repisa"
                    search-id="repisa_busqueda"
                    value-id="repisa"
                    :search-url="route('catalogos.repisas.buscar', ['todos' => 1])"
                    :selected-id="$repisaFiltro?->id_repisa"
                    :selected-label="$repisaFiltro
                        ? $repisaFiltro->codigo.($repisaFiltro->descripcion ? ' — '.$repisaFiltro->descripcion : '')
                        : ''"
                    placeholder="Código o descripción"
                    empty-text="No se encontró la repisa."
                />
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

    @if ($herramientasEnUso->isNotEmpty())
        <section class="panel inventory-tools-panel">
            <div class="panel-heading panel-heading--split">
                <div>
                    <p class="eyebrow">Uso temporal</p>
                    <h2>Herramientas en uso</h2>
                    <p>
                        No están reservadas ni consumidas: salieron temporalmente y continúan pendientes de devolución.
                    </p>
                </div>
                <span class="count-chip">{{ $herramientasEnUso->count() }}</span>
            </div>

            <div class="table-wrap table-wrap--responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Herramienta</th>
                            <th class="text-right">En uso</th>
                            <th>Orden</th>
                            <th>Responsable</th>
                            <th>Salida</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($herramientasEnUso as $herramienta)
                            <tr>
                                <td>
                                    <strong>{{ $herramienta->producto_codigo }}</strong>
                                    <span>{{ $herramienta->producto_descripcion }}</span>
                                </td>
                                <td class="text-right"><x-ui.quantity :value="$herramienta->pendiente" /> {{ $herramienta->unidad_codigo }}</td>
                                <td>{{ $herramienta->codigo_orden ?: 'Uso interno' }}</td>
                                <td>{{ $herramienta->entregado_a ?: 'No registrado' }}</td>
                                <td>
                                    @if (auth()->user()->puede('salidas.ver'))
                                        <a href="{{ route('notas-salida.show', $herramienta->nota_id) }}" class="table-primary-link">
                                            {{ $herramienta->nota_codigo }}
                                        </a>
                                    @else
                                        {{ $herramienta->nota_codigo }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="panel {{ $inventarios->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($inventarios->count() > 0)
        <div class="table-wrap table-wrap--wide table-wrap--responsive inventory-planning-table-wrap" data-responsive-table>
                <table class="data-table data-table--actions data-table--wide data-table--responsive inventory-planning-table">
                <thead>
                    <tr>
                        <th class="table-sticky--start">Producto</th>
                        <th class="table-priority--medium">Repisa</th>
                        <th class="text-right">Stock físico</th>
                        <th class="text-right" title="Reservado para órdenes">Reservado <small>producto</small></th>
                        <th class="text-right" title="Disponible libre después de reservas">Disponible <small>producto</small></th>
                        <th class="text-right table-priority--medium">Mínimo</th>
                        <th class="text-right table-priority--low">Máximo</th>
                        <th class="text-right table-priority--low">Costo prom.</th>
                        <th class="text-right table-priority--low">Valor</th>
                        <th class="text-right">Compra sug.</th>
                        <th>Estado físico</th>
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
                                <span>{{ $item->unidad_codigo }} · esta repisa</span>
                            </td>
                            <td class="text-right">
                                <strong><x-ui.quantity :value="$item->reservado_total" /></strong>
                                <span>{{ $item->unidad_codigo }} · producto</span>
                            </td>
                            <td class="text-right">
                                <strong @class(['availability-negative' => (float) $item->disponible_total < 0])>
                                    <x-ui.quantity :value="$item->disponible_total" />
                                </strong>
                                <span>{{ $item->unidad_codigo }} · producto</span>
                            </td>
                            <td class="text-right table-priority--medium"><x-ui.quantity :value="$item->stock_minimo" /></td>
                            <td class="text-right table-priority--low">
                                @if ($item->stock_maximo === null) — @else <x-ui.quantity :value="$item->stock_maximo" /> @endif
                            </td>
                            <td class="text-right table-priority--low">S/ {{ number_format((float) $item->costo_promedio_soles, 2, '.', ',') }}</td>
                            <td class="text-right table-priority--low">S/ {{ number_format((float) $item->valor_total, 2, '.', ',') }}</td>
                            <td class="text-right">
                                @if ((float) $item->necesidad_abastecimiento > 0.0001)
                                    <span class="badge badge--warning">
                                        <x-ui.quantity :value="$item->necesidad_abastecimiento" /> {{ $item->unidad_codigo }}
                                    </span>
                                @else
                                    <span class="badge badge--success">Cubierto</span>
                                @endif
                            </td>
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
                        <x-ui.table-row-details :id="$detailsId" :colspan="12">
                            <dl class="table-details-grid">
                                <div class="table-detail--medium"><dt>Repisa</dt><dd>{{ $item->repisa_codigo }}</dd></div>
                                <div class="table-detail--medium"><dt>Stock mínimo</dt><dd><x-ui.quantity :value="$item->stock_minimo" /></dd></div>
                                <div class="table-detail--medium"><dt>Stock físico total</dt><dd><x-ui.quantity :value="$item->stock_fisico_total" /> {{ $item->unidad_codigo }}</dd></div>
                                <div class="table-detail--medium"><dt>Reservado total</dt><dd><x-ui.quantity :value="$item->reservado_total" /> {{ $item->unidad_codigo }}</dd></div>
                                <div class="table-detail--medium"><dt>Disponible libre</dt><dd><x-ui.quantity :value="$item->disponible_total" /> {{ $item->unidad_codigo }}</dd></div>
                                <div class="table-detail--medium"><dt>Compra sugerida</dt><dd><x-ui.quantity :value="$item->necesidad_abastecimiento" /> {{ $item->unidad_codigo }}</dd></div>
                                <div class="table-detail--low"><dt>Stock máximo</dt><dd>@if ($item->stock_maximo === null) — @else <x-ui.quantity :value="$item->stock_maximo" /> @endif</dd></div>
                                <div class="table-detail--low"><dt>Costo promedio</dt><dd>S/ {{ number_format((float) $item->costo_promedio_soles, 2, '.', ',') }}</dd></div>
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
