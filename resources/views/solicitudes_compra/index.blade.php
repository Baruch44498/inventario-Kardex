@extends('layouts.app')

@section('title', 'Compras aprobadas')
@section('page-kicker', 'Compras')
@section('page-title', 'Compras aprobadas')

@section('content')
    <section class="module-header">
        <div>
            <p class="eyebrow">{{ $esContabilidad ? 'Consulta contable' : 'Trazabilidad de Compras' }}</p>
            <h1>Compras aprobadas</h1>
            <p>{{ $esContabilidad ? 'Consulta las cotizaciones y órdenes aprobadas por Compras. Los pagos se registran después en la factura del proveedor.' : 'Consulta las decisiones tomadas por Compras y la orden que se generó para cada cotización.' }}</p>
        </div>
        @unless ($esContabilidad)
            <a href="{{ route('historial-precios.index', ['solo_utilizables' => 1]) }}" class="button button--primary">
                <x-ui.icon name="search" :size="17" /> Buscar cotización
            </a>
        @endunless
    </section>

    <section class="summary-strip summary-strip--four">
        @foreach ([
            ['Registros anteriores', 'warning', 'clipboard', $resumen['pendientes']],
            ['Aprobadas', 'success', 'check-circle', $resumen['aprobadas']],
            ['No utilizadas anteriores', 'danger', 'error', $resumen['rechazadas']],
            ['Con orden', 'info', 'purchase-order', $resumen['convertidas']],
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
                    @foreach (['PENDIENTE' => 'Registro anterior pendiente de OC', 'APROBADA' => 'Aprobada para compra', 'RECHAZADA' => 'No utilizada (anterior)', 'CONVERTIDA' => 'Con orden de compra', 'ANULADA' => 'Anulada'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected((request('estado') ?: ($esContabilidad ? 'CONVERTIDA' : '')) === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-field">
                <span>Origen</span>
                <select name="origen">
                    <option value="">Todos</option>
                    @foreach (['REQUERIMIENTO' => 'Desde requerimiento', 'COMPRA_DIRECTA' => 'Compra directa', 'REGULARIZACION' => 'Regularización', 'URGENTE' => 'Compra urgente', 'REPOSICION' => 'Reposición directa'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected(request('origen') === $valor)>{{ $texto }}</option>
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
            <div class="table-wrap table-wrap--cards">
                <table class="data-table purchase-approval-table data-table--cards" role="table">
                    <thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Solicitud</th><th role="columnheader" scope="col">Origen</th><th role="columnheader" scope="col">Cotización</th><th role="columnheader" scope="col">Proveedor</th><th role="columnheader" scope="col">Fecha</th><th role="columnheader" scope="col">Productos</th><th role="columnheader" scope="col" class="text-right">Total seleccionado</th><th role="columnheader" scope="col">Estado</th><th role="columnheader" scope="col">Acción</th></tr></thead>
                    <tbody role="rowgroup">
                        @foreach ($solicitudes as $solicitud)
                            <tr role="row">
                                <td class="table-card__wide" role="cell" data-label="Solicitud"><strong>{{ $solicitud->codigo }}</strong><span>Por {{ $solicitud->solicitante?->nombreVisible() ?? '—' }}</span></td>
                                <td role="cell" data-label="Origen"><span class="badge badge--{{ $solicitud->origenClase() }}">{{ $solicitud->origenVisible() }}</span></td>
                                <td role="cell" data-label="Cotización"><strong>{{ $solicitud->cotizacion?->codigo ?? '—' }}</strong><span>{{ $solicitud->cotizacion?->numero_documento ?: 'Sin documento externo' }}</span></td>
                                <td class="table-card__wide" role="cell" data-label="Proveedor">{{ $solicitud->cotizacion?->proveedor?->nombreVisible() ?? '—' }}</td>
                                <td role="cell" data-label="Fecha">{{ $solicitud->fecha_solicitud?->format('d/m/Y') }}</td>
                                <td role="cell" data-label="Productos">{{ $solicitud->detalles_count }}</td>
                                <td class="text-right" role="cell" data-label="Total seleccionado">
                                    <strong><x-ui.money :value="$solicitud->total_seleccionado" :currency="$solicitud->cotizacion?->moneda ?? 'PEN'" /></strong>
                                    @if (round((float) $solicitud->total_seleccionado, 2) !== round((float) $solicitud->cotizacion?->total, 2))
                                        <span>Documento completo: <x-ui.money :value="$solicitud->cotizacion?->total" :currency="$solicitud->cotizacion?->moneda ?? 'PEN'" /></span>
                                    @endif
                                </td>
                                <td role="cell" data-label="Estado"><span class="badge badge--{{ $solicitud->estadoClase() }}">{{ $solicitud->estadoVisible() }}</span></td>
                                <td class="table-card__wide" role="cell" data-label="Acción"><a class="button button--ghost button--small" href="{{ route('solicitudes-compra.show', $solicitud) }}">Consultar</a></td>
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
                <span>Las cotizaciones aprobadas por Compras aparecerán aquí.</span>
            </div>
        @endif
    </section>
@endsection
