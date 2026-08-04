@extends('layouts.app')

@section('title', 'Proformas')
@section('page-kicker', 'Ventas')
@section('page-title', 'Proformas')

@section('content')
    <x-ui.page-header
        kicker="Almacén"
        title="Proformas de venta directa"
        description="Almacén prepara la solicitud; Logística la convierte en cotización y posteriormente en una OV."
    >
        @if (auth()->user()->puede('proformas.crear'))
            <x-slot:actions>
                <a href="{{ route('proformas.create') }}" class="button button--primary">
                    <x-ui.icon name="plus" :size="18" /> Nueva proforma
                </a>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    <section class="summary-strip summary-strip--four">
        @foreach ([
            ['Pendientes en Logística', 'warning', 'warning', $resumen['pendientes']],
            ['Cotizaciones abiertas', 'edit', 'info', $resumen['abiertas']],
            ['Cotizaciones cerradas', 'check-circle', 'success', $resumen['cerradas']],
            ['Proformas anuladas', 'error', 'danger', $resumen['anuladas']],
        ] as [$titulo, $icono, $tono, $valor])
            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--{{ $tono }}">
                    <x-ui.icon :name="$icono" :size="21" />
                </span>
                <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
            </article>
        @endforeach
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('proformas.index') }}" class="commercial-filter">
            <label class="form-field commercial-filter__search">
                <span>Buscar</span>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span>
                    <input type="search" name="q" value="{{ request('q') }}"
                        placeholder="Código, cliente o documento">
                </div>
            </label>
            <label class="form-field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    @foreach (App\Models\Proforma::ESTADOS as $estado)
                        <option value="{{ $estado }}" @selected(request('estado') === $estado)>
                            {{ str_replace('_', ' ', $estado) }}
                        </option>
                    @endforeach
                </select>
            </label>
            <div class="filter-actions">
                <button class="button button--primary" type="submit">
                    <x-ui.icon name="filter" :size="17" /> Filtrar
                </button>
                <a href="{{ route('proformas.index') }}" class="button button--ghost">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="panel {{ $proformas->isEmpty() ? 'panel--empty-list' : '' }}">
        @if ($proformas->isNotEmpty())
            <div class="table-wrap table-wrap--responsive">
                <table class="data-table data-table--actions">
                    <thead>
                        <tr>
                            <th>Proforma</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Productos</th>
                            <th>Cotizaciones</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($proformas as $proforma)
                            @php
                                $tono = match ($proforma->estado) {
                                    'ANULADA' => 'danger',
                                    'COTIZADA', 'CONVERTIDA_EN_ORDEN' => 'success',
                                    'ENVIADA_A_LOGISTICA' => 'warning',
                                    default => 'info',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <a class="table-primary-link" href="{{ route('proformas.show', $proforma) }}">
                                        {{ $proforma->codigo }}
                                    </a>
                                    <span>{{ $proforma->moneda }}</span>
                                </td>
                                <td>
                                    <strong>{{ $proforma->cliente?->nombreVisible() ?: 'Cliente no disponible' }}</strong>
                                    <span>{{ $proforma->cliente?->documentoVisible() ?: 'Sin documento' }}</span>
                                </td>
                                <td>{{ $proforma->fecha_emision->format('d/m/Y') }}</td>
                                <td>{{ $proforma->detalles_count }}</td>
                                <td>{{ $proforma->cotizaciones_cliente_count }}</td>
                                <td><x-ui.status-badge :tone="$tono">{{ str_replace('_', ' ', $proforma->estado) }}</x-ui.status-badge></td>
                                <td><a href="{{ route('proformas.show', $proforma) }}" class="button button--ghost button--small">Ver detalle</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$proformas" />
        @else
            <div class="empty-table-state">
                <span class="empty-state__icon"><x-ui.icon name="quotes" :size="30" /></span>
                <strong>No hay proformas para mostrar</strong>
                <span>Almacén puede registrar la primera solicitud de cotización.</span>
            </div>
        @endif
    </section>
@endsection
