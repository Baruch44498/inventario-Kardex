@extends('layouts.app')

@section('title', 'Historial del vehículo')
@section('page-kicker', 'Clientes')
@section('page-title', 'Historial del vehículo')

@section('content')
    <a
        href="{{ route('clientes.show', $cliente->id) }}"
        class="back-link"
    >
        <x-ui.icon name="arrow-left" :size="17" />
        Volver al cliente
    </a>

    <section class="module-header">
        <div>
            <p class="eyebrow">
                {{ $cliente->nombreVisible() }}
            </p>
            <h1>{{ $vehiculo->placa }}</h1>
            <p>
                {{ $vehiculo->descripcionVisible() }}
                @if ($vehiculo->anio || $vehiculo->color)
                    ·
                    {{ collect([
                        $vehiculo->anio,
                        $vehiculo->color,
                    ])->filter()->implode(' · ') }}
                @endif
            </p>
        </div>

        @unless ($vehiculo->es_comodin)
            <a
                href="{{ route(
                    'clientes.vehiculos.edit',
                    [$cliente->id, $vehiculo->id]
                ) }}"
                class="button button--primary"
            >
                <x-ui.icon name="edit" :size="17" />
                Editar vehículo
            </a>
        @endunless
    </section>

    <section class="summary-strip summary-strip--four">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--neutral">
                <x-ui.icon name="orders" :size="21" />
            </span>
            <div>
                <span>Total de órdenes</span>
                <strong>{{ $resumen['total'] }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info">
                <x-ui.icon name="clipboard" :size="21" />
            </span>
            <div>
                <span>Abiertas</span>
                <strong>{{ $resumen['abiertas'] }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--warning">
                <x-ui.icon name="activity" :size="21" />
            </span>
            <div>
                <span>En proceso</span>
                <strong>{{ $resumen['en_proceso'] }}</strong>
            </div>
        </article>

        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success">
                <x-ui.icon name="check-circle" :size="21" />
            </span>
            <div>
                <span>Cerradas</span>
                <strong>{{ $resumen['cerradas'] }}</strong>
            </div>
        </article>
    </section>

    <section class="panel vehicle-history-panel">
        <header class="vehicle-history-panel__heading">
            <div>
                <p class="eyebrow">Trazabilidad operativa</p>
                <h2>Órdenes vinculadas a la placa</h2>
                <p>
                    El historial se conserva mediante la relación entre
                    el vehículo y cada orden de operación.
                </p>
            </div>

            <span class="badge badge--{{ $vehiculo->estado
                ? 'success'
                : 'danger' }}">
                {{ $vehiculo->estado ? 'ACTIVO' : 'INACTIVO' }}
            </span>
        </header>

        @if ($ordenes->count() > 0)
            <div class="table-wrap">
                <table class="data-table data-table--actions">
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Tipo</th>
                            <th>Apertura</th>
                            <th>Trabajo</th>
                            <th>Salidas</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ordenes as $orden)
                            @php
                                $tonoEstado = match ($orden->estado) {
                                    'ABIERTA' => 'info',
                                    'EN_PROCESO' => 'warning',
                                    'CERRADA' => 'success',
                                    default => 'danger',
                                };
                            @endphp

                            <tr>
                                <td>
                                    <strong>
                                        {{ $orden->codigo_orden }}
                                    </strong>
                                </td>
                                <td>
                                    {{ $orden->tipoOrden?->codigo ?? '—' }}
                                </td>
                                <td>
                                    {{ $orden->fecha_apertura?->format(
                                        'd/m/Y'
                                    ) ?? '—' }}
                                </td>
                                <td>
                                    <span>
                                        {{ \Illuminate\Support\Str::limit(
                                            $orden->descripcion
                                                ?: 'Sin descripción',
                                            90
                                        ) }}
                                    </span>
                                </td>
                                <td>
                                    {{ $orden->notas_salida_count }}
                                </td>
                                <td>
                                    <span class="badge badge--{{ $tonoEstado }}">
                                        {{ str_replace(
                                            '_',
                                            ' ',
                                            $orden->estado
                                        ) }}
                                    </span>
                                </td>
                                <td>
                                    <a
                                        href="{{ route(
                                            'ordenes-operacion.show',
                                            $orden->id
                                        ) }}"
                                        class="button button--ghost button--small"
                                    >
                                        Ver orden
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-ui.pagination :paginator="$ordenes" />
        @else
            <div class="vehicle-history-empty">
                <span class="vehicle-history-empty__icon">
                    <x-ui.icon name="orders" :size="29" />
                </span>
                <div class="vehicle-history-empty__copy">
                    <strong>Sin órdenes vinculadas</strong>
                    <p>
                        Las órdenes futuras asociadas a esta placa aparecerán
                        automáticamente en este historial.
                    </p>
                </div>
            </div>
        @endif
    </section>
@endsection
