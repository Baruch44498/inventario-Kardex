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
                    <option value="">Todos</option>
                    <option value="ACTIVA" @selected(request('estado') === 'ACTIVA')>Activa</option>
                    <option value="ATENDIDA" @selected(request('estado') === 'ATENDIDA')>Atendida</option>
                    <option value="RESUELTA" @selected(request('estado') === 'RESUELTA')>Resuelta</option>
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

    <x-ui.collapsible-notice title="Cómo funciona una alerta atendida" label="Ver información sobre alertas atendidas">
        <span>
            Marcar una alerta como atendida confirma que un responsable ya la revisó.
            No modifica el stock ni la resuelve; la resolución ocurre cuando el inventario
            vuelve a superar su mínimo.
        </span>
    </x-ui.collapsible-notice>

    <section class="panel {{ $alertas->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($alertas->count() > 0)
        <div class="table-wrap table-wrap--wide table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--actions data-table--alerts data-table--responsive}">
                <thead>
                    <tr>
                        <th class="table-sticky--start">Producto</th>
                        <th class="table-priority--medium">Repisa</th>
                        <th>Condición</th>
                        <th>Existencia</th>
                        <th>Estado</th>
                        <th class="table-priority--low">Detectada</th>
                        <th class="table-priority--low">Responsable</th>
                        <th class="table-sticky--end">Acción</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($alertas as $alerta)
                        @php
                            $estadoClase = match ($alerta->estado) { 'ACTIVA' => 'danger', 'ATENDIDA' => 'warning', default => 'success' };
                            $nivelClase = $alerta->nivel === 'CRITICA' ? 'danger' : 'warning';
                            $detailsId = 'alerta-detalles-' . $alerta->id;
                        @endphp

                        <tr>
                            <td class="table-sticky--start">
                                <a href="{{ route('productos.show', $alerta->producto_id) }}" class="table-primary-link">{{ $alerta->producto_codigo }}</a>
                                <span>{{ $alerta->producto_descripcion }}</span>
                                <small class="table-message">{{ $alerta->mensaje }}</small>
                            </td>
                            <td class="table-priority--medium"><span class="location-chip"><x-ui.icon name="shelf" :size="14" />{{ $alerta->repisa_codigo }}</span></td>
                            <td><span class="badge badge--{{ $nivelClase }}">{{ $alerta->tipo_alerta === 'SIN_STOCK' ? 'SIN STOCK' : 'STOCK MÍNIMO' }}</span><span>{{ str($alerta->nivel)->title() }}</span></td>
                            <td><span class="stock-comparison"><strong><x-ui.quantity :value="$alerta->stock_actual" /></strong><span>mínimo <x-ui.quantity :value="$alerta->stock_minimo" /></span></span></td>
                            <td><span class="badge badge--{{ $estadoClase }}">{{ $alerta->estado }}</span></td>
                            <td class="table-date table-priority--low"><strong>{{ \Illuminate\Support\Carbon::parse($alerta->detectada_en)->format('d/m/Y') }}</strong><span>{{ \Illuminate\Support\Carbon::parse($alerta->detectada_en)->format('H:i') }}</span></td>
                            <td class="table-priority--low">
                                @if ($alerta->estado === 'ATENDIDA') {{ $alerta->atendida_por_nombre ?: 'Usuario no disponible' }}
                                @elseif ($alerta->estado === 'RESUELTA') {{ $alerta->resuelta_por_nombre ?: 'Resolución automática' }}
                                @else <span class="text-muted">Pendiente</span> @endif
                            </td>
                            <td class="table-sticky--end">
                                <div class="table-actions">
                                    @if (auth()->user()->puede('requerimientos.compra.crear') && $alerta->estado !== 'RESUELTA')
                                        <a href="{{ route('requerimientos-compra.create', ['producto_id' => $alerta->producto_id]) }}"
                                            class="icon-button" title="Crear requerimiento de compra" aria-label="Crear requerimiento de compra">
                                            <x-ui.icon name="requisitions" :size="17" />
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
                                    <x-ui.table-details-toggle :target="$detailsId" label="Ver más datos de la alerta" />
                                </div>
                            </td>
                        </tr>
                        <x-ui.table-row-details :id="$detailsId" :colspan="8">
                            <dl class="table-details-grid">
                                <div class="table-detail--medium"><dt>Repisa</dt><dd>{{ $alerta->repisa_codigo }}</dd></div>
                                <div class="table-detail--low"><dt>Detectada</dt><dd>{{ \Illuminate\Support\Carbon::parse($alerta->detectada_en)->format('d/m/Y H:i') }}</dd></div>
                                <div class="table-detail--low"><dt>Responsable</dt><dd>@if ($alerta->estado === 'ATENDIDA') {{ $alerta->atendida_por_nombre ?: 'Usuario no disponible' }} @elseif ($alerta->estado === 'RESUELTA') {{ $alerta->resuelta_por_nombre ?: 'Resolución automática' }} @else Pendiente @endif</dd></div>
                                <div class="table-detail--low"><dt>Mensaje</dt><dd>{{ $alerta->mensaje }}</dd></div>
                            </dl>
                        </x-ui.table-row-details>
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
