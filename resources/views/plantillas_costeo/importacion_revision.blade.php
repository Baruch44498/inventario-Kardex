@extends('layouts.app')

@section('title', 'Revisar importación de costos')
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Revisar Excel importado')

@section('content')
    @php
        $tipos = \App\Models\CotizacionPresupuesto::TIPOS;
        $unidades = \App\Models\CotizacionPresupuesto::UNIDADES;
        $cotizacionDestino = $importacion->cotizacionCliente;
    @endphp

    <a href="{{ $cotizacionDestino ? route('cotizaciones-cliente.presupuesto.show', $cotizacionDestino) : route('plantillas-costeo.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> {{ $cotizacionDestino ? 'Volver a '.$cotizacionDestino->codigo : 'Volver a plantillas' }}
    </a>

    <section class="module-header module-header--compact">
        <div>
            <p class="eyebrow">Paso 2 de 3 · {{ $importacion->tipoOrden?->codigo }}</p>
            <h1>{{ $importacion->nombre }}</h1>
            <p>{{ $importacion->nombre_original }} · Hoja {{ $importacion->hoja ?: 'activa' }}</p>
        </div>
        <form method="POST" action="{{ route('plantillas-costeo.importaciones.reanalizar', $importacion) }}">
            @csrf
            <button type="submit" class="button button--secondary">Buscar productos nuevos por código</button>
        </form>
    </section>

    @error('importacion')
        <section class="notice notice--danger notice--block"><div><strong>No se puede confirmar</strong><span>{{ $message }}</span></div></section>
    @enderror

    <section class="metric-grid">
        <article class="metric-card"><span>Partidas activas</span><strong>{{ $resumen['total'] }}</strong></article>
        <article class="metric-card"><span>Materiales vinculados</span><strong>{{ $resumen['vinculadas'] }}</strong></article>
        <article class="metric-card"><span>Pendientes</span><strong>{{ $resumen['pendientes'] }}</strong></article>
        <article class="metric-card"><span>Omitidas</span><strong>{{ $resumen['omitidas'] }}</strong></article>
    </section>

    @if ($resumen['pendientes'] > 0)
        <section class="notice notice--warning notice--block">
            <x-ui.icon name="alert-triangle" :size="20" />
            <div>
                <strong>Faltan {{ $resumen['pendientes'] }} partidas por revisar</strong>
                <span>Vincula los materiales y clasifica cada servicio como externo o interno HIDROIL. También puedes omitir filas.</span>
            </div>
        </section>
    @else
        <section class="notice notice--success notice--block">
            <x-ui.icon name="check-circle" :size="20" />
            <div><strong>La importación está lista</strong><span>{{ $cotizacionDestino ? 'Confirma para añadir las filas a '.$cotizacionDestino->codigo.'. Luego revisa y sincroniza el precio final.' : 'Ya puedes confirmar la plantilla. Sus partidas seguirán siendo editables cuando la apliques a una cotización.' }}</span></div>
        </section>
    @endif

    @if (count($importacion->advertencias ?? []) > 0)
        <details class="notice notice--info notice--block">
            <summary><strong>Ver advertencias de lectura ({{ count($importacion->advertencias) }})</strong></summary>
            <ul>
                @foreach (array_slice($importacion->advertencias, 0, 20) as $advertencia)
                    <li>{{ $advertencia }}</li>
                @endforeach
            </ul>
        </details>
    @endif

    <section class="panel supplier-quote-detail-lines">
        <header class="supplier-panel-heading">
            <div>
                <p class="eyebrow">Revisión asistida</p>
                <h2>Áreas detectadas en el Excel</h2>
                <p>Abre cada sección para revisar sus partidas y campos. Puedes corregir el área y el tratamiento del costo antes de confirmar.</p>
            </div>
        </header>

        <form method="GET" action="{{ route('plantillas-costeo.importaciones.show', $importacion) }}" class="form-actions">
            <label class="form-field"><span>Revisar área</span><select name="area"><option value="">Todas las áreas</option>@foreach ($areasDetectadas as $nombreArea)<option value="{{ $nombreArea }}" @selected($areaSeleccionada === $nombreArea)>{{ $nombreArea }}</option>@endforeach</select></label>
            <button class="button button--secondary" type="submit">Mostrar área</button>
        </form>
        <div class="quote-area-accordion">
        @foreach ($partidas->getCollection()->groupBy(fn($linea) => $linea->grupo_costo ?: 'Costos generales') as $nombreArea => $lineasArea)
        <details class="quote-area-card" @if ($areaSeleccionada) open @endif>
            <summary><span class="quote-area-card__number">{{ $loop->iteration }}</span><strong>{{ $nombreArea }}</strong><span>{{ $lineasArea->count() }} partidas en esta página</span><span aria-hidden="true">⌄</span></summary>
        <div class="quote-area-card__body">
            <div class="import-review-list">
                    @foreach ($lineasArea as $partida)
                        @php
                            $servicioPendiente = $partida->tipo_costo === 'SERVICIO_TERCERO'
                                && ! in_array($partida->ejecucion_servicio, ['EXTERNO', 'INTERNO_HIDROIL'], true);
                            $filaPendiente = $partida->estado_vinculacion === 'PENDIENTE' || $servicioPendiente;
                        @endphp
                        <article @class(['import-review-item', 'is-muted' => $partida->omitida])>
                            <div class="import-review-item__overview">
                                <div class="import-review-item__identity">
                                    <span>Fila {{ $partida->fila_excel }} · {{ $partida->ruta_areas ? implode(' / ', $partida->ruta_areas) : ($partida->grupo_costo ?: 'Sin grupo') }}</span>
                                    <strong>{{ $partida->descripcion }}</strong>
                                    <small>{{ $partida->codigo_referencia ? 'Código Excel: '.$partida->codigo_referencia : 'Sin código en Excel' }}</small>
                                    @if ($partida->producto)<small>Almacén: {{ $partida->producto->codigo }} · {{ $partida->producto->descripcion }}</small>@endif
                                </div>
                                <dl class="import-review-item__facts">
                                    <div><dt>Tipo</dt><dd>{{ $tipos[$partida->tipo_costo] ?? $partida->tipo_costo }} · {{ $unidades[$partida->unidad] ?? $partida->unidad }}</dd></div>
                                    <div><dt>Cantidad</dt><dd><x-ui.quantity :value="$partida->cantidad" /></dd></div>
                                    <div><dt>Costo unit.</dt><dd>{{ $partida->moneda }} {{ number_format((float) $partida->costo_unitario, 2) }}</dd></div>
                                    <div><dt>Margen Excel</dt><dd>{{ number_format((float) $partida->margen_porcentaje, 2) }}%</dd></div>
                                </dl>
                                <div class="import-review-item__state">
                                @if ($partida->omitida)
                                    <form method="POST" action="{{ route('plantillas-costeo.importaciones.partidas.update', $partida) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="accion" value="RESTAURAR">
                                        <button type="submit" class="button button--ghost button--small">Restaurar</button>
                                    </form>
                                @else
                                    <span class="status-badge status-badge--{{ $filaPendiente ? 'warning' : 'success' }}">
                                        {{ $filaPendiente ? 'Pendiente' : 'Revisada' }}
                                    </span>
                                @endif
                                </div>
                            </div>
                            @if (! $partida->omitida)
                                    <details class="import-review-item__details">
                                        <summary>{{ $filaPendiente ? 'Revisar ahora' : 'Corregir' }}</summary>
                                        <div class="import-review-item__editor">
                                        <form method="POST" action="{{ route('plantillas-costeo.importaciones.partidas.update', $partida) }}" class="import-review-form">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="accion" value="GUARDAR">
                                            <label class="form-field">
                                                <span>Área o sección del Excel</span>
                                                <input type="text" name="grupo_costo" maxlength="150" value="{{ $partida->grupo_costo }}">
                                            </label>
                                            <label class="form-field">
                                                <span>Descripción</span>
                                                <input type="text" name="descripcion" maxlength="300" value="{{ $partida->descripcion }}" required>
                                            </label>
                                            <label class="form-field">
                                                <span>Tipo</span>
                                                <select name="tipo_costo">
                                                    @foreach ($tipos as $codigo => $nombre)
                                                        <option value="{{ $codigo }}" @selected($partida->tipo_costo === $codigo)>{{ $nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label class="form-field">
                                                <span>Producto de almacén (solo material)</span>
                                                <x-ui.remote-combobox
                                                    name="producto_id"
                                                    :search-id="'importacion_producto_'.$partida->id.'_buscar'"
                                                    :value-id="'importacion_producto_'.$partida->id"
                                                    :search-url="route('catalogos.productos.buscar')"
                                                    :selected-id="$partida->producto_id"
                                                    :selected-label="$partida->producto ? $partida->producto->codigo.' — '.$partida->producto->descripcion : ''"
                                                    placeholder="Código o descripción"
                                                />
                                            </label>
                                            <label class="form-field">
                                                <span>Ejecución (solo servicios)</span>
                                                <select name="ejecucion_servicio">
                                                    <option value="">Pendiente de clasificar</option>
                                                    <option value="EXTERNO" @selected($partida->ejecucion_servicio === 'EXTERNO')>Servicio externo · queda como costo</option>
                                                    <option value="INTERNO_HIDROIL" @selected($partida->ejecucion_servicio === 'INTERNO_HIDROIL')>Servicio interno HIDROIL · generará OS hija</option>
                                                </select>
                                            </label>
                                            <label class="form-field">
                                                <span>Unidad (costos no materiales)</span>
                                                <select name="unidad">
                                                    @foreach ($unidades as $codigo => $nombre)
                                                        <option value="{{ $codigo }}" @selected($partida->unidad === $codigo)>{{ $nombre }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label class="form-field">
                                                <span>Cantidad</span>
                                                <input type="number" name="cantidad" min="0.001" step="0.001" value="{{ $partida->cantidad }}" required>
                                            </label>
                                            <label class="form-field">
                                                <span>Costo unitario</span>
                                                <input type="number" name="costo_unitario" min="0.0001" step="0.0001" value="{{ $partida->costo_unitario }}" required>
                                            </label>
                                            <label class="form-field">
                                                <span>Moneda</span>
                                                <select name="moneda">@foreach (\App\Models\CotizacionPresupuesto::MONEDAS as $codigo => $nombre)<option value="{{ $codigo }}" @selected($partida->moneda === $codigo)>{{ $nombre }}</option>@endforeach</select>
                                            </label>
                                            <label class="form-field">
                                                <span>Tratamiento del IGV de compra</span>
                                                <select name="igv_modo">@foreach (\App\Models\CotizacionPresupuesto::MODOS_IGV as $codigo => $nombre)<option value="{{ $codigo }}" @selected($partida->igv_modo === $codigo)>{{ $nombre }}</option>@endforeach</select>
                                            </label>
                                            <p class="import-review-form__reference">TC de referencia: {{ number_format((float) $partida->tipo_cambio, 2) }} · Carga social: {{ number_format((float) $partida->carga_social_porcentaje, 2) }}%. {{ $cotizacionDestino ? 'Se conserva el margen de esta fila del Excel y se usa el TC de la cotización.' : 'Al aplicar esta plantilla a una cotización se usarán su TC y su margen.' }}</p>
                                            <label class="form-field"><span>Observación</span><textarea name="observacion" maxlength="500">{{ $partida->observacion }}</textarea></label>
                                            <div class="form-actions">
                                                <button type="submit" class="button button--primary button--small">Guardar revisión</button>
                                            </div>
                                        </form>
                                        <form method="POST" action="{{ route('plantillas-costeo.importaciones.partidas.update', $partida) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="accion" value="OMITIR">
                                            <button type="submit" class="button button--ghost button--small">Omitir esta fila</button>
                                        </form>
                                        </div>
                                    </details>
                            @endif
                        </article>
                    @endforeach
            </div>
        </div>
        </details>
        @endforeach
        </div>
        <x-ui.pagination :paginator="$partidas" />
    </section>

    <section class="panel">
        <header class="supplier-panel-heading"><div><p class="eyebrow">Paso 3 de 3</p><h2>{{ $cotizacionDestino ? 'Añadir a '.$cotizacionDestino->codigo : 'Crear la plantilla' }}</h2><p>{{ $cotizacionDestino ? 'Se añadirán las filas activas, conservando lo registrado anteriormente. No vuelvas a cargar las mismas partidas si ya están en la cotización.' : 'Esta acción no modifica todavía ninguna cotización ni el stock.' }}</p></div></header>
        <form method="POST" action="{{ route('plantillas-costeo.importaciones.confirmar', $importacion) }}">
            @csrf
            <div class="form-actions">
                <button type="submit" class="button button--primary" @disabled($resumen['pendientes'] > 0 || ! $importacion->esBorrador())>
                    <x-ui.icon name="check-circle" :size="17" /> {{ $cotizacionDestino ? 'Confirmar y añadir a cotización' : 'Confirmar y crear plantilla' }}
                </button>
            </div>
        </form>
    </section>
@endsection
