@extends('layouts.app')

@section('title', 'Notas de ingreso')
@section('page-kicker', 'Almacén')
@section('page-title', 'Notas de ingreso')

@section('content')
    <div class="document-list-page">
    <section class="module-header">
        <div>
            <p class="eyebrow">Recepción de compras</p>
            <h1>Notas de ingreso</h1>
            <p>
                Registra la recepción de productos comprados y actualiza el
                inventario por repisa con trazabilidad completa.
            </p>
        </div>

        <a href="{{ route('notas-ingreso.create') }}" class="button button--primary">
            <x-ui.icon name="plus" :size="18" />
            Nueva nota de ingreso
        </a>
    </section>

    <section class="summary-strip summary-strip--four" aria-label="Resumen de notas de ingreso">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--neutral">
                <x-ui.icon name="entry" :size="21" />
            </span>
            <div>
                <span>Total</span>
                <strong>{{ (int) ($resumen->total ?? 0) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success">
                <x-ui.icon name="check-circle" :size="21" />
            </span>
            <div>
                <span>Confirmadas</span>
                <strong>{{ (int) ($resumen->confirmadas ?? 0) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info">
                <x-ui.icon name="calendar" :size="21" />
            </span>
            <div>
                <span>Ingresos hoy</span>
                <strong>{{ (int) ($resumen->hoy ?? 0) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success">
                <x-ui.icon name="banknote" :size="21" />
            </span>
            <div>
                <span>Valor recibido</span>
                <strong>S/ {{ number_format($valorRecibido, 2, '.', ',') }}</strong>
            </div>
        </article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('notas-ingreso.index') }}" class="filter-grid filter-grid--entries">
            <label class="form-field filter-grid__search">
                <span>Buscar</span>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol">
                        <x-ui.icon name="search" :size="17" />
                    </span>
                    <input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Código, orden, proveedor o guía"
                    >
                </div>
            </label>

            <label class="form-field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    <option value="CONFIRMADA" @selected(request('estado') === 'CONFIRMADA')>Confirmada</option>
                    <option value="BORRADOR" @selected(request('estado') === 'BORRADOR')>Borrador</option>
                    <option value="ANULADA" @selected(request('estado') === 'ANULADA')>Anulada</option>
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

                <a href="{{ route('notas-ingreso.index') }}" class="button button--ghost">
                    Limpiar
                </a>
            </div>
        </form>
    </section>

    <div class="notice notice--info notice--block">
        <x-ui.icon name="info" :size="18" />
        <span>
            Al confirmar una nota, el sistema incrementa el stock, recalcula el
            costo promedio y registra los movimientos automáticamente.
        </span>
    </div>

    <section class="panel {{ $notas->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($notas->count() > 0)
        <div class="table-wrap table-wrap--wide table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--actions data-table--entries data-table--responsive">
                <thead>
                    <tr>
                        <th class="table-sticky--start">Nota</th>
                        <th class="table-priority--medium">Fecha</th>
                        <th class="table-priority--medium">Orden de compra</th>
                        <th>Proveedor</th>
                        <th class="table-priority--low">Guía / factura</th>
                        <th class="table-priority--low">Productos</th>
                        <th class="table-priority--medium">Cantidad</th>
                        <th>Importe</th>
                        <th>Estado</th>
                        <th class="table-sticky--end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($notas as $nota)
                        @php
                            $estadoClase = match ($nota->estado) { 'CONFIRMADA' => 'success', 'ANULADA' => 'danger', default => 'warning' };
                            $detailsId = 'nota-ingreso-detalles-' . $nota->id;
                        @endphp
                        <tr>
                            <td class="table-sticky--start">
                                <a href="{{ route('notas-ingreso.show', $nota->id) }}" class="table-primary-link">{{ $nota->codigo }}</a>
                                <span>Por {{ $nota->registrador?->username ?? 'Usuario no disponible' }}</span>
                            </td>
                            <td class="table-date table-priority--medium"><strong>{{ $nota->fecha_ingreso?->format('d/m/Y') }}</strong><span>{{ $nota->created_at?->format('H:i') }}</span></td>
                            <td class="table-priority--medium"><strong>{{ $nota->ordenCompra?->codigo ?? '—' }}</strong><span>{{ $nota->ordenCompra?->estado ?? 'Sin estado' }}</span></td>
                            <td><strong>{{ $nota->ordenCompra?->proveedor?->razon_social ?? '—' }}</strong><span>{{ $nota->ordenCompra?->proveedor?->ruc ?? 'Sin RUC' }}</span></td>
                            <td class="table-priority--low"><strong>{{ $nota->numero_guia_remision ?: 'Sin guía' }}</strong><span>@if ($nota->facturaProveedor) {{ $nota->facturaProveedor->serie }}-{{ $nota->facturaProveedor->numero }} @else Sin factura vinculada @endif</span></td>
                            <td class="table-priority--low">{{ (int) $nota->detalles_count }}</td>
                            <td class="table-priority--medium"><x-ui.quantity :value="$nota->cantidad_total ?? 0" /></td>
                            <td>S/ {{ number_format((float) ($nota->importe_total ?? 0), 2, '.', ',') }}</td>
                            <td><span class="badge badge--{{ $estadoClase }}">{{ $nota->estado }}</span></td>
                            <td class="table-sticky--end">
                                <div class="table-actions">
                                    <a href="{{ route('notas-ingreso.show', $nota->id) }}" class="icon-button" title="Ver nota de ingreso" aria-label="Ver nota de ingreso"><x-ui.icon name="eye" :size="17" /></a>
                                    <x-ui.table-details-toggle :target="$detailsId" label="Ver más datos de la nota {{ $nota->codigo }}" />
                                </div>
                            </td>
                        </tr>
                        <x-ui.table-row-details :id="$detailsId" :colspan="10">
                            <dl class="table-details-grid">
                                <div class="table-detail--medium"><dt>Fecha</dt><dd>{{ $nota->fecha_ingreso?->format('d/m/Y') }} {{ $nota->created_at?->format('H:i') }}</dd></div>
                                <div class="table-detail--medium"><dt>Orden de compra</dt><dd>{{ $nota->ordenCompra?->codigo ?? '—' }}</dd></div>
                                <div class="table-detail--low"><dt>Guía</dt><dd>{{ $nota->numero_guia_remision ?: 'Sin guía' }}</dd></div>
                                <div class="table-detail--low"><dt>Factura</dt><dd>@if ($nota->facturaProveedor) {{ $nota->facturaProveedor->serie }}-{{ $nota->facturaProveedor->numero }} @else Sin factura vinculada @endif</dd></div>
                                <div class="table-detail--low"><dt>Productos</dt><dd>{{ (int) $nota->detalles_count }}</dd></div>
                                <div class="table-detail--medium"><dt>Cantidad total</dt><dd><x-ui.quantity :value="$nota->cantidad_total ?? 0" /></dd></div>
                            </dl>
                        </x-ui.table-row-details>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-ui.pagination :paginator="$notas" />
        @else
            <div class="empty-table-state">
                <span class="empty-state__icon"><x-ui.icon name="entry" :size="30" /></span>
                <strong>Aún no hay notas de ingreso</strong>
                <span>Registra la recepción de una orden de compra para crear inventario y movimientos reales.</span>
                <div class="empty-table-state__actions">
                    <a href="{{ route('notas-ingreso.create') }}" class="button button--primary button--small">
                        <x-ui.icon name="plus" :size="16" />
                        Registrar primera nota
                    </a>
                </div>
            </div>
        @endif
    </section>
    </div>
@endsection
