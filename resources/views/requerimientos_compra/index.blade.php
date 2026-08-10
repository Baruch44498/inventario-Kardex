@extends('layouts.app')

@section('title', 'Requerimientos de compra')
@section('page-kicker', $esLogistica && ! $puedeCrear ? 'Compras y proveedores' : 'Almacén')
@section('page-title', 'Requerimientos de compra')

@section('content')
<div class="purchase-requirement-list-page">
    <section class="module-header">
        <div>
            <p class="eyebrow">Almacén → Logística</p>
            <h1>Requerimientos de compra</h1>
            <p>
                @if ($esLogistica && ! $puedeCrear)
                    Recibe las necesidades enviadas por Almacén, revisa proveedores conocidos y comienza la cotización sin volver a buscar la información desde cero.
                @else
                    Formaliza faltantes de una OM/OS/OP o reposiciones de stock y envíalos a Logística para su abastecimiento.
                @endif
            </p>
        </div>

        @if ($puedeCrear)
            <a href="{{ route('requerimientos-compra.create') }}" class="button button--primary">
                <x-ui.icon name="plus" :size="18" /> Nuevo requerimiento
            </a>
        @endif
    </section>

    <section class="summary-strip" aria-label="Resumen de requerimientos">
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--neutral"><x-ui.icon name="requisitions" :size="20" /></span>
            <div><span>Total</span><strong>{{ (int) $resumen['total'] }}</strong></div>
        </article>
        @if ($puedeCrear)
            <article class="summary-strip__item">
                <span class="summary-strip__icon summary-strip__icon--warning"><x-ui.icon name="edit" :size="20" /></span>
                <div><span>Borradores</span><strong>{{ (int) $resumen['borradores'] }}</strong></div>
            </article>
        @endif
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="mail" :size="20" /></span>
            <div><span>Recibidos / revisión</span><strong>{{ (int) $resumen['recibidos'] }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--warning"><x-ui.icon name="quotes" :size="20" /></span>
            <div><span>Cotizando</span><strong>{{ (int) $resumen['cotizando'] }}</strong></div>
        </article>
        <article class="summary-strip__item">
            <span class="summary-strip__icon summary-strip__icon--success"><x-ui.icon name="check-circle" :size="20" /></span>
            <div><span>Atendidos</span><strong>{{ (int) $resumen['atendidos'] }}</strong></div>
        </article>
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('requerimientos-compra.index') }}" class="filter-grid filter-grid--entries">
            <label class="form-field filter-grid__search">
                <span>Buscar</span>
                <span class="input-with-icon">
                    <span class="input-with-icon__symbol"><x-ui.icon name="search" :size="17" /></span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="REQ, OM/OS/OP o descripción">
                </span>
            </label>

            <label class="form-field">
                <span>Estado</span>
                <select name="estado">
                    <option value="">Todos</option>
                    @if ($puedeCrear)<option value="BORRADOR" @selected(request('estado') === 'BORRADOR')>Borrador</option>@endif
                    <option value="ENVIADA" @selected(request('estado') === 'ENVIADA')>Enviada</option>
                    <option value="EN_REVISION" @selected(request('estado') === 'EN_REVISION')>En revisión</option>
                    <option value="COTIZANDO" @selected(request('estado') === 'COTIZANDO')>Cotizando</option>
                    <option value="ATENDIDA" @selected(request('estado') === 'ATENDIDA')>Atendida</option>
                    <option value="ANULADA" @selected(request('estado') === 'ANULADA')>Anulada</option>
                </select>
            </label>

            <label class="form-field">
                <span>Origen</span>
                <select name="origen">
                    <option value="">Todos</option>
                    <option value="ORDEN_OPERACION" @selected(request('origen') === 'ORDEN_OPERACION')>OM / OS / OP</option>
                    <option value="REPOSICION" @selected(request('origen') === 'REPOSICION')>Reposición</option>
                </select>
            </label>

            <div class="filter-actions">
                <button type="submit" class="button button--primary"><x-ui.icon name="filter" :size="17" /> Filtrar</button>
                <a href="{{ route('requerimientos-compra.index') }}" class="button button--ghost">Limpiar</a>
            </div>
        </form>
    </section>

    <x-ui.collapsible-notice title="Qué representa este documento" label="Ver función del requerimiento de compra">
        <span>El requerimiento solo comunica una necesidad de Almacén a Logística. No selecciona proveedor, no autoriza una compra, no reserva y no mueve inventario.</span>
    </x-ui.collapsible-notice>

    <section class="panel {{ $requerimientos->count() === 0 ? 'panel--empty-list' : '' }}">
        @if ($requerimientos->count() > 0)
            <div class="table-wrap table-wrap--wide table-wrap--responsive" data-responsive-table>
                <table class="data-table data-table--actions data-table--responsive purchase-requirement-list-table">
                    <thead>
                        <tr>
                            <th>Requerimiento</th>
                            <th>Fecha</th>
                            <th>Origen</th>
                            <th>Solicitante</th>
                            <th>Productos</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requerimientos as $requerimiento)
                            @php
                                $estadoClase = match ($requerimiento->estado) {
                                    'ATENDIDA' => 'success',
                                    'COTIZANDO', 'EN_REVISION' => 'warning',
                                    'ANULADA' => 'danger',
                                    'ENVIADA' => 'info',
                                    default => 'neutral',
                                };
                                $prioridadClase = match ($requerimiento->prioridad) {
                                    'URGENTE' => 'danger',
                                    'ALTA' => 'warning',
                                    'BAJA' => 'neutral',
                                    default => 'info',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('requerimientos-compra.show', $requerimiento) }}" class="table-primary-link">{{ $requerimiento->codigo }}</a>
                                    <span>{{ $requerimiento->descripcion ?: 'Sin descripción adicional' }}</span>
                                </td>
                                <td><strong>{{ $requerimiento->fecha_solicitud?->format('d/m/Y') }}</strong></td>
                                <td>
                                    <strong>{{ $requerimiento->origenVisible() }}</strong>
                                    <span>{{ $requerimiento->ordenOperacion?->codigo_orden ?: 'Stock general' }}</span>
                                </td>
                                <td>{{ $requerimiento->solicitante?->nombreVisible() ?? '—' }}</td>
                                <td><strong>{{ (int) $requerimiento->detalles_count }}</strong></td>
                                <td><span class="badge badge--{{ $prioridadClase }}">{{ $requerimiento->prioridad }}</span></td>
                                <td><span class="badge badge--{{ $estadoClase }}">{{ str($requerimiento->estado)->replace('_', ' ')->title() }}</span></td>
                                <td>
                                    <a href="{{ route('requerimientos-compra.show', $requerimiento) }}" class="icon-button" title="Ver requerimiento" aria-label="Ver requerimiento">
                                        <x-ui.icon name="eye" :size="17" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$requerimientos" />
        @else
            <div class="empty-table-state">
                <span class="empty-state__icon"><x-ui.icon name="requisitions" :size="30" /></span>
                <strong>{{ $puedeCrear ? 'Aún no hay requerimientos de compra' : 'No hay requerimientos recibidos' }}</strong>
                <span>{{ $puedeCrear ? 'Cuando Almacén detecte un faltante podrá formalizarlo aquí.' : 'Los requerimientos enviados por Almacén aparecerán en esta bandeja.' }}</span>
                @if ($puedeCrear)
                    <div class="empty-table-state__actions"><a href="{{ route('requerimientos-compra.create') }}" class="button button--primary button--small"><x-ui.icon name="plus" :size="16" /> Crear requerimiento</a></div>
                @endif
            </div>
        @endif
    </section>
</div>
@endsection
