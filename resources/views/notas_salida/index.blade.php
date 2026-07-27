@extends('layouts.app')

@section('title', 'Notas de salida')
@section('page-kicker', 'Almacén')
@section('page-title', 'Notas de salida')

@section('content')
    <div class="document-list-page">
    <section class="module-header">
        <div>
            <p class="eyebrow">Despacho de materiales</p>
            <h1>Notas de salida</h1>
            <p>
                Registra la entrega de productos para una orden de operación y
                descuenta las existencias con trazabilidad completa.
            </p>
        </div>

        <a href="{{ route('notas-salida.create') }}" class="button button--primary">
            <x-ui.icon name="plus" :size="18" />
            Nueva nota de salida
        </a>
    </section>

    <section class="summary-strip summary-strip--four" aria-label="Resumen de notas de salida">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--neutral">
                <x-ui.icon name="exit" :size="21" />
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
            <span class="summary-strip__icon summary-strip__icon--danger">
                <x-ui.icon name="error" :size="21" />
            </span>
            <div>
                <span>Anuladas</span>
                <strong>{{ (int) ($resumen->anuladas ?? 0) }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info">
                <x-ui.icon name="banknote" :size="21" />
            </span>
            <div>
                <span>Valor entregado</span>
                <strong>S/ {{ number_format($valorEntregado, 2, '.', ',') }}</strong>
            </div>
        </article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('notas-salida.index') }}" class="filter-grid filter-grid--entries">
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
                        placeholder="Nota, orden, cliente, vehículo o receptor"
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

                <a href="{{ route('notas-salida.index') }}" class="button button--ghost">
                    Limpiar
                </a>
            </div>
        </form>
    </section>

    <div class="notice notice--info notice--block">
        <x-ui.icon name="info" :size="18" />
        <span>
            Al confirmar una salida, el sistema descuenta el stock, registra el
            costo promedio aplicado, genera movimientos y evalúa las alertas.
        </span>
    </div>

    <section class="panel {{ $notas->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($notas->count() > 0)
            <div class="table-wrap table-wrap--wide table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--actions data-table--responsive output-list-table">
                    <thead>
                        <tr>
                            <th class="table-sticky--start">Nota</th>
                            <th class="table-priority--medium">Fecha</th>
                            <th class="table-priority--medium">Orden</th>
                            <th>Cliente / vehículo</th>
                            <th class="table-priority--low">Entregado a</th>
                            <th class="table-priority--low">Productos</th>
                            <th class="table-priority--medium">Cantidad</th>
                            <th>Valor</th>
                            <th>Estado</th>
                            <th class="table-sticky--end">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($notas as $nota)
                            @php
                                $estadoClase = match ($nota->estado) {
                                    'CONFIRMADA' => 'success',
                                    'ANULADA' => 'danger',
                                    default => 'warning',
                                };
                                $detailsId = 'nota-salida-detalles-' . $nota->id;
                                $cliente = $nota->ordenOperacion?->cliente?->razon_social ?? 'Sin cliente';
                                $vehiculo = $nota->ordenOperacion?->vehiculo?->placa
                                    ?? $nota->ordenOperacion?->vehiculo?->codigo_interno
                                    ?? 'Sin vehículo';
                            @endphp
                            <tr>
                                <td class="table-sticky--start">
                                    <a
                                        href="{{ route('notas-salida.show', $nota->id) }}"
                                        class="table-primary-link"
                                    >
                                        {{ $nota->codigo }}
                                    </a>
                                    <span>Por {{ $nota->registrador?->username ?? 'Usuario no disponible' }}</span>
                                </td>
                                <td class="table-date table-priority--medium">
                                    <strong>{{ $nota->fecha_salida?->format('d/m/Y') }}</strong>
                                    <span>{{ $nota->created_at?->format('H:i') }}</span>
                                </td>
                                <td class="table-priority--medium">
                                    <strong>{{ $nota->ordenOperacion?->codigo_orden ?? '—' }}</strong>
                                    <span>{{ $nota->ordenOperacion?->tipoOrden?->codigo ?? 'Sin tipo' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $cliente }}</strong>
                                    <span>{{ $vehiculo }}</span>
                                </td>
                                <td class="table-priority--low">
                                    {{ $nota->entregado_a ?: 'No registrado' }}
                                </td>
                                <td class="table-priority--low">
                                    {{ (int) $nota->detalles_count }}
                                </td>
                                <td class="table-priority--medium">
                                    <x-ui.quantity :value="$nota->cantidad_total ?? 0" />
                                </td>
                                <td>
                                    S/ {{ number_format((float) ($nota->importe_total ?? 0), 2, '.', ',') }}
                                </td>
                                <td>
                                    <span class="badge badge--{{ $estadoClase }}">
                                        {{ $nota->estado }}
                                    </span>
                                </td>
                                <td class="table-sticky--end">
                                    <div class="table-actions">
                                        <a
                                            href="{{ route('notas-salida.show', $nota->id) }}"
                                            class="icon-button"
                                            title="Ver nota de salida"
                                            aria-label="Ver nota de salida"
                                        >
                                            <x-ui.icon name="eye" :size="17" />
                                        </a>
                                        <x-ui.table-details-toggle
                                            :target="$detailsId"
                                            label="Ver más datos de la nota {{ $nota->codigo }}"
                                        />
                                    </div>
                                </td>
                            </tr>

                            <x-ui.table-row-details :id="$detailsId" :colspan="10">
                                <dl class="table-details-grid">
                                    <div class="table-detail--medium">
                                        <dt>Fecha</dt>
                                        <dd>{{ $nota->fecha_salida?->format('d/m/Y') }} {{ $nota->created_at?->format('H:i') }}</dd>
                                    </div>
                                    <div class="table-detail--medium">
                                        <dt>Orden</dt>
                                        <dd>{{ $nota->ordenOperacion?->codigo_orden ?? '—' }}</dd>
                                    </div>
                                    <div class="table-detail--low">
                                        <dt>Entregado a</dt>
                                        <dd>{{ $nota->entregado_a ?: 'No registrado' }}</dd>
                                    </div>
                                    <div class="table-detail--low">
                                        <dt>Productos</dt>
                                        <dd>{{ (int) $nota->detalles_count }}</dd>
                                    </div>
                                    <div class="table-detail--medium">
                                        <dt>Cantidad total</dt>
                                        <dd><x-ui.quantity :value="$nota->cantidad_total ?? 0" /></dd>
                                    </div>
                                </dl>
                            </x-ui.table-row-details>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$notas" />
        @else
            <div class="empty-table-state">
                <span class="empty-state__icon">
                    <x-ui.icon name="exit" :size="30" />
                </span>
                <strong>Aún no hay notas de salida</strong>
                <span>
                    Registra la entrega de materiales para una orden de operación.
                </span>
                <div class="empty-table-state__actions">
                    <a
                        href="{{ route('notas-salida.create') }}"
                        class="button button--primary button--small"
                    >
                        <x-ui.icon name="plus" :size="16" />
                        Registrar primera salida
                    </a>
                </div>
            </div>
        @endif
    </section>
    </div>
@endsection
