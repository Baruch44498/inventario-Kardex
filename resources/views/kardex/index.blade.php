@extends('layouts.app')

@section('title', 'Kardex valorizado')
@section('page-kicker', 'Administración')
@section('page-title', 'Kardex valorizado')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Trazabilidad económica del inventario</p>
            <h1>Kardex valorizado</h1>
            <p>
                Consulta cada entrada, salida, costo promedio y saldo valorizado.
                Los resultados se construyen únicamente con movimientos confirmados.
            </p>
        </div>

        <a href="{{ route('inventario.index') }}" class="button button--ghost">
            <x-ui.icon name="inventory" :size="18" />
            Ver inventario actual
        </a>
    </section>

    <section class="summary-strip kardex-summary" aria-label="Resumen valorizado del periodo">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info">
                <x-ui.icon name="movements" :size="21" />
            </span>
            <div>
                <span>Movimientos filtrados</span>
                <strong>{{ number_format((int) ($resumen->total_movimientos ?? 0)) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success">
                <x-ui.icon name="entry" :size="21" />
            </span>
            <div>
                <span>Valor de entradas</span>
                <strong><x-ui.money :value="$resumen->valor_entradas ?? 0" /></strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--danger">
                <x-ui.icon name="exit" :size="21" />
            </span>
            <div>
                <span>Valor de salidas</span>
                <strong><x-ui.money :value="$resumen->valor_salidas ?? 0" /></strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--accent">
                <x-ui.icon name="coins" :size="21" />
            </span>
            <div>
                <span>Valor actual seleccionado</span>
                <strong><x-ui.money :value="$inventarioActual->valor_actual ?? 0" /></strong>
            </div>
        </article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('kardex.index') }}" class="filter-grid filter-grid--kardex">
            <label class="form-field filter-grid__search">
                <span>Buscar movimiento</span>
                <span class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="18" /></span>
                    <input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Producto, repisa, motivo, origen o usuario"
                    >
                </span>
            </label>

            <div class="form-field">
                <label for="producto_busqueda">Producto</label>
                <x-ui.remote-combobox
                    name="producto_id"
                    search-id="producto_busqueda"
                    value-id="producto_id"
                    :search-url="route('catalogos.productos.buscar', ['todos' => 1])"
                    :selected-id="$productoFiltro?->id"
                    :selected-label="$productoFiltro
                        ? $productoFiltro->codigo.' — '.$productoFiltro->descripcion
                        : ''"
                    placeholder="Código o descripción"
                    empty-text="No se encontró el producto."
                />
            </div>

            <div class="form-field">
                <label for="repisa_busqueda">Repisa</label>
                <x-ui.remote-combobox
                    name="repisa_id"
                    search-id="repisa_busqueda"
                    value-id="repisa_id"
                    :search-url="route('catalogos.repisas.buscar', ['todos' => 1])"
                    :selected-id="$repisaFiltro?->id"
                    :selected-label="$repisaFiltro
                        ? $repisaFiltro->codigo.($repisaFiltro->descripcion ? ' — '.$repisaFiltro->descripcion : '')
                        : ''"
                    placeholder="Código o descripción"
                    empty-text="No se encontró la repisa."
                />
            </div>

            <label class="form-field">
                <span>Tipo</span>
                <select name="tipo">
                    <option value="">Entradas y salidas</option>
                    <option value="ENTRADA" @selected(request('tipo') === 'ENTRADA')>Entradas</option>
                    <option value="SALIDA" @selected(request('tipo') === 'SALIDA')>Salidas</option>
                    <option value="AJUSTE_COSTO" @selected(request('tipo') === 'AJUSTE_COSTO')>Ajustes de costo</option>
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
                    Aplicar filtros
                </button>
                <a href="{{ route('kardex.index') }}" class="button button--ghost">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="panel {{ $movimientos->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($movimientos->count() > 0)
            <div class="table-wrap table-wrap--wide table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--actions data-table--kardex data-table--responsive">
                    <thead>
                        <tr>
                            <th class="table-sticky--start">Fecha</th>
                            <th>Producto</th>
                            <th class="table-priority--medium">Repisa</th>
                            <th>Operación</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right table-priority--low">Costo unitario</th>
                            <th class="text-right">Valor movimiento</th>
                            <th class="text-right table-priority--medium">Saldo físico<br><span class="table-heading-note">por repisa</span></th>
                            <th class="text-right table-priority--low">Costo promedio</th>
                            <th class="text-right">Saldo valorizado<br><span class="table-heading-note">por repisa</span></th>
                            <th class="table-sticky--end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movimientos as $movimiento)
                            @php
                                $esEntrada = $movimiento->tipo_movimiento === 'ENTRADA';
                                $esAjusteCosto = $movimiento->tipo_movimiento === 'AJUSTE_COSTO';
                                $origen = str($movimiento->origen_tipo)->replace('_', ' ')->title();
                                $detailsId = 'kardex-detalles-' . $movimiento->id;
                            @endphp
                            <tr>
                                <td class="table-date table-sticky--start">
                                    <strong>{{ \Illuminate\Support\Carbon::parse($movimiento->fecha_movimiento)->format('d/m/Y') }}</strong>
                                    <span>{{ \Illuminate\Support\Carbon::parse($movimiento->fecha_movimiento)->format('H:i') }}</span>
                                </td>
                                <td>
                                    <a href="{{ route('productos.show', $movimiento->producto_id) }}" class="table-primary-link">
                                        {{ $movimiento->producto_codigo }}
                                    </a>
                                    <span>{{ $movimiento->producto_descripcion }}</span>
                                </td>
                                <td class="table-priority--medium">
                                    <span class="location-chip"><x-ui.icon name="shelf" :size="14" />{{ $movimiento->repisa_codigo }}</span>
                                </td>
                                <td>
                                    <span class="badge badge--{{ $esAjusteCosto ? 'info' : ($esEntrada ? 'success' : 'danger') }}">
                                        {{ $esAjusteCosto ? 'AJUSTE COSTO' : $movimiento->tipo_movimiento }}
                                    </span>
                                    <span>{{ str($movimiento->motivo)->replace('_', ' ')->title() }}</span>
                                </td>
                                <td class="text-right movement-quantity movement-quantity--{{ $esEntrada ? 'in' : 'out' }}">
                                    {{ $esAjusteCosto ? '—' : ($esEntrada ? '+' : '−') }}<x-ui.quantity :value="$movimiento->cantidad" />
                                    <span class="table-unit">{{ $movimiento->unidad_codigo }}</span>
                                </td>
                                <td class="text-right table-priority--low">
                                    S/ {{ number_format((float) ($movimiento->costo_unitario ?? $movimiento->costo_promedio_nuevo), 2, '.', ',') }}
                                </td>
                                <td class="text-right kardex-value kardex-value--{{ $esEntrada ? 'in' : 'out' }}">
                                    <x-ui.money :value="$movimiento->valor_movimiento" />
                                </td>
                                <td class="text-right table-priority--medium">
                                    <strong><x-ui.quantity :value="$movimiento->stock_posterior" /></strong>
                                </td>
                                <td class="text-right table-priority--low">
                                    S/ {{ number_format((float) $movimiento->costo_promedio_nuevo, 2, '.', ',') }}
                                </td>
                                <td class="text-right kardex-balance">
                                    <strong><x-ui.money :value="$movimiento->saldo_valorizado" /></strong>
                                </td>
                                <td class="table-sticky--end">
                                    <div class="table-actions">
                                        <a href="{{ route('movimientos.show', $movimiento->id) }}" class="icon-button" title="Ver movimiento" aria-label="Ver movimiento">
                                            <x-ui.icon name="eye" :size="17" />
                                        </a>
                                        <x-ui.table-details-toggle :target="$detailsId" label="Ver más datos del Kardex" />
                                    </div>
                                </td>
                            </tr>
                            <x-ui.table-row-details :id="$detailsId" :colspan="11">
                                <dl class="table-details-grid">
                                    <div><dt>Origen</dt><dd>{{ $origen }} #{{ $movimiento->origen_id }}</dd></div>
                                    <div><dt>Usuario</dt><dd>{{ $movimiento->usuario }}</dd></div>
                                    <div><dt>Stock anterior</dt><dd><x-ui.quantity :value="$movimiento->stock_anterior" /></dd></div>
                                    <div><dt>Costo promedio anterior</dt><dd>S/ {{ number_format((float) $movimiento->costo_promedio_anterior, 2, '.', ',') }}</dd></div>
                                    @if ($movimiento->observacion)
                                        <div><dt>Observación</dt><dd>{{ $movimiento->observacion }}</dd></div>
                                    @endif
                                </dl>
                            </x-ui.table-row-details>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$movimientos" />
        @else
            <x-ui.empty-table
                icon="coins"
                title="No hay movimientos para estos filtros"
                description="Prueba otro producto, repisa o rango de fechas. El Kardex solo muestra operaciones confirmadas."
                :action-url="route('kardex.index')"
                action-label="Limpiar filtros"
                action-icon="refresh"
            />
        @endif
    </section>
@endsection
