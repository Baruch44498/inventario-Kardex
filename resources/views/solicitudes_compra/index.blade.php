@extends('layouts.app')

@section('title', 'Solicitudes de compra')
@section('page-kicker', 'Compras')
@section('page-title', 'Solicitudes de compra')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">Puente con Contabilidad</p>
            <h1>Solicitudes de compra</h1>
            <p>La cotización elegida llega aquí para revisión. Solo una solicitud aprobada podrá convertirse en orden de compra.</p>
        </div>
        @unless ($esContabilidad)
            <a href="{{ route('historial-precios.index', ['solo_utilizables' => 1]) }}" class="button button--primary">
                <x-ui.icon name="search" :size="17" /> Buscar cotización
            </a>
        @endunless
    </section>

    <section class="summary-strip summary-strip--four">
        @foreach ([
            ['Pendientes', 'warning', 'clipboard', $resumen['pendientes']],
            ['Aprobadas', 'success', 'check-circle', $resumen['aprobadas']],
            ['Rechazadas', 'danger', 'error', $resumen['rechazadas']],
            ['Convertidas', 'info', 'purchase-order', $resumen['convertidas']],
        ] as [$titulo, $tono, $icono, $valor])
            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--{{ $tono }}"><x-ui.icon :name="$icono" :size="20" /></span>
                <div><span>{{ $titulo }}</span><strong>{{ $valor }}</strong></div>
            </article>
        @endforeach
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('solicitudes-compra.index') }}" class="purchase-approval-filter">
            <label class="form-field purchase-approval-filter__search">
                <span>Buscar</span>
                <div class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Solicitud, cotización, documento o proveedor">
                </div>
            </label>
            <label class="form-field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    @foreach (['PENDIENTE' => 'Pendiente de Contabilidad', 'APROBADA' => 'Aprobada para compra', 'RECHAZADA' => 'Rechazada', 'CONVERTIDA' => 'Convertida en orden', 'ANULADA' => 'Anulada'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </label>
            <div class="filter-actions">
                <button class="button button--primary" type="submit"><x-ui.icon name="filter" :size="17" /> Filtrar</button>
                <a class="button button--ghost" href="{{ route('solicitudes-compra.index') }}">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="panel">
        @if ($solicitudes->isNotEmpty())
            <div class="table-wrap">
                <table class="data-table purchase-approval-table">
                    <thead><tr><th>Solicitud</th><th>Cotización</th><th>Proveedor</th><th>Fecha</th><th>Productos</th><th class="text-right">Total seleccionado</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody>
                        @foreach ($solicitudes as $solicitud)
                            <tr>
                                <td><strong>{{ $solicitud->codigo }}</strong><span>Por {{ $solicitud->solicitante?->nombreVisible() ?? '—' }}</span></td>
                                <td><strong>{{ $solicitud->cotizacion?->codigo ?? '—' }}</strong><span>{{ $solicitud->cotizacion?->numero_documento ?: 'Sin documento externo' }}</span></td>
                                <td>{{ $solicitud->cotizacion?->proveedor?->nombreVisible() ?? '—' }}</td>
                                <td>{{ $solicitud->fecha_solicitud?->format('d/m/Y') }}</td>
                                <td>{{ $solicitud->detalles_count }}</td>
                                <td class="text-right">
                                    <strong><x-ui.money :value="$solicitud->total_seleccionado" :currency="$solicitud->cotizacion?->moneda ?? 'PEN'" /></strong>
                                    @if (round((float) $solicitud->total_seleccionado, 2) !== round((float) $solicitud->cotizacion?->total, 2))
                                        <span>Documento completo: <x-ui.money :value="$solicitud->cotizacion?->total" :currency="$solicitud->cotizacion?->moneda ?? 'PEN'" /></span>
                                    @endif
                                </td>
                                <td><span class="badge badge--{{ $solicitud->estadoClase() }}">{{ $solicitud->estadoVisible() }}</span></td>
                                <td><a class="button button--ghost button--small" href="{{ route('solicitudes-compra.show', $solicitud) }}">Revisar</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$solicitudes" />
        @else
            <div class="empty-table-state">
                <span class="empty-state__icon"><x-ui.icon name="clipboard" :size="28" /></span>
                <strong>No hay solicitudes con estos filtros</strong>
                <span>Las cotizaciones seleccionadas por Compras aparecerán aquí.</span>
            </div>
        @endif
    </section>
@endsection
