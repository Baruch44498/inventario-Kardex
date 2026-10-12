        @if ($admiteReservas)
            <section class="panel supplier-quote-detail-lines" id="avance-operativo">
                <header class="supplier-panel-heading supplier-panel-heading--split">
                    <div>
                        <p class="eyebrow">Ejecución de la orden</p>
                        <h2>Avance</h2>
                        <p>El avance operativo lo registra Planta; el avance de materiales se calcula desde salidas y retornos confirmados.</p>
                    </div>
                    @if ($resumenEjecucion['ultimo_avance'])
                        <span class="badge badge--info">
                            Actualizado {{ $resumenEjecucion['ultimo_avance']->registrado_en?->format('d/m/Y H:i') }}
                        </span>
                    @endif
                </header>

                <div class="summary-strip summary-strip--four operation-summary">
                    <article class="summary-strip__item">
                        <span class="summary-strip__icon summary-strip__icon--info"><x-ui.icon name="activity" :size="21" /></span>
                        <div><span>Avance operativo</span><strong>{{ number_format($resumenEjecucion['avance_operativo'], 2) }}%</strong></div>
                    </article>
                    <article class="summary-strip__item">
                        <span class="summary-strip__icon summary-strip__icon--warning"><x-ui.icon name="inventory" :size="21" /></span>
                        <div><span>Avance de materiales</span><strong>{{ number_format($resumenEjecucion['avance_materiales'], 2) }}%</strong></div>
                    </article>
                    <article class="summary-strip__item">
                        <span class="summary-strip__icon summary-strip__icon--success"><x-ui.icon name="check-circle" :size="21" /></span>
                        <div><span>Líneas atendidas</span><strong>{{ $resumenEjecucion['lineas_atendidas'] }}/{{ $resumenEjecucion['lineas_materiales'] }}</strong></div>
                    </article>
                    <article class="summary-strip__item">
                        <span class="summary-strip__icon summary-strip__icon--neutral"><x-ui.icon name="clipboard" :size="21" /></span>
                        <div><span>Último registro</span><strong>{{ $resumenEjecucion['ultimo_avance']?->registradoPor?->username ?? 'Pendiente' }}</strong></div>
                    </article>
                </div>

                @if ($puedeGestionarMateriales && $orden->estaEnProceso())
                    <form method="POST" action="{{ route('ordenes-operacion.avances.store', $orden) }}" class="operation-filter-grid operation-advance-form" data-loading-form>
                        @csrf
                        <label class="form-field">
                            <span>Avance (%)</span>
                            <input type="number" name="porcentaje" min="0" max="100" step="0.01" value="{{ old('porcentaje', number_format($resumenEjecucion['avance_operativo'], 2, '.', '')) }}" required>
                            @error('porcentaje')<small class="field-error">{{ $message }}</small>@enderror
                        </label>
                        <label class="form-field operation-filter-grid__search">
                            <span>Trabajo realizado</span>
                            <input type="text" name="detalle" maxlength="500" value="{{ old('detalle') }}" placeholder="Ej. Armado hidráulico y pruebas preliminares" required>
                            @error('detalle')<small class="field-error">{{ $message }}</small>@enderror
                        </label>
                        <div class="filter-actions operation-filter-actions">
                            <button class="button button--primary" data-submit-button>
                                <x-ui.icon name="save" :size="17" /> Registrar avance
                            </button>
                        </div>
                    </form>
                @endif

                @if ($orden->avances->isNotEmpty())
                    <h3 class="operation-section-subtitle">Historial de avances</h3>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Fecha</th><th>Responsable</th><th class="text-center">Avance</th><th>Detalle</th></tr></thead>
                            <tbody>
                                @foreach ($orden->avances as $avance)
                                    <tr>
                                        <td>{{ $avance->registrado_en?->format('d/m/Y H:i') }}</td>
                                        <td>{{ $avance->registradoPor?->username ?? '—' }}</td>
                                        <td class="text-center">{{ number_format((float) $avance->porcentaje, 2) }}%</td>
                                        <td>{{ $avance->detalle }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @if ($puedeVerCostos && $resumenEjecucion['costos'])
                <section class="panel supplier-quote-detail-lines operation-costs-section" id="costos-directos">
                    <header class="supplier-panel-heading">
                        <div><p class="eyebrow">Ejecución económica</p><h2>Costos</h2><p>Costos y resultado de la orden según las operaciones registradas.</p></div>
                    </header>
                    @php
                        $costosOrden = $resumenEjecucion['costos'];
                        $rentabilidadOrden = $costosOrden['rentabilidad'];
                        $hayCostoReal = (float) $costosOrden['total_real'] > 0.0001;
                    @endphp
                    <div class="table-wrap">
                        <table class="data-table operation-costs-table">
                            <thead><tr><th>Concepto</th><th class="text-right">Importe en soles</th></tr></thead>
                            <tbody>
                                <tr class="operation-costs-table__group"><th scope="row" colspan="2">Materiales</th></tr>
                                <tr><td>Costo previsto de materiales</td><td class="text-right"><x-ui.money :value="$costosOrden['previsto_materiales']" currency="PEN" /></td></tr>
                                <tr><td>Salidas reales para consumo</td><td class="text-right"><x-ui.money :value="$costosOrden['salidas_consumo']" currency="PEN" /></td></tr>
                                <tr><td>Material retornado</td><td class="text-right">@if ((float) $costosOrden['retornos'] > 0)−@endif<x-ui.money :value="$costosOrden['retornos']" currency="PEN" /></td></tr>
                                <tr class="operation-costs-table__subtotal"><td><strong>Costo material real neto</strong></td><td class="text-right"><strong><x-ui.money :value="$costosOrden['real_materiales']" currency="PEN" /></strong></td></tr>
                                <tr><td>Desviación frente a lo previsto</td><td class="text-right">@if ((float) $costosOrden['desviacion'] < 0)−@endif<x-ui.money :value="abs((float) $costosOrden['desviacion'])" currency="PEN" /></td></tr>
                                <tr class="operation-costs-table__group"><th scope="row" colspan="2">Otros costos</th></tr>
                                <tr><td>Mano de obra, servicios, transporte y otros</td><td class="text-right"><x-ui.money :value="$costosOrden['directos']" currency="PEN" /></td></tr>
                                @if ($costosOrden['os_internas_total_real'] !== null)
                                    <tr><td>Gasto de OS internas</td><td class="text-right"><x-ui.money :value="$costosOrden['os_internas_total_real']" currency="PEN" /></td></tr>
                                @endif
                                <tr class="operation-costs-table__group"><th scope="row" colspan="2">Resultado</th></tr>
                                <tr class="operation-costs-table__subtotal"><td><strong>Costo real total acumulado</strong></td><td class="text-right"><strong><x-ui.money :value="$costosOrden['total_real']" currency="PEN" /></strong></td></tr>
                                @if ($rentabilidadOrden['disponible'])
                                    <tr><td>Ingreso neto cotizado (sin IGV)</td><td class="text-right"><x-ui.money :value="$rentabilidadOrden['ingreso_neto_soles']" currency="PEN" /></td></tr>
                                    <tr class="operation-costs-table__subtotal"><td><strong>Utilidad real</strong> @if (! $rentabilidadOrden['cierre_congelado'] && $hayCostoReal)<span class="badge badge--info">Proyección</span>@endif</td><td class="text-right">@if ($hayCostoReal)<strong>@if ((float) $rentabilidadOrden['utilidad_real_soles'] < 0)−@endif<x-ui.money :value="abs((float) $rentabilidadOrden['utilidad_real_soles'])" currency="PEN" /></strong>@else <span>—</span><small>Aún sin consumo registrado</small>@endif</td></tr>
                                    <tr><td>Margen real sobre venta neta @if (! $rentabilidadOrden['cierre_congelado'] && $hayCostoReal)<span class="badge badge--info">Proyección</span>@endif</td><td class="text-right">@if ($hayCostoReal && $rentabilidadOrden['margen_real_porcentaje'] !== null)<span class="operation-margin-value"><strong>{{ number_format((float) $rentabilidadOrden['margen_real_porcentaje'], 2) }}%</strong> · {{ $rentabilidadOrden['resultado'] }}</span>@else <span>—</span><small>Aún sin consumo registrado</small>@endif</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <x-ui.collapsible-notice title="Herramientas y rentabilidad" label="Herramientas y rentabilidad">
                        Las herramientas en uso temporal no forman parte del costo. Los retornos confirmados reducen el costo real sin modificar la salida histórica.
                        @if ($rentabilidadOrden['disponible'])
                            {{ $rentabilidadOrden['cierre_congelado'] ? 'Los importes corresponden al cierre congelado.' : 'La rentabilidad es una proyección hasta que la orden sea cerrada.' }}
                        @else
                            No existe una cotización vinculada para calcular rentabilidad.
                        @endif
                    </x-ui.collapsible-notice>

                    @if ($puedeGestionarCostos && $orden->estaEnProceso())
                        @php
                            $abrirCosto = collect(['orden_area_id', 'tipo', 'fecha_costo', 'descripcion', 'proveedor_id', 'cantidad', 'unidad', 'costo_unitario_soles', 'documento_referencia'])
                                ->contains(fn (string $campo): bool => $errors->has($campo))
                                || old('tipo') !== null || old('descripcion') !== null || old('costo_unitario_soles') !== null;
                        @endphp
                        <details class="operation-cost-entry" @if ($abrirCosto) open @endif>
                            <summary class="button button--ghost"><x-ui.icon name="plus" :size="17" /> Registrar costo</summary>
                            <form method="POST" action="{{ route('ordenes-operacion.costos-directos.store', $orden) }}" class="operation-cost-form" data-loading-form>
                                @csrf
                                <fieldset class="operation-cost-form__group">
                                    <legend>Qué</legend>
                                    <div class="operation-cost-form__fields">
                                        @if ($areasCostos->isNotEmpty())
                                            <label class="form-field operation-cost-form__field--wide">
                                                <span>Área que incurrió en el costo</span>
                                                <select name="orden_area_id" required>
                                                    <option value="">Selecciona el área</option>
                                                    @foreach ($areasCostos as $areaCosto)
                                                        <option value="{{ $areaCosto['id'] }}" @selected((int) old('orden_area_id') === $areaCosto['id'])>{{ $areaCosto['ruta'] }}</option>
                                                    @endforeach
                                                </select>
                                                @error('orden_area_id')<small class="field-error">{{ $message }}</small>@enderror
                                            </label>
                                        @endif
                                        <label class="form-field">
                                            <span>Tipo de costo</span>
                                            <select name="tipo" required>
                                                <option value="">Seleccionar</option>
                                                @foreach (\App\Models\CostoDirectoOrden::TIPOS as $valor => $nombre)
                                                    <option value="{{ $valor }}" @selected(old('tipo') === $valor)>{{ $nombre }}</option>
                                                @endforeach
                                            </select>
                                            @error('tipo')<small class="field-error">{{ $message }}</small>@enderror
                                        </label>
                                        <label class="form-field operation-cost-form__field--wide">
                                            <span>Descripción</span>
                                            <input type="text" name="descripcion" maxlength="300" value="{{ old('descripcion') }}" placeholder="Ej. Torneado de eje o 4 horas de montaje" required>
                                            @error('descripcion')<small class="field-error">{{ $message }}</small>@enderror
                                        </label>
                                        <label class="form-field operation-cost-form__field--wide">
                                            <span>Proveedor (opcional)</span>
                                            <select name="proveedor_id">
                                                <option value="">Sin proveedor</option>
                                                @foreach ($proveedoresCostos as $proveedorCosto)
                                                    <option value="{{ $proveedorCosto->id }}" @selected((int) old('proveedor_id') === $proveedorCosto->id)>{{ $proveedorCosto->nombreVisible() }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </div>
                                </fieldset>
                                <fieldset class="operation-cost-form__group">
                                    <legend>Cuánto</legend>
                                    <div class="operation-cost-form__fields">
                                        <label class="form-field">
                                            <span>Fecha</span>
                                            <input type="date" name="fecha_costo" max="{{ now()->toDateString() }}" value="{{ old('fecha_costo', now()->toDateString()) }}" required>
                                            @error('fecha_costo')<small class="field-error">{{ $message }}</small>@enderror
                                        </label>
                                        <label class="form-field">
                                            <span>Cantidad</span>
                                            <input type="number" name="cantidad" min="0.001" step="0.001" value="{{ old('cantidad', 1) }}" required>
                                            @error('cantidad')<small class="field-error">{{ $message }}</small>@enderror
                                        </label>
                                        <label class="form-field">
                                            <span>Unidad</span>
                                            <select name="unidad" required>
                                                @foreach (\App\Models\CostoDirectoOrden::UNIDADES as $valor => $nombre)
                                                    <option value="{{ $valor }}" @selected(old('unidad', 'GLOBAL') === $valor)>{{ $nombre }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="form-field">
                                            <span>Costo unitario (S/)</span>
                                            <input type="number" name="costo_unitario_soles" min="0.0001" step="0.0001" value="{{ old('costo_unitario_soles') }}" required>
                                            @error('costo_unitario_soles')<small class="field-error">{{ $message }}</small>@enderror
                                        </label>
                                        <label class="form-field">
                                            <span>Documento de referencia</span>
                                            <input type="text" name="documento_referencia" maxlength="100" value="{{ old('documento_referencia') }}" placeholder="Factura, recibo o sustento">
                                        </label>
                                    </div>
                                </fieldset>
                                <div class="operation-cost-form__actions">
                                    <button class="button button--primary" data-submit-button><x-ui.icon name="save" :size="17" /> Registrar costo</button>
                                </div>
                            </form>
                        </details>
                    @endif

                    @if ($orden->costosDirectos->isNotEmpty())
                        <h3 class="operation-section-subtitle">Historial de costos</h3>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead><tr><th>Fecha / tipo</th><th>Detalle</th><th class="text-right">Cálculo</th><th>Estado</th><th>Acción</th></tr></thead>
                                <tbody>
                                    @foreach ($orden->costosDirectos as $costoDirecto)
                                        <tr>
                                            <td><strong>{{ $costoDirecto->fecha_costo?->format('d/m/Y') }}</strong><span>{{ $costoDirecto->tipoVisible() }}</span></td>
                                            <td><strong>{{ $costoDirecto->descripcion }}</strong><span>Área: {{ $areasCostos->firstWhere('id', $costoDirecto->orden_area_id)['ruta'] ?? ($costoDirecto->area?->nombre ?? 'Sin área registrada') }}</span><span>{{ $costoDirecto->proveedor?->nombreVisible() ?? 'Sin proveedor' }}{{ $costoDirecto->documento_referencia ? ' · '.$costoDirecto->documento_referencia : '' }}</span></td>
                                            <td class="text-right"><x-ui.quantity :value="$costoDirecto->cantidad" /> {{ $costoDirecto->unidadVisible() }} × <x-ui.money :value="$costoDirecto->costo_unitario_soles" currency="PEN" /><br><strong><x-ui.money :value="$costoDirecto->total_soles" currency="PEN" /></strong></td>
                                            <td><span class="badge badge--{{ $costoDirecto->estaVigente() ? 'success' : 'danger' }}">{{ $costoDirecto->estado }}</span>@if (! $costoDirecto->estaVigente())<span>{{ $costoDirecto->motivo_anulacion }}</span>@endif</td>
                                            <td>
                                                @if ($puedeGestionarCostos && $orden->estaEnProceso() && $costoDirecto->estaVigente())
                                                    <form method="POST" action="{{ route('costos-directos-orden.anular', $costoDirecto) }}" data-loading-form>
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="text" name="motivo_anulacion" minlength="10" maxlength="500" placeholder="Motivo de anulación" required>
                                                        <button class="button button--danger button--small" data-submit-button>Anular</button>
                                                    </form>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif
        @endif
