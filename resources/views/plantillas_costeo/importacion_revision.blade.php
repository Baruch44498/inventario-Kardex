@extends('layouts.app')

@section('title', 'Revisar importación de costos')
@section('page-kicker', 'Cotizaciones')
@section('page-title', 'Revisar Excel importado')

@section('content')
    @php
        $tipos = \App\Models\CotizacionPresupuesto::TIPOS;
        $unidades = \App\Models\CotizacionPresupuesto::UNIDADES;
    @endphp

    <a href="{{ route('plantillas-costeo.index') }}" class="back-link">
        <x-ui.icon name="arrow-left" :size="17" /> Volver a plantillas
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
                <strong>Faltan {{ $resumen['pendientes'] }} materiales por vincular</strong>
                <span>Busca el producto del almacén en cada fila pendiente. Si una fila no debe formar parte de la plantilla, puedes omitirla.</span>
            </div>
        </section>
    @else
        <section class="notice notice--success notice--block">
            <x-ui.icon name="check-circle" :size="20" />
            <div><strong>La importación está lista</strong><span>Ya puedes confirmar la plantilla. Sus partidas seguirán siendo editables cuando la apliques a una cotización.</span></div>
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
                <h2>Partidas detectadas</h2>
                <p>El costo leído está en soles e incluye IGV, igual que el costo registrado por almacén.</p>
            </div>
        </header>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Fila / grupo</th><th>Partida</th><th>Clasificación</th><th class="text-right">Cantidad</th><th class="text-right">Costo unit.</th><th>Revisión</th></tr>
                </thead>
                <tbody>
                    @foreach ($partidas as $partida)
                        @php
                            $servicioPendiente = $partida->tipo_costo === 'SERVICIO_TERCERO'
                                && ! in_array($partida->ejecucion_servicio, ['EXTERNO', 'INTERNO_HIDROIL'], true);
                            $filaPendiente = $partida->estado_vinculacion === 'PENDIENTE' || $servicioPendiente;
                        @endphp
                        <tr @class(['is-muted' => $partida->omitida])>
                            <td><strong>Fila {{ $partida->fila_excel }}</strong><span>{{ $partida->grupo_costo ?: 'Sin grupo' }}</span></td>
                            <td>
                                <strong>{{ $partida->descripcion }}</strong>
                                <span>{{ $partida->codigo_referencia ? 'Código Excel: '.$partida->codigo_referencia : 'Sin código en Excel' }}</span>
                                @if ($partida->producto)<span>Almacén: {{ $partida->producto->codigo }} · {{ $partida->producto->descripcion }}</span>@endif
                            </td>
                            <td>{{ $tipos[$partida->tipo_costo] ?? $partida->tipo_costo }}<span>{{ $unidades[$partida->unidad] ?? $partida->unidad }}</span></td>
                            <td class="text-right"><strong><x-ui.quantity :value="$partida->cantidad" /></strong><span>{{ $partida->unidad_original ?: 'Sin U.M. original' }}</span></td>
                            <td class="text-right"><strong>S/ {{ number_format((float) $partida->costo_unitario, 2) }}</strong><span>Margen {{ number_format((float) $partida->margen_porcentaje, 2) }}%</span></td>
                            <td>
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
                                    <details class="table-row-details">
                                        <summary>{{ $filaPendiente ? 'Revisar ahora' : 'Corregir' }}</summary>
                                        <form method="POST" action="{{ route('plantillas-costeo.importaciones.partidas.update', $partida) }}">
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
                                                <span>Costo unitario con IGV (S/)</span>
                                                <input type="number" name="costo_unitario" min="0.0001" step="0.0001" value="{{ $partida->costo_unitario }}" required>
                                            </label>
                                            <label class="form-field">
                                                <span>Margen (%)</span>
                                                <input type="number" name="margen_porcentaje" min="0" step="0.0001" value="{{ $partida->margen_porcentaje }}" required>
                                            </label>
                                            <label class="form-field">
                                                <span>Tipo de cambio</span>
                                                <input type="number" name="tipo_cambio" min="0.1" step="0.000001" value="{{ $partida->tipo_cambio }}" required>
                                            </label>
                                            <div class="form-actions">
                                                <button type="submit" class="button button--primary button--small">Guardar revisión</button>
                                            </div>
                                        </form>
                                        <form method="POST" action="{{ route('plantillas-costeo.importaciones.partidas.update', $partida) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="accion" value="OMITIR">
                                            <button type="submit" class="button button--ghost button--small">Omitir esta fila</button>
                                        </form>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-ui.pagination :paginator="$partidas" />
    </section>

    <section class="panel">
        <header class="supplier-panel-heading"><div><p class="eyebrow">Paso 3 de 3</p><h2>Crear la plantilla</h2><p>Esta acción no modifica todavía ninguna cotización ni el stock.</p></div></header>
        <form method="POST" action="{{ route('plantillas-costeo.importaciones.confirmar', $importacion) }}">
            @csrf
            <div class="form-actions">
                <button type="submit" class="button button--primary" @disabled($resumen['pendientes'] > 0 || ! $importacion->esBorrador())>
                    <x-ui.icon name="check-circle" :size="17" /> Confirmar y crear plantilla
                </button>
            </div>
        </form>
    </section>
@endsection
