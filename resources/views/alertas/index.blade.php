@extends('layouts.app')

@section('title', 'Alertas de stock')
@section('page-kicker', 'Almacén')
@section('page-title', 'Alertas de stock')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Control de reposición</p>
            <h1>Alertas de stock</h1>
            <p>
                Identifica productos sin stock o en el mínimo configurado.
                Las alertas se resuelven automáticamente cuando la existencia
                vuelve a superar el mínimo.
            </p>
        </div>

        <form
            method="POST"
            action="{{ route('alertas.evaluar') }}"
            data-loading-form
        >
            @csrf
            <button
                type="submit"
                class="button button--primary"
                data-submit-button
                data-loading-text="Evaluando..."
            >
                <span data-submit-icon>
                    <x-ui.icon name="refresh" :size="18" />
                </span>
                <span class="button-spinner" data-submit-spinner hidden></span>
                <span data-submit-label>Evaluar inventario</span>
            </button>
        </form>
    </section>

    <section class="summary-strip" aria-label="Resumen de alertas">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--danger">
                <x-ui.icon name="bell" :size="20" />
            </span>
            <div>
                <span>Activas</span>
                <strong>{{ number_format((int) ($resumen->activas ?? 0)) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--warning">
                <x-ui.icon name="clock" :size="20" />
            </span>
            <div>
                <span>Atendidas</span>
                <strong>{{ number_format((int) ($resumen->atendidas ?? 0)) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--danger">
                <x-ui.icon name="warning" :size="20" />
            </span>
            <div>
                <span>Críticas abiertas</span>
                <strong>{{ number_format((int) ($resumen->criticas ?? 0)) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success">
                <x-ui.icon name="check-circle" :size="20" />
            </span>
            <div>
                <span>Resueltas</span>
                <strong>{{ number_format((int) ($resumen->resueltas ?? 0)) }}</strong>
            </div>
        </article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('alertas.index') }}" class="filter-grid filter-grid--alerts">
            <label class="form-field filter-grid__search">
                <span>Buscar</span>
                <span class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="18" /></span>
                    <input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Producto, descripción, repisa o mensaje"
                    >
                </span>
            </label>

            <label class="form-field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Abiertas (activas + atendidas)</option>
                    <option value="ACTIVA" @selected(request('estado') === 'ACTIVA')>Solo activas</option>
                    <option value="ATENDIDA" @selected(request('estado') === 'ATENDIDA')>Solo atendidas</option>
                    <option value="RESUELTA" @selected(request('estado') === 'RESUELTA')>Solo resueltas</option>
                    <option value="TODOS" @selected(request('estado') === 'TODOS')>Todos los estados</option>
                </select>
            </label>

            <label class="form-field">
                <span>Nivel</span>
                <select name="nivel">
                    <option value="">Todos</option>
                    <option value="CRITICA" @selected(request('nivel') === 'CRITICA')>Crítica</option>
                    <option value="ADVERTENCIA" @selected(request('nivel') === 'ADVERTENCIA')>Advertencia</option>
                </select>
            </label>

            <label class="form-field">
                <span>Condición</span>
                <select name="tipo">
                    <option value="">Todas</option>
                    <option value="SIN_STOCK" @selected(request('tipo') === 'SIN_STOCK')>Sin stock</option>
                    <option value="STOCK_MINIMO" @selected(request('tipo') === 'STOCK_MINIMO')>Stock mínimo</option>
                </select>
            </label>

            <div class="filter-actions">
                <button type="submit" class="button button--primary">
                    <x-ui.icon name="filter" :size="17" />
                    Filtrar
                </button>

                <a href="{{ route('alertas.index') }}" class="button button--ghost">
                    Limpiar
                </a>
            </div>
        </form>
    </section>

    <section class="panel inventory-alert-workspace {{ $alertas->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($puedeCrearRequerimiento && $elegiblesFiltrados > 0)
        <form id="alertas-requerimiento-form" class="inventory-alert-bulk-form" method="POST" action="{{ route('alertas.preparar-requerimiento') }}" data-alert-bulk-form>
            @csrf
            @foreach (['q', 'estado', 'nivel', 'tipo'] as $filtro)
                @if (request()->filled($filtro))
                    <input type="hidden" name="{{ $filtro }}" value="{{ request($filtro) }}">
                @endif
            @endforeach

            <div class="inventory-alert-bulk-panel">
                <header class="inventory-alert-bulk-heading">
                    <div class="inventory-alert-bulk-copy">
                        <p class="eyebrow">Reposición masiva</p>
                        <h2>Preparar requerimiento desde alertas</h2>
                        <p>Selecciona una, varias o todas las alertas filtradas. El sistema consolidará las repisas del mismo producto antes de calcular la cantidad sugerida.</p>
                    </div>
                    <div class="inventory-alert-bulk-actions">
                        <button type="button" class="button button--primary button--small" data-alert-selection-start>
                            <x-ui.icon name="check" :size="16" /> Seleccionar alertas
                        </button>
                        <span class="count-chip inventory-alert-selection-control" data-alert-selection-count hidden>0 seleccionadas</span>
                        <button type="submit" name="alcance" value="SELECCIONADAS" class="button button--primary button--small inventory-alert-selection-control" data-selected-alerts-submit hidden disabled>
                            <x-ui.icon name="requisitions" :size="16" /> Preparar seleccionadas
                        </button>
                        <button type="button" class="button button--ghost button--small inventory-alert-selection-control" data-alert-selection-cancel hidden>
                            Cancelar selección
                        </button>
                        <button type="submit" name="alcance" value="FILTRADAS" class="button button--ghost button--small">
                            Preparar todas las filtradas ({{ $elegiblesFiltrados }})
                        </button>
                    </div>
                </header>
                @error('alerta_ids')
                    <small class="field-error">{{ $message }}</small>
                @enderror
            </div>
        </form>
        @endif

        <header class="inventory-alert-list-heading">
            <h2>Alertas registradas</h2>
            <x-ui.collapsible-notice title="Cómo funciona una alerta atendida" label="Ver información sobre alertas atendidas">
                <span>
                    Cuando una alerta origina un requerimiento queda identificada como reposición
                    en curso y no puede seleccionarse nuevamente. Esto no modifica el stock; la
                    resolución ocurre cuando una recepción eleva la existencia sobre su mínimo.
                </span>
            </x-ui.collapsible-notice>
        </header>
        @if ($alertas->count() > 0)
            <div class="table-wrap table-wrap--responsive inventory-alert-table-wrap" data-responsive-table>
                <table class="data-table data-table--actions data-table--responsive inventory-alert-table">
                <thead>
                    <tr>
                        @if ($puedeCrearRequerimiento && $elegiblesFiltrados > 0)
                            <th class="inventory-alert-selection-control" data-alert-selection-control hidden>
                                <input type="checkbox" data-select-visible-alerts aria-label="Seleccionar todas las alertas visibles elegibles">
                            </th>
                        @endif
                        <th>Producto / repisa</th>
                        <th>Condición / existencia</th>
                        <th>Estado / reposición</th>
                        <th>Detectada</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($alertas as $alerta)
                        @php
                            $estadoClase = match ($alerta->estado) { 'ACTIVA' => 'danger', 'ATENDIDA' => 'warning', default => 'success' };
                            $nivelClase = $alerta->nivel === 'CRITICA' ? 'danger' : 'warning';
                        @endphp

                        <tr>
                            @if ($puedeCrearRequerimiento && $elegiblesFiltrados > 0)
                                <td class="inventory-alert-selection-control" data-alert-selection-control hidden>
                                    @if ($alerta->estado !== 'RESUELTA' && ! $alerta->requisicion_activa_id)
                                        <input
                                            type="checkbox"
                                            name="alerta_ids[]"
                                            value="{{ $alerta->id }}"
                                            form="alertas-requerimiento-form"
                                            data-alert-checkbox
                                            aria-label="Seleccionar alerta de {{ $alerta->producto_codigo }}"
                                        >
                                    @endif
                                </td>
                            @endif
                            <td data-label="Producto / repisa" class="inventory-flow-product">
                                <a href="{{ route('productos.show', $alerta->producto_id) }}" class="table-primary-link">{{ $alerta->producto_codigo }}</a>
                                <span>{{ $alerta->producto_descripcion }}</span>
                                <span class="location-chip"><x-ui.icon name="shelf" :size="14" />{{ $alerta->repisa_codigo }}</span>
                            </td>
                            <td data-label="Condición / existencia" class="inventory-alert-condition"><span class="badge badge--{{ $nivelClase }}">{{ $alerta->tipo_alerta === 'SIN_STOCK' ? 'SIN STOCK' : 'STOCK MÍNIMO' }}</span><span>{{ str($alerta->nivel)->title() }}</span><span class="stock-comparison"><strong><x-ui.quantity :value="$alerta->stock_actual" /></strong><span>mínimo <x-ui.quantity :value="$alerta->stock_minimo" /></span></span></td>
                            <td data-label="Estado / reposición" class="inventory-alert-state">
                                <span class="badge badge--{{ $estadoClase }}">{{ $alerta->estado }}</span>
                                @if ($alerta->requisicion_activa_id)
                                    <a href="{{ route('requerimientos-compra.show', $alerta->requisicion_activa_id) }}">
                                        {{ $alerta->requisicion_activa_codigo }} · Reposición en curso
                                    </a>
                                @endif
                                @if ($alerta->estado === 'ATENDIDA')
                                    <small>Responsable: {{ $alerta->atendida_por_nombre ?: 'Usuario no disponible' }}</small>
                                @elseif ($alerta->estado === 'RESUELTA')
                                    <small>Responsable: {{ $alerta->resuelta_por_nombre ?: 'Resolución automática' }}</small>
                                @endif
                            </td>
                            <td data-label="Detectada" class="table-date"><strong>{{ \Illuminate\Support\Carbon::parse($alerta->detectada_en)->format('d/m/Y') }}</strong><span>{{ \Illuminate\Support\Carbon::parse($alerta->detectada_en)->format('H:i') }}</span></td>
                            <td data-label="Acción">
                                <div class="table-actions inventory-alert-actions" role="group" aria-label="Acciones de la alerta {{ $alerta->producto_codigo }}">
                                    @if (auth()->user()->puede('requerimientos.compra.crear') && $alerta->estado !== 'RESUELTA' && ! $alerta->requisicion_activa_id)
                                        <a href="{{ route('requerimientos-compra.create', ['producto_id' => $alerta->producto_id, 'alerta_id' => $alerta->id]) }}"
                                            class="icon-button" title="Crear requerimiento de compra" aria-label="Crear requerimiento de compra">
                                            <x-ui.icon name="requisitions" :size="17" />
                                        </a>
                                    @elseif ($alerta->requisicion_activa_id)
                                        <a href="{{ route('requerimientos-compra.show', $alerta->requisicion_activa_id) }}"
                                            class="icon-button" title="Ver reposición en curso" aria-label="Ver reposición en curso">
                                            <x-ui.icon name="eye" :size="17" />
                                        </a>
                                    @endif
                                    @if ($alerta->estado === 'ACTIVA')
                                        <form method="POST" action="{{ route('alertas.atender', $alerta->id) }}" data-loading-form>
                                            @csrf @method('PATCH')
                                            <button type="submit" class="icon-button icon-button--success" title="Marcar como atendida" aria-label="Marcar como atendida" data-submit-button data-loading-text="Procesando...">
                                                <span data-submit-icon><x-ui.icon name="check" :size="17" /></span>
                                                <span class="button-spinner" data-submit-spinner hidden></span>
                                                <span class="sr-only" data-submit-label>Atender</span>
                                            </button>
                                        </form>
                                    @else
                                        <span class="table-action-complete"><x-ui.icon name="check-circle" :size="18" /></span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pagination :paginator="$alertas" />
        @else
            <div class="empty-table-state">
                <span class="empty-state__icon empty-state__icon--success">
                    <x-ui.icon name="check-circle" :size="30" />
                </span>
                <strong>Sin alertas registradas</strong>
                <span>Evalúa el inventario para comprobar si existen productos sin stock o en el mínimo.</span>
                <form method="POST" action="{{ route('alertas.evaluar') }}" class="empty-table-state__actions" data-loading-form>
                    @csrf
                    <button type="submit" class="button button--primary button--small" data-submit-button data-loading-text="Evaluando...">
                        <span data-submit-icon><x-ui.icon name="refresh" :size="16" /></span>
                        <span class="button-spinner" data-submit-spinner hidden></span>
                        <span data-submit-label>Evaluar inventario</span>
                    </button>
                </form>
            </div>
        @endif
    </section>
@endsection

@push('scripts')
<script src="{{ asset('js/alertas-stock.js') }}" defer></script>
@endpush
