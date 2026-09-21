@extends('layouts.app')

@section('title', 'Inventarios periódicos')
@section('page-kicker', 'Almacén')
@section('page-title', 'Inventarios periódicos')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Conteo físico auditable</p>
            <h1>Inventarios periódicos</h1>
            <p>
                Abre un conteo por repisa, registra las existencias físicas y aplica las diferencias
                al Kardex únicamente cuando el conteo sea cerrado.
            </p>
        </div>

        @if (auth()->user()->puede('inventario.configurar'))
            <a href="{{ route('inventarios-periodicos.create') }}" class="button button--primary">
                <x-ui.icon name="plus" :size="18" /> Abrir conteo
            </a>
        @endif
    </section>

    <section class="summary-strip" aria-label="Resumen de inventarios periódicos">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="clipboard" :size="21" /></span>
            <div><span>Total</span><strong>{{ (int) ($resumen->total ?? 0) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--warning"><x-ui.icon name="edit" :size="21" /></span>
            <div><span>Abiertos</span><strong>{{ (int) ($resumen->abiertos ?? 0) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success"><x-ui.icon name="check-circle" :size="21" /></span>
            <div><span>Cerrados</span><strong>{{ (int) ($resumen->cerrados ?? 0) }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--danger"><x-ui.icon name="warning" :size="21" /></span>
            <div><span>Líneas ajustadas</span><strong>{{ (int) ($resumen->diferencias ?? 0) }}</strong></div>
        </article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('inventarios-periodicos.index') }}" class="filter-grid filter-grid--entries">
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
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="ABIERTO" @selected(request('estado') === 'ABIERTO')>Abierto</option>
                    <option value="CERRADO" @selected(request('estado') === 'CERRADO')>Cerrado</option>
                    <option value="ANULADO" @selected(request('estado') === 'ANULADO')>Anulado</option>
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
                <button type="submit" class="button button--primary"><x-ui.icon name="filter" :size="17" /> Filtrar</button>
                <a href="{{ route('inventarios-periodicos.index') }}" class="button button--ghost">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="panel {{ $inventariosPeriodicos->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($inventariosPeriodicos->count() > 0)
            <div class="table-wrap table-wrap--responsive periodic-inventory-list-wrap" data-responsive-table>
                <table class="data-table data-table--actions data-table--responsive periodic-inventory-list">
                    <thead>
                        <tr>
                            <th>Conteo / repisa</th>
                            <th>Fecha de corte</th>
                            <th>Avance</th>
                            <th>Diferencias</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inventariosPeriodicos as $periodico)
                            @php
                                $tono = match ($periodico->estado) {
                                    'CERRADO' => 'success',
                                    'ANULADO' => 'danger',
                                    default => 'warning',
                                };
                                $detailsId = 'inventario-periodico-detalles-' . $periodico->id;
                            @endphp
                            <tr>
                                <td data-label="Conteo / repisa" class="inventory-flow-product">
                                    <a href="{{ route('inventarios-periodicos.show', $periodico) }}" class="table-primary-link">{{ $periodico->codigo }}</a>
                                    <span class="location-chip"><x-ui.icon name="shelf" :size="14" />{{ $periodico->repisa?->codigo }}</span>
                                </td>
                                <td data-label="Fecha de corte" class="table-date"><strong>{{ $periodico->fecha_corte?->format('d/m/Y') }}</strong><span>{{ $periodico->fecha_corte?->format('H:i') }}</span></td>
                                <td data-label="Avance" class="periodic-inventory-progress"><strong>{{ (int) $periodico->lineas_contadas }}/{{ (int) $periodico->total_lineas }}</strong><span>líneas contadas</span></td>
                                <td data-label="Diferencias" class="periodic-inventory-difference"><strong>{{ (int) $periodico->lineas_con_diferencia }}</strong><span><x-ui.money :value="$periodico->valor_diferencia_soles" /></span></td>
                                <td data-label="Estado"><x-ui.status-badge :tone="$tono">{{ str($periodico->estado)->title() }}</x-ui.status-badge></td>
                                <td data-label="Acción"><div class="table-actions">
                                    <a href="{{ route('inventarios-periodicos.show', $periodico) }}" class="icon-button" title="Ver conteo" aria-label="Ver conteo"><x-ui.icon name="eye" :size="17" /></a>
                                    <x-ui.table-details-toggle :target="$detailsId" label="Ver auditoría del conteo" />
                                </div>
                                </td>
                            </tr>
                            <x-ui.table-row-details :id="$detailsId" :colspan="6">
                                <dl class="table-details-grid inventory-audit-details">
                                    <div><dt>Responsable</dt><dd>{{ $periodico->abiertoPor?->nombreVisible() ?? '—' }}</dd></div>
                                    <div><dt>Repisa</dt><dd>{{ $periodico->repisa?->codigo ?? '—' }}</dd></div>
                                    <div><dt>Total de líneas</dt><dd>{{ (int) $periodico->total_lineas }}</dd></div>
                                    <div><dt>Valor diferencia</dt><dd><x-ui.money :value="$periodico->valor_diferencia_soles" /></dd></div>
                                    <div><dt>Observación</dt><dd>{{ $periodico->observacion ?: 'Conteo físico por repisa' }}</dd></div>
                                </dl>
                            </x-ui.table-row-details>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$inventariosPeriodicos" />
        @else
            <x-ui.empty-table
                icon="clipboard"
                title="No hay inventarios periódicos"
                description="Los conteos físicos por repisa aparecerán aquí."
                :action-url="auth()->user()->puede('inventario.configurar') ? route('inventarios-periodicos.create') : null"
                action-label="Abrir primer conteo"
                action-icon="plus"
            />
        @endif
    </section>
@endsection
