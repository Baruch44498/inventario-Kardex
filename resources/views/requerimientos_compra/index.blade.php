@extends('layouts.app')

@section('title', 'Requerimientos de compra')
@section('page-kicker', $esLogistica && ! $puedeCrear ? 'Compras y proveedores' : 'Almacén')
@section('page-title', 'Requerimientos de compra')

@section('content')
<div class="purchase-requirement-list-page">
    <section class="module-header">
        <div>
            <h1>Requerimientos de compra</h1>
            <p>
                @if ($esLogistica && ! $puedeCrear)
                    Revisa las necesidades de Almacén y gestiona su cotización.
                @else
                    Registra faltantes de una orden o de stock y envíalos a Logística.
                @endif
            </p>
        </div>

        @if ($puedeCrear)
            <a href="{{ route('requerimientos-compra.create') }}" class="button button--primary">
                <x-ui.icon name="plus" :size="18" /> Nuevo requerimiento
            </a>
        @endif
    </section>

    @php
        $tarjetas = [
            ['Total', $resumen['total'], null, null, 'Todos los requerimientos visibles'],
            ['Borradores', $resumen['borradores'], 'estado', 'BORRADOR', 'Aún no enviados'],
            ['Recibidos / revisión', $resumen['recibidos'], null, null, 'Enviados a Logística o en revisión'],
            ['Cotizando', $resumen['cotizando'], 'estado', 'COTIZANDO', 'Cotización en proceso'],
            ['Gestión atendida', $resumen['atendidos'], 'estado', 'ATENDIDA', 'Logística completó la gestión; la recepción física puede continuar'],
            ['Abastecidos', $resumen['abastecidos'], 'abastecimiento', 'COMPLETO', 'La cantidad solicitada se recibió físicamente'],
        ];
    @endphp
    <section class="purchase-requirement-metrics" aria-label="Resumen de requerimientos">
        @foreach ($tarjetas as [$etiqueta, $valor, $parametro, $filtro, $ayuda])
            @continue($etiqueta === 'Borradores' && ! $puedeCrear)
            @php
                $claseTarjeta = 'metric-card metric-card--summary'.($etiqueta === 'Total' ? ' metric-card--highlight' : '');
                $urlTarjeta = route('requerimientos-compra.index', $parametro ? [$parametro => $filtro] : []);
            @endphp
            @if ($etiqueta === 'Recibidos / revisión')
                <article class="{{ $claseTarjeta }}" title="{{ $ayuda }}">
                    <span>{{ $etiqueta }}</span><strong>{{ (int) $valor }}</strong>
                    <small class="purchase-requirement-metrics__links">
                        @if ($valor - $enRevision > 0)<a href="{{ route('requerimientos-compra.index', ['estado' => 'ENVIADA']) }}" @if (request('estado') === 'ENVIADA') aria-current="page" @endif>Enviadas: {{ (int) $valor - $enRevision }}</a>@endif
                        @if ($enRevision > 0)<a href="{{ route('requerimientos-compra.index', ['estado' => 'EN_REVISION']) }}" @if (request('estado') === 'EN_REVISION') aria-current="page" @endif>En revisión: {{ $enRevision }}</a>@endif
                        @if ($valor === 0) Sin enviadas ni en revisión @endif
                    </small>
                </article>
            @elseif ($valor > 0)
                <a class="{{ $claseTarjeta }}" href="{{ $urlTarjeta }}" title="{{ $ayuda }}"
                    @if ($parametro ? request($parametro) === $filtro : ! request('estado') && ! request('abastecimiento')) aria-current="page" @endif>
                    <span>{{ $etiqueta }}</span><strong>{{ (int) $valor }}</strong><small>{{ $ayuda }}</small>
                </a>
            @else
                <article class="{{ $claseTarjeta }}" title="{{ $ayuda }}"><span>{{ $etiqueta }}</span><strong>0</strong><small>{{ $ayuda }}</small></article>
            @endif
        @endforeach
    </section>

    <section class="panel filter-panel">
        <form method="GET" action="{{ route('requerimientos-compra.index') }}" class="purchase-requirement-filters">
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

            <label class="form-field">
                <span>Abastecimiento</span>
                <select name="abastecimiento">
                    <option value="">Todos</option>
                    <option value="PENDIENTE" @selected(request('abastecimiento') === 'PENDIENTE')>Pendiente</option>
                    <option value="PARCIAL" @selected(request('abastecimiento') === 'PARCIAL')>Parcial</option>
                    <option value="COMPLETO" @selected(request('abastecimiento') === 'COMPLETO')>Completo</option>
                </select>
            </label>

            <label class="form-field">
                <span>Ordenar por</span>
                <select name="orden">
                    <option value="recientes" @selected(request('orden', 'recientes') === 'recientes')>Recientes</option>
                    <option value="antiguos" @selected(request('orden') === 'antiguos')>Antiguos</option>
                    <option value="prioridad" @selected(request('orden') === 'prioridad')>Prioridad</option>
                </select>
            </label>

            <div class="filter-actions">
                <button type="submit" class="button button--primary"><x-ui.icon name="filter" :size="17" /> Filtrar</button>
                <a href="{{ route('requerimientos-compra.index') }}" class="button button--ghost">Limpiar</a>
            </div>
        </form>
        @php
            $chips = [
                'q' => request('q') ? 'Buscar: '.request('q') : null,
                'estado' => [
                    'BORRADOR' => 'Borrador', 'ENVIADA' => 'Enviada', 'EN_REVISION' => 'En revisión',
                    'COTIZANDO' => 'Cotizando', 'ATENDIDA' => 'Atendida', 'ANULADA' => 'Anulada',
                ][request('estado')] ?? null,
                'origen' => ['ORDEN_OPERACION' => 'OM / OS / OP', 'REPOSICION' => 'Reposición'][request('origen')] ?? null,
                'abastecimiento' => ['PENDIENTE' => 'Pendiente', 'PARCIAL' => 'Parcial', 'COMPLETO' => 'Completo'][request('abastecimiento')] ?? null,
                'orden' => ['antiguos' => 'Antiguos', 'prioridad' => 'Prioridad'][request('orden')] ?? null,
            ];
        @endphp
        @if (collect($chips)->filter()->isNotEmpty())
            <nav class="purchase-requirement-filters__chips" aria-label="Filtros activos">
                @foreach ($chips as $campo => $etiqueta)
                    @if ($etiqueta)
                        <a href="{{ route('requerimientos-compra.index', request()->except(['page', $campo])) }}" aria-label="Quitar filtro {{ $etiqueta }}">{{ $etiqueta }} <span aria-hidden="true">×</span></a>
                    @endif
                @endforeach
            </nav>
        @endif
    </section>

    <section class="panel purchase-requirement-list-panel {{ $requerimientos->count() === 0 ? 'panel--empty-list' : '' }}">
        <header class="purchase-requirement-list-heading">
            <h2>Requerimientos registrados</h2>
            <x-ui.collapsible-notice title="Qué representa este documento" label="Ver función del requerimiento de compra">
                <span>El requerimiento solo comunica una necesidad de Almacén a Logística. No selecciona proveedor, no autoriza una compra, no reserva y no mueve inventario.</span>
            </x-ui.collapsible-notice>
        </header>
        @if ($requerimientos->count() > 0)
            <div class="table-wrap table-wrap--responsive purchase-requirement-list-wrap" data-responsive-table>
                <table class="data-table data-table--actions data-table--responsive purchase-requirement-list-table">
                    <thead>
                        <tr>
                            <th class="table-sticky--start">Requerimiento</th>
                            <th class="table-priority--medium">Fecha</th>
                            <th>Estado</th>
                            <th class="table-sticky--end">Acción</th>
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
                                    default => 'neutral',
                                };
                                $estadoEtiqueta = [
                                    'BORRADOR' => 'Borrador', 'ENVIADA' => 'Enviada',
                                    'EN_REVISION' => 'En revisión', 'COTIZANDO' => 'Cotizando',
                                    'ATENDIDA' => 'Atendida', 'ANULADA' => 'Anulada',
                                ][$requerimiento->estado] ?? $requerimiento->estado;
                                $detailsId = 'requerimiento-detalles-'.$requerimiento->id;
                            @endphp
                            <tr class="purchase-requirement-list-row">
                                <td class="table-sticky--start">
                                    <div class="purchase-requirement-list-row__code">
                                        <a href="{{ route('requerimientos-compra.show', $requerimiento) }}" class="table-primary-link">{{ $requerimiento->codigo }}</a>
                                        @if (in_array($requerimiento->prioridad, ['URGENTE', 'ALTA'], true))
                                            <x-ui.status-badge :tone="$prioridadClase">{{ $requerimiento->prioridad }}</x-ui.status-badge>
                                        @endif
                                    </div>
                                    <small>{{ $requerimiento->ordenOperacion ? $requerimiento->ordenOperacion->tipoOrden?->codigo.' · '.$requerimiento->ordenOperacion->codigo_orden : 'Stock general' }} · {{ (int) $requerimiento->detalles_count }} {{ (int) $requerimiento->detalles_count === 1 ? 'producto' : 'productos' }}</small>
                                    @if ($requerimiento->descripcion)
                                        <small class="purchase-requirement-list-row__description" title="{{ $requerimiento->descripcion }}">{{ $requerimiento->descripcion }}</small>
                                    @endif
                                </td>
                                <td class="table-priority--medium" data-label="Fecha">
                                    <strong>{{ $requerimiento->fecha_solicitud?->format('d/m/Y') }}</strong>
                                    <small class="purchase-requirement-list-row__age {{ ! in_array($requerimiento->estado, ['ATENDIDA', 'ANULADA'], true) && $requerimiento->dias_antiguedad > $diasAlertaAntiguedad ? 'purchase-requirement-list-row__age--warning' : '' }}">
                                        hace {{ $requerimiento->dias_antiguedad }} {{ $requerimiento->dias_antiguedad === 1 ? 'día' : 'días' }}
                                    </small>
                                </td>
                                <td data-label="Estado" class="purchase-requirement-list-row__status">
                                    <x-ui.status-badge :tone="$estadoClase">{{ $estadoEtiqueta }}</x-ui.status-badge>
                                    @if (! in_array($requerimiento->estado, ['BORRADOR', 'ANULADA'], true))
                                        <progress class="progress-bar {{ $requerimiento->avance_abastecimiento === 100 ? 'progress-bar--success' : '' }}" value="{{ $requerimiento->avance_abastecimiento }}" max="100" aria-label="{{ $requerimiento->avance_abastecimiento }}% recibido"></progress>
                                        <small>{{ $requerimiento->avance_abastecimiento }}% recibido · {{ $requerimiento->estadoAbastecimientoVisible() }}</small>
                                    @endif
                                </td>
                                <td class="table-sticky--end" data-label="Acción">
                                    <div class="table-actions purchase-requirement-list-actions">
                                        @if ($requerimiento->siguiente_accion['ruta'] && $requerimiento->siguiente_accion['boton_listado'])
                                            <a
                                                href="{{ $requerimiento->siguiente_accion['ruta'] }}"
                                                class="button button--ghost button--small"
                                                title="{{ $requerimiento->siguiente_accion['titulo'] }}"
                                            >
                                                <x-ui.icon :name="$requerimiento->siguiente_accion['icono']" :size="16" />
                                                {{ $requerimiento->siguiente_accion['boton_listado'] }}
                                            </a>
                                        @else
                                            <a href="{{ route('requerimientos-compra.show', $requerimiento) }}" class="icon-button" title="Ver requerimiento" aria-label="Ver requerimiento">
                                                <x-ui.icon name="eye" :size="17" />
                                            </a>
                                        @endif
                                        <x-ui.table-details-toggle :target="$detailsId" label="Ver más datos del requerimiento" />
                                    </div>
                                </td>
                            </tr>
                            <x-ui.table-row-details :id="$detailsId" :colspan="4">
                                <dl class="table-details-grid purchase-requirement-list-details">
                                    <div>
                                        <dt>Solicitante</dt>
                                        <dd>{{ $requerimiento->solicitante?->nombreVisible() ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Responsable</dt>
                                        <dd>
                                            @if ($requerimiento->receptor)
                                                {{ $requerimiento->receptor->nombreVisible() }} · Logística
                                            @elseif ($requerimiento->estado === 'ENVIADA')
                                                Pendiente de tomar
                                            @else
                                                —
                                            @endif
                                        </dd>
                                    </div>
                                    @if ($requerimiento->abastecido_en)
                                        <div>
                                            <dt>Abastecido</dt>
                                            <dd>{{ $requerimiento->abastecido_en->format('d/m/Y H:i') }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </x-ui.table-row-details>
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
