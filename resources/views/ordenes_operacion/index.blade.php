@extends('layouts.app')

@section('title', 'Órdenes de operación')
@section('page-kicker', 'Almacén')
@section('page-title', 'Órdenes de operación')

@section('content')
    <div class="operation-page operation-page--index">
        <section class="module-header operation-index-header">
            <div>
                <p class="eyebrow">OP · OS · OM · OV</p>
                <h1>Órdenes de operación</h1>
                <p>
                    Organiza los trabajos que originan requisiciones y consumos de almacén.
                </p>
            </div>

            @if (auth()->user()->puedeAlguno(
                'ordenes.crear_comercial',
                'ordenes.crear_venta'
            ))
                <a
                    href="{{ route('ordenes-operacion.create') }}"
                    class="button button--primary operation-index-header__action"
                >
                    <x-ui.icon name="plus" :size="18" />
                    Nueva orden
                </a>
            @endif
        </section>

        <section
            class="summary-strip summary-strip--four operation-summary"
            aria-label="Resumen de órdenes"
        >
            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--neutral">
                    <x-ui.icon name="orders" :size="21" />
                </span>
                <div>
                    <span>Total</span>
                    <strong>{{ (int) ($resumen->total ?? 0) }}</strong>
                </div>
            </article>

            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--info">
                    <x-ui.icon name="clipboard" :size="21" />
                </span>
                <div>
                    <span>Abiertas</span>
                    <strong>{{ (int) ($resumen->abiertas ?? 0) }}</strong>
                </div>
            </article>

            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--warning">
                    <x-ui.icon name="activity" :size="21" />
                </span>
                <div>
                    <span>En proceso</span>
                    <strong>{{ (int) ($resumen->en_proceso ?? 0) }}</strong>
                </div>
            </article>

            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--success">
                    <x-ui.icon name="check-circle" :size="21" />
                </span>
                <div>
                    <span>Cerradas</span>
                    <strong>{{ (int) ($resumen->cerradas ?? 0) }}</strong>
                </div>
            </article>
        </section>

        <section class="panel filter-panel operation-filter-panel">
            <form
                method="GET"
                action="{{ route('ordenes-operacion.index') }}"
                class="operation-filter-grid"
            >
                <label class="form-field operation-filter-grid__search">
                    <span>Buscar</span>
                    <div class="input-with-icon">
                        <span class="input-with-icon__symbol">
                            <x-ui.icon name="search" :size="17" />
                        </span>
                        <input
                            type="search"
                            name="q"
                            value="{{ request('q') }}"
                            placeholder="Código, descripción, cliente o vehículo"
                        >
                    </div>
                </label>

                <label class="form-field">
                    <span>Tipo</span>
                    <select name="tipo">
                        <option value="">Todos</option>
                        @foreach ($tipos as $tipo)
                            <option
                                value="{{ $tipo->id }}"
                                @selected((int) request('tipo') === $tipo->id)
                            >
                                {{ $tipo->codigo }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="form-field">
                    <span>Estado</span>
                    <select name="estado">
                        <option value="">Todos</option>
                        @foreach ([
                            'ABIERTA' => 'Abierta',
                            'EN_PROCESO' => 'En proceso',
                            'CERRADA' => 'Cerrada',
                            'ANULADA' => 'Anulada',
                        ] as $valor => $nombre)
                            <option
                                value="{{ $valor }}"
                                @selected(request('estado') === $valor)
                            >
                                {{ $nombre }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="form-field">
                    <span>Desde</span>
                    <input
                        type="date"
                        name="desde"
                        value="{{ request('desde') }}"
                    >
                </label>

                <label class="form-field">
                    <span>Hasta</span>
                    <input
                        type="date"
                        name="hasta"
                        value="{{ request('hasta') }}"
                    >
                </label>

                <div class="filter-actions operation-filter-actions">
                    <button type="submit" class="button button--primary">
                        <x-ui.icon name="filter" :size="17" />
                        Filtrar
                    </button>

                    <a
                        href="{{ route('ordenes-operacion.index') }}"
                        class="button button--ghost"
                    >
                        Limpiar
                    </a>
                </div>
            </form>
        </section>

        <div class="notice notice--info notice--block operation-list-notice">
            <x-ui.icon name="info" :size="18" />
            <span>
                Las órdenes cerradas y anuladas se conservan como registros de solo lectura.
            </span>
        </div>

        <section
            class="panel operation-table-panel {{ $ordenes->count() === 0 ? 'panel--empty-list' : '' }}"
        >
            @if ($ordenes->count() > 0)
                <div
                    class="table-wrap table-wrap--wide table-wrap--responsive operation-table-wrap"
                    data-responsive-table
                >
                    <table
                        class="data-table data-table--actions data-table--responsive operation-list-table"
                    >
                        <thead>
                            <tr>
                                <th class="table-sticky--start">Orden</th>
                                <th>Tipo</th>
                                <th class="table-priority--medium">Apertura</th>
                                <th>Cliente / vehículo</th>
                                <th class="table-priority--low">Descripción</th>
                                <th class="table-priority--low">Requisiciones</th>
                                <th class="table-priority--medium">Salidas</th>
                                <th>Estado</th>
                                <th class="table-sticky--end">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($ordenes as $orden)
                                @php
                                    $estadoClase = match ($orden->estado) {
                                        'ABIERTA' => 'info',
                                        'EN_PROCESO' => 'warning',
                                        'CERRADA' => 'success',
                                        'ANULADA' => 'danger',
                                        default => 'neutral',
                                    };

                                    $vehiculo = $orden->vehiculo?->placa
                                        ?? 'Sin vehículo';

                                    $detailsId = 'orden-detalles-' . $orden->id;
                                    $descripcion = trim((string) $orden->descripcion);
                                    $tieneDescripcion = $descripcion !== ''
                                        && ! preg_match('/^[\s\-_.]+$/u', $descripcion);
                                @endphp

                                <tr>
                                    <td class="table-sticky--start">
                                        <a
                                            href="{{ route('ordenes-operacion.show', $orden->id) }}"
                                            class="table-primary-link"
                                        >
                                            {{ $orden->codigo_orden }}
                                        </a>
                                        <span>
                                            Por {{ $orden->creador?->username ?? '—' }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="type-chip">
                                            {{ $orden->tipoOrden?->codigo ?? '—' }}
                                        </span>
                                    </td>

                                    <td class="table-priority--medium">
                                        {{ $orden->fecha_apertura?->format('d/m/Y') }}
                                    </td>

                                    <td>
                                        <strong>
                                            {{ $orden->cliente?->razon_social ?? 'Sin cliente' }}
                                        </strong>
                                        <span>{{ $vehiculo }}</span>
                                    </td>

                                    <td class="table-priority--low operation-description-cell">
                                        {{ $tieneDescripcion ? $descripcion : 'Sin descripción' }}
                                    </td>

                                    <td class="table-priority--low">
                                        {{ (int) $orden->requisiciones_count }}
                                    </td>

                                    <td class="table-priority--medium">
                                        {{ (int) $orden->notas_salida_count }}
                                    </td>

                                    <td>
                                        <span class="badge badge--{{ $estadoClase }}">
                                            {{ str_replace('_', ' ', $orden->estado) }}
                                        </span>
                                    </td>

                                    <td class="table-sticky--end">
                                        <div class="table-actions">
                                            <a
                                                href="{{ route('ordenes-operacion.show', $orden->id) }}"
                                                class="icon-button"
                                                title="Ver orden"
                                                aria-label="Ver orden"
                                            >
                                                <x-ui.icon name="eye" :size="17" />
                                            </a>

                                            @php
                                                $puedeEditarFila =
                                                    auth()->user()->esAdministrador()
                                                    || (
                                                        $orden->tipoOrden?->codigo === 'OV'
                                                        && auth()->user()->puede('ordenes.editar_venta')
                                                    )
                                                    || (
                                                        $orden->tipoOrden?->codigo !== 'OV'
                                                        && auth()->user()->puede('ordenes.editar_comercial')
                                                    );
                                            @endphp

                                            @if ($orden->puedeEditar() && $puedeEditarFila)
                                                <a
                                                    href="{{ route('ordenes-operacion.edit', $orden->id) }}"
                                                    class="icon-button"
                                                    title="Editar orden"
                                                    aria-label="Editar orden"
                                                >
                                                    <x-ui.icon name="edit" :size="17" />
                                                </a>
                                            @endif

                                            <x-ui.table-details-toggle
                                                :target="$detailsId"
                                                label="Ver más datos de {{ $orden->codigo_orden }}"
                                            />
                                        </div>
                                    </td>
                                </tr>

                                <x-ui.table-row-details :id="$detailsId" :colspan="9">
                                    <dl class="table-details-grid">
                                        <div class="table-detail--medium">
                                            <dt>Apertura</dt>
                                            <dd>{{ $orden->fecha_apertura?->format('d/m/Y') }}</dd>
                                        </div>
                                        <div class="table-detail--low">
                                            <dt>Descripción</dt>
                                            <dd>{{ $tieneDescripcion ? $descripcion : 'Sin descripción' }}</dd>
                                        </div>
                                        <div class="table-detail--low">
                                            <dt>Requisiciones</dt>
                                            <dd>{{ (int) $orden->requisiciones_count }}</dd>
                                        </div>
                                        <div class="table-detail--medium">
                                            <dt>Salidas</dt>
                                            <dd>{{ (int) $orden->notas_salida_count }}</dd>
                                        </div>
                                    </dl>
                                </x-ui.table-row-details>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-ui.pagination :paginator="$ordenes" />
            @else
                <div class="empty-table-state">
                    <span class="empty-state__icon">
                        <x-ui.icon name="orders" :size="30" />
                    </span>
                    <strong>Aún no hay órdenes de operación</strong>
                    <span>
                        Crea una orden OP, OS, OM u OV para iniciar el flujo operativo.
                    </span>
                    <div class="empty-table-state__actions">
                        <a
                            href="{{ route('ordenes-operacion.create') }}"
                            class="button button--primary button--small"
                        >
                            <x-ui.icon name="plus" :size="16" />
                            Registrar primera orden
                        </a>
                    </div>
                </div>
            @endif
        </section>
    </div>
@endsection
