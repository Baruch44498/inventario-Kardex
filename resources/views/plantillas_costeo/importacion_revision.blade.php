@extends('layouts.app')

@section('title', 'Revisar importación de costos')
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Revisar Excel importado')

@section('content')
    @php
        $tipos = \App\Models\CotizacionPresupuesto::TIPOS;
        $unidades = \App\Models\CotizacionPresupuesto::UNIDADES;
        $cotizacionDestino = $importacion->cotizacionCliente;
        $revisadas = max(0, $resumen['total'] - $resumen['pendientes']);
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
            <button type="submit" class="button button--ghost">Buscar productos nuevos por código</button>
        </form>
    </section>

    @if ($errors->any())
        <section class="notice notice--danger notice--block" role="alert"><div><strong>Revisa la partida</strong><span>{{ $errors->first() }}</span></div></section>
    @endif

    <section class="metric-grid">
        <article class="metric-card"><span>Partidas activas</span><strong>{{ $resumen['total'] }}</strong></article>
        <article class="metric-card"><span>Materiales vinculados</span><strong>{{ $resumen['vinculadas'] }}</strong></article>
        <article class="metric-card"><span>Pendientes</span><strong>{{ $resumen['pendientes'] }}</strong></article>
        <article class="metric-card"><span>Omitidas</span><strong>{{ $resumen['omitidas'] }}</strong></article>
    </section>

    <section class="import-review-progress panel" aria-label="Progreso de revisión">
        <div><strong>{{ $revisadas }} de {{ $resumen['total'] }} revisadas</strong><span>{{ $resumen['pendientes'] }} pendientes</span></div>
        <progress value="{{ $revisadas }}" max="{{ max(1, $resumen['total']) }}" aria-label="Partidas revisadas" aria-valuetext="{{ $revisadas }} de {{ $resumen['total'] }} revisadas"></progress>
    </section>

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

    <section class="panel supplier-quote-detail-lines import-review-panel">
        <header class="supplier-panel-heading">
            <div>
                <p class="eyebrow">Revisión asistida</p>
                <h2>Áreas detectadas en el Excel</h2>
                <p>Vincula materiales, clasifica servicios y corrige los demás campos cuando sea necesario.</p>
            </div>
        </header>
        <div class="import-review-layout">
            <nav class="quote-area-nav" aria-label="Áreas de la importación">
                <a href="{{ route('plantillas-costeo.importaciones.show', ['importacion' => $importacion, 'pendientes' => (int) $soloPendientes]) }}"
                   class="quote-area-nav__link" @if (! $areaSeleccionada) aria-current="page" @endif>
                    <strong>Todas</strong><span>{{ $resumen['pendientes'] }} pendientes de {{ $resumen['total'] + $resumen['omitidas'] }}</span>
                </a>
                @foreach ($areasResumen as $nombreArea => $estadisticas)
                    <a href="{{ route('plantillas-costeo.importaciones.show', ['importacion' => $importacion, 'area' => $nombreArea, 'pendientes' => (int) $soloPendientes]) }}"
                       class="quote-area-nav__link" @if ($areaSeleccionada === $nombreArea) aria-current="page" @endif>
                    <strong>{{ $nombreArea }}</strong><span>{{ $estadisticas['pendientes'] }} {{ $estadisticas['pendientes'] === 1 ? 'pendiente' : 'pendientes' }} de {{ $estadisticas['total'] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="import-review-main">
                <nav class="import-review-filters" aria-label="Filtro de partidas">
                    <a href="{{ route('plantillas-costeo.importaciones.show', ['importacion' => $importacion, 'area' => $areaSeleccionada, 'pendientes' => 1]) }}"
                       @if ($soloPendientes) aria-current="page" @endif>Solo pendientes</a>
                    <a href="{{ route('plantillas-costeo.importaciones.show', ['importacion' => $importacion, 'area' => $areaSeleccionada, 'pendientes' => 0]) }}"
                       @unless ($soloPendientes) aria-current="page" @endunless>Todas las partidas</a>
                </nav>

                @forelse ($partidas->getCollection()->groupBy(fn($linea) => $linea->grupo_costo ?: 'Costos generales') as $nombreArea => $lineasArea)
                    <section class="quote-area-group" aria-label="{{ $nombreArea }}">
                        <h3>{{ $nombreArea }} <span>{{ $lineasArea->count() }} en esta página</span></h3>
                        <div class="import-review-list">
                            @foreach ($lineasArea as $partida)
                                @php
                                    $servicioPendiente = $partida->tipo_costo === 'SERVICIO_TERCERO'
                                        && ! in_array($partida->ejecucion_servicio, ['EXTERNO', 'INTERNO_HIDROIL'], true);
                                    $filaPendiente = $partida->estado_vinculacion === 'PENDIENTE' || $servicioPendiente;
                                    $accionPartida = route('plantillas-costeo.importaciones.partidas.update', [
                                        'partida' => $partida, 'area' => $areaSeleccionada, 'page' => $partidas->currentPage(),
                                        'pendientes' => (int) $soloPendientes,
                                    ]);
                                @endphp
                                <article id="partida-{{ $partida->id }}" @class(['import-review-item', 'is-muted' => $partida->omitida])>
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
                                                <form method="POST" action="{{ $accionPartida }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="accion" value="RESTAURAR">
                                                    <button type="submit" class="button button--ghost button--small">Restaurar</button>
                                                </form>
                                            @else
                                                <x-ui.status-badge :tone="$filaPendiente ? 'warning' : 'success'">{{ $filaPendiente ? 'Pendiente' : 'Revisada' }}</x-ui.status-badge>
                                            @endif
                                        </div>
                                    </div>

                                    @if (! $partida->omitida)
                                        @if ($filaPendiente && in_array($partida->tipo_costo, ['MATERIAL', 'SERVICIO_TERCERO'], true))
                                            <form method="POST" action="{{ $accionPartida }}" class="import-review-item__quick">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="accion" value="GUARDAR">
                                                <input type="hidden" name="grupo_costo" value="{{ $partida->grupo_costo }}">
                                                <input type="hidden" name="descripcion" value="{{ $partida->descripcion }}">
                                                <input type="hidden" name="tipo_costo" value="{{ $partida->tipo_costo }}">
                                                <input type="hidden" name="unidad" value="{{ $partida->unidad }}">
                                                <input type="hidden" name="cantidad" value="{{ $partida->cantidad }}">
                                                <input type="hidden" name="costo_unitario" value="{{ $partida->costo_unitario }}">
                                                <input type="hidden" name="moneda" value="{{ $partida->moneda }}">
                                                <input type="hidden" name="igv_modo" value="{{ $partida->igv_modo }}">
                                                <input type="hidden" name="observacion" value="{{ $partida->observacion }}">
                                                @if ($partida->tipo_costo === 'MATERIAL')
                                                    <label class="form-field">
                                                        <span>Producto de almacén para fila {{ $partida->fila_excel }}</span>
                                                        <x-ui.remote-combobox
                                                            name="producto_id"
                                                            :search-id="'importacion_producto_rapido_'.$partida->id.'_buscar'"
                                                            :value-id="'importacion_producto_rapido_'.$partida->id"
                                                            :search-url="route('catalogos.productos.buscar')"
                                                            :selected-id="$partida->producto_id"
                                                            :selected-label="$partida->producto ? $partida->producto->codigo.' — '.$partida->producto->descripcion : ''"
                                                            placeholder="Código o descripción"
                                                        />
                                                    </label>
                                                @else
                                                    <label class="form-field">
                                                        <span>Ejecución para fila {{ $partida->fila_excel }}</span>
                                                        <select name="ejecucion_servicio" required>
                                                            <option value="">Selecciona la ejecución</option>
                                                            <option value="EXTERNO" @selected($partida->ejecucion_servicio === 'EXTERNO')>Servicio externo · queda como costo</option>
                                                            <option value="INTERNO_HIDROIL" @selected($partida->ejecucion_servicio === 'INTERNO_HIDROIL')>Servicio interno HIDROIL · generará OS hija</option>
                                                        </select>
                                                    </label>
                                                @endif
                                                <button type="submit" class="button button--primary button--small">Guardar</button>
                                            </form>
                                        @endif

                                        <details class="import-review-item__details">
                                            <summary>Editar todos los campos</summary>
                                            <div class="import-review-item__editor">
                                                <form method="POST" action="{{ $accionPartida }}" class="import-review-form">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="accion" value="GUARDAR">
                                                    <fieldset class="import-review-form__section">
                                                        <legend>Identificación</legend>
                                                        <label class="form-field"><span>Área o sección del Excel</span><input type="text" name="grupo_costo" maxlength="150" value="{{ $partida->grupo_costo }}"></label>
                                                        <label class="form-field"><span>Descripción</span><input type="text" name="descripcion" maxlength="300" value="{{ $partida->descripcion }}" required></label>
                                                        <label class="form-field"><span>Tipo</span><select name="tipo_costo" data-import-review-type>
                                                            @foreach ($tipos as $codigo => $nombre)<option value="{{ $codigo }}" @selected($partida->tipo_costo === $codigo)>{{ $nombre }}</option>@endforeach
                                                        </select></label>
                                                        <label class="form-field"><span>Observación</span><textarea name="observacion" maxlength="500">{{ $partida->observacion }}</textarea></label>
                                                    </fieldset>
                                                    <fieldset class="import-review-form__section">
                                                        <legend>Vinculación</legend>
                                                        <label class="form-field import-review-form__field" data-import-review-for="material">
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
                                                        <label class="form-field import-review-form__field" data-import-review-for="servicio">
                                                            <span>Ejecución (solo servicios)</span>
                                                            <select name="ejecucion_servicio">
                                                                <option value="">Pendiente de clasificar</option>
                                                                <option value="EXTERNO" @selected($partida->ejecucion_servicio === 'EXTERNO')>Servicio externo · queda como costo</option>
                                                                <option value="INTERNO_HIDROIL" @selected($partida->ejecucion_servicio === 'INTERNO_HIDROIL')>Servicio interno HIDROIL · generará OS hija</option>
                                                            </select>
                                                        </label>
                                                        <label class="form-field import-review-form__field" data-import-review-for="no-material">
                                                            <span>Unidad (costos no materiales)</span>
                                                            <select name="unidad">@foreach ($unidades as $codigo => $nombre)<option value="{{ $codigo }}" @selected($partida->unidad === $codigo)>{{ $nombre }}</option>@endforeach</select>
                                                        </label>
                                                    </fieldset>
                                                    <fieldset class="import-review-form__section">
                                                        <legend>Cálculo</legend>
                                                        <label class="form-field"><span>Cantidad</span><input type="number" name="cantidad" min="0.001" step="0.001" value="{{ $partida->cantidad }}" required></label>
                                                        <label class="form-field"><span>Costo unitario</span><input type="number" name="costo_unitario" min="0.0001" step="0.0001" value="{{ $partida->costo_unitario }}" required></label>
                                                        <label class="form-field"><span>Moneda</span><select name="moneda">@foreach (\App\Models\CotizacionPresupuesto::MONEDAS as $codigo => $nombre)<option value="{{ $codigo }}" @selected($partida->moneda === $codigo)>{{ $nombre }}</option>@endforeach</select></label>
                                                        <label class="form-field"><span>Tratamiento del IGV de compra</span><select name="igv_modo">@foreach (\App\Models\CotizacionPresupuesto::MODOS_IGV as $codigo => $nombre)<option value="{{ $codigo }}" @selected($partida->igv_modo === $codigo)>{{ $nombre }}</option>@endforeach</select></label>
                                                        <p class="import-review-form__reference">TC de referencia: {{ number_format((float) $partida->tipo_cambio, 2) }} · Carga social: {{ number_format((float) $partida->carga_social_porcentaje, 2) }}%. {{ $cotizacionDestino ? 'Se conserva el margen de esta fila del Excel y se usa el TC de la cotización.' : 'Al aplicar esta plantilla a una cotización se usarán su TC y su margen.' }}</p>
                                                    </fieldset>
                                                    <div class="form-actions"><button type="submit" class="button button--primary button--small">Guardar revisión</button></div>
                                                </form>
                                                <form method="POST" action="{{ $accionPartida }}">
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
                    </section>
                @empty
                    <div class="import-review-empty">
                        <strong>{{ $soloPendientes ? 'No hay pendientes en esta selección.' : 'No hay partidas en esta área.' }}</strong>
                        <a href="{{ route('plantillas-costeo.importaciones.show', ['importacion' => $importacion, 'pendientes' => 0]) }}">Ver todas las partidas</a>
                    </div>
                @endforelse
                <x-ui.pagination :paginator="$partidas" />
            </div>
        </div>
    </section>

    <section class="panel import-review-action-bar" aria-label="Confirmación de importación">
        <div class="import-review-action-bar__status" id="import-review-confirm-reason">
            <strong>{{ $cotizacionDestino ? 'Añadir a '.$cotizacionDestino->codigo : 'Crear la plantilla' }}</strong>
            @if ($resumen['pendientes'] > 0)
                <span>Faltan {{ $resumen['pendientes'] }} partidas por revisar.</span>
                @if ($primeraPendiente)
                    <a href="{{ route('plantillas-costeo.importaciones.show', ['importacion' => $importacion, 'area' => $primeraPendiente->grupo_costo ?: 'Costos generales', 'pendientes' => 1]) }}#partida-{{ $primeraPendiente->id }}">Ir a la primera pendiente</a>
                @endif
            @elseif (! $importacion->esBorrador())
                <span>Esta importación ya fue confirmada.</span>
            @else
                <span>{{ $cotizacionDestino ? 'Se añadirán las filas activas a la cotización.' : 'Se creará una plantilla sin modificar cotizaciones ni stock.' }}</span>
            @endif
        </div>
        <form method="POST" action="{{ route('plantillas-costeo.importaciones.confirmar', $importacion) }}">
            @csrf
            <button type="submit" class="button button--primary" aria-describedby="import-review-confirm-reason" @disabled($resumen['pendientes'] > 0 || ! $importacion->esBorrador())>
                <x-ui.icon name="check-circle" :size="17" /> {{ $cotizacionDestino ? 'Confirmar y añadir a cotización' : 'Confirmar y crear plantilla' }}
            </button>
        </form>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/importacion-revision.js') }}" defer></script>
@endpush
