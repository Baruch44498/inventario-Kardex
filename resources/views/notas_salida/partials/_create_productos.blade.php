<section id="paso-productos" class="panel" data-flow-step-section="3">
    <div class="panel-heading panel-heading--split output-stock-heading">
        <div class="output-stock-heading__copy">
            <p class="eyebrow">Productos y áreas</p>
            <h2>Productos que salen</h2>
            <p><strong>Consumo</strong> es salida definitiva. <strong>Uso temporal</strong> debe regresar. Las reservas son blandas: la salida no se bloquea si existe stock físico, pero se advierte cuando afecta material comprometido.</p>
        </div>
        @if ($motivo === 'ORDEN_OPERACION' && $orden)
            <div class="ui-collapsible-notice-cluster output-stock-heading__notices" aria-label="Ayudas de la entrega">
                <x-ui.collapsible-notice title="Entrega guiada por la orden" label="Ver cómo funciona la entrega guiada"><span>El sistema prioriza los materiales pendientes de {{ $orden->codigo_orden }}. Puedes hacer entregas parciales; cada Nota de Salida actualiza lo entregado y el saldo reservado.</span></x-ui.collapsible-notice>
                @if ($pendientesOrden->isEmpty())
                    <x-ui.collapsible-notice variant="success" icon="check-circle" title="Sin materiales pendientes planificados" label="Ver estado de los materiales planificados"><span>La orden no tiene materiales planificados pendientes de consumo. Las salidas adicionales se registrarán como consumo real no planificado o uso temporal.</span></x-ui.collapsible-notice>
                @else
                    <x-ui.collapsible-notice variant="success" icon="inventory" title="Materiales pendientes de la orden" label="Ver resumen de materiales pendientes"><span>{{ $pendientesOrden->count() }} {{ $pendientesOrden->count() === 1 ? 'material pendiente' : 'materiales pendientes' }} por atender en esta orden.</span></x-ui.collapsible-notice>
                @endif
                <x-ui.collapsible-notice title="Productos adicionales" label="Ver ayuda sobre productos adicionales"><span data-summary="Los materiales previstos ya están cargados.">Los materiales previstos de la orden ya están cargados en la tabla. Usa el buscador únicamente para agregar productos adicionales o no previstos.</span></x-ui.collapsible-notice>
            </div>
        @endif
    </div>

    @if ($motivo === 'ORDEN_OPERACION' && $orden && $pendientesSinStock->isNotEmpty())
        <div class="notice notice--warning notice--block"><x-ui.icon name="warning" :size="18" /><div><strong>Pendientes sin stock físico</strong><span>{{ $pendientesSinStock->map(fn ($item) => ($item['producto']?->codigo ?? 'Producto').' (pend. '.number_format($item['pendiente'], 2).')')->implode(' · ') }}. Permanecen pendientes hasta que Almacén sea abastecido.</span></div></div>
    @endif

    @error('detalles')<div class="notice notice--danger notice--block"><x-ui.icon name="error" :size="18" /><span>{{ $message }}</span></div>@enderror

    @if ($motivo === 'ORDEN_OPERACION' && $orden)
        <div class="output-product-finder output-product-finder--extras-only" data-output-product-finder>
            <div class="form-field output-product-finder__extra">
                <label for="producto_extra_salida_busqueda">Agregar producto adicional / no previsto</label>
                <x-ui.remote-combobox name="producto_extra_salida_id" search-id="producto_extra_salida_busqueda" value-id="producto_extra_salida_id" :search-url="route('catalogos.existencias-salida.buscar', ['orden_id' => $orden->id])" placeholder="Código, descripción o repisa" empty-text="No se encontró stock adicional disponible." />
                <small>Si piden más cantidad de un material previsto, registra el exceso en su misma fila.</small>
            </div>
        </div>
        @if ($filas->isEmpty())<div class="notice notice--warning notice--block output-extra-help"><x-ui.icon name="warning" :size="18" /><span>No hay stock físico disponible para los materiales planificados. Puedes buscar un producto adicional si corresponde.</span></div>@endif
    @endif

    @if ($filas->isEmpty() && $motivo !== 'ORDEN_OPERACION')
        <div class="empty-table-state empty-table-state--document-lines"><div class="document-lines-empty"><span class="empty-state__icon empty-state__icon--warning document-lines-empty__icon"><x-ui.icon name="warning" :size="30" /></span><div class="document-lines-empty__copy"><strong>No hay existencias pendientes disponibles para este origen</strong><p>Verifica el stock físico o si los productos ya fueron despachados.</p></div></div></div>
    @else
        <div class="table-wrap table-wrap--responsive output-lines-table-wrap">
            <table class="data-table data-table--responsive output-lines-table output-lines-table--compact">
                <thead><tr><th>Producto / repisa</th><th>Disponibilidad</th><th>{{ $motivo === 'ORDEN_OPERACION' ? 'Plan de la orden' : 'Origen' }}</th><th>Tratamiento</th><th>Cantidad</th><th>Control</th></tr></thead>
                <tbody>
                    @foreach ($filas as $indice => $fila)
                        @php
                            $inventario = $fila['inventario'];
                            $detalleProforma = $fila['proforma_detalle'];
                            $tratamientoFijo = $fila['tratamiento'];
                            $maximo = $fila['pendiente_origen'] !== null ? min((float) $inventario->stock_actual, (float) $fila['pendiente_origen']) : (float) $inventario->stock_actual;
                            $tratamientoAnterior = old("detalles.{$indice}.tratamiento", $tratamientoFijo ?: 'CONSUMO');
                            $reservaOrden = (float) ($fila['reserva_orden_pendiente'] ?? 0);
                            $reservadoGlobal = (float) ($fila['reservado_global_pendiente'] ?? 0);
                            $disponibleLibre = (float) ($fila['disponible_libre'] ?? 0);
                            $herramientasEnUso = (float) ($fila['herramientas_en_uso'] ?? 0);
                            $materialOrden = $fila['material_orden'] ?? null;
                            $pendienteOrden = (float) ($materialOrden['pendiente'] ?? 0);
                            $planificado = (bool) $materialOrden;
                        @endphp
                        <tr data-output-row data-output-motivo="{{ $motivo }}" data-reserva-orden="{{ $reservaOrden }}" data-reservado-global="{{ $reservadoGlobal }}" data-stock-total="{{ (float) ($fila['stock_total_producto'] ?? $inventario->stock_actual) }}" data-herramientas-en-uso="{{ $herramientasEnUso }}" data-planificado-orden="{{ $planificado ? '1' : '0' }}" data-pendiente-orden="{{ $pendienteOrden }}" data-producto-id="{{ $inventario->producto_id }}">
                            <td data-label="Producto / repisa" class="output-col-product">
                                <input type="hidden" name="detalles[{{ $indice }}][inventario_id]" value="{{ $inventario->id }}"><input type="hidden" name="detalles[{{ $indice }}][producto_id]" value="{{ $inventario->producto_id }}"><input type="hidden" name="detalles[{{ $indice }}][repisa_id]" value="{{ $inventario->repisa_id }}">@if ($detalleProforma)<input type="hidden" name="detalles[{{ $indice }}][proforma_detalle_id]" value="{{ $detalleProforma->id }}">@endif
                                <a href="{{ route('productos.show', $inventario->producto_id) }}" class="table-primary-link">{{ $inventario->producto?->codigo }}</a><span>{{ $inventario->producto?->descripcion }}</span><span class="location-chip"><x-ui.icon name="shelf" :size="14" /> {{ $inventario->repisa?->codigo }}</span>
                            </td>
                            <td data-label="Disponibilidad" class="output-col-availability"><div class="output-availability-stack"><span><small>Stock físico</small><strong><x-ui.quantity :value="$inventario->stock_actual" /></strong></span>@if ($motivo === 'ORDEN_OPERACION')<span><small>Reserva pendiente</small><strong><x-ui.quantity :value="$reservaOrden" /></strong></span>@endif<span><small>Disponible libre</small><strong @class(['availability-negative' => $disponibleLibre < 0])><x-ui.quantity :value="$disponibleLibre" /></strong></span></div></td>
                            <td data-label="Plan / origen" class="output-col-plan">
                                @if ($motivo === 'ORDEN_OPERACION')
                                    @if ($materialOrden)<span class="badge badge--{{ $pendienteOrden > 0.0001 ? 'warning' : 'success' }}">{{ $pendienteOrden > 0.0001 ? 'Pendiente' : 'Atendido' }}</span><strong><x-ui.quantity :value="$pendienteOrden" /></strong><small>Previsto <x-ui.quantity :value="$materialOrden['previsto']" /> · Requerido <x-ui.quantity :value="$materialOrden['requerido']" /></small><small>Entregado <x-ui.quantity :value="$materialOrden['entregado']" />@if ($materialOrden['retornado'] > 0) · Retornado <x-ui.quantity :value="$materialOrden['retornado']" /> · Consumido <x-ui.quantity :value="$materialOrden['consumido']" />@endif</small>@else<span class="badge badge--neutral">No planificado</span><small>No figura en materiales requeridos.</small>@endif
                                @elseif ($motivo === 'PROFORMA')
                                    <strong><x-ui.quantity :value="$fila['pendiente_origen']" /></strong><small>Pendiente Proforma</small>
                                @else
                                    <span class="badge badge--neutral">{{ $motivo === 'USO_INTERNO' ? 'Uso interno' : 'Otro' }}</span>
                                @endif
                            </td>
                            <td data-label="Tratamiento" class="output-col-treatment">
                                @if ($tratamientoFijo)<input type="hidden" name="detalles[{{ $indice }}][tratamiento]" value="{{ $tratamientoFijo }}" data-output-treatment><span class="badge badge--{{ $tratamientoFijo === 'PRESTAMO_EXTERNO' ? 'warning' : 'info' }}">{{ $tratamientoFijo === 'PRESTAMO_EXTERNO' ? 'Préstamo' : 'Venta' }}</span>@else<select name="detalles[{{ $indice }}][tratamiento]" class="table-input" data-output-treatment><option value="CONSUMO" @selected($tratamientoAnterior === 'CONSUMO')>Consumo</option><option value="USO_TEMPORAL" @selected($tratamientoAnterior === 'USO_TEMPORAL')>Uso temporal / herramienta</option></select>@endif
                            </td>
                            <td data-label="Cantidad" class="output-col-quantity"><input type="number" name="detalles[{{ $indice }}][cantidad]" value="{{ old("detalles.{$indice}.cantidad", 0) }}" min="0" max="{{ $maximo }}" step="{{ $inventario->producto?->permite_fraccionamiento ? '0.01' : '1' }}" class="table-input table-input--quantity" data-output-quantity data-output-stock="{{ (float) $inventario->stock_actual }}" data-output-total-stock="{{ (float) ($fila['stock_total_producto'] ?? $inventario->stock_actual) }}">@if ($motivo === 'ORDEN_OPERACION' && $pendienteOrden > 0.0001)<button type="button" class="button button--ghost button--small output-fill-pending" data-fill-pending>Usar pendiente</button>@endif @error("detalles.{$indice}.cantidad")<small class="field-error table-field-error">{{ $message }}</small>@enderror</td>
                            <td data-label="Control" class="output-col-control">
                                <details class="output-row-details"><summary>Motivo, observación y alertas</summary><div class="output-row-details__content">
                                    @if ($motivo === 'ORDEN_OPERACION')<label><span>Motivo del exceso</span><select name="detalles[{{ $indice }}][motivo_excedente]" class="table-input" data-output-excess-reason><option value="">Solo si supera el plan</option><option value="NECESIDAD_OPERATIVA" @selected(old("detalles.{$indice}.motivo_excedente") === 'NECESIDAD_OPERATIVA')>Necesidad operativa adicional</option><option value="REPOSICION_MALOGRADO" @selected(old("detalles.{$indice}.motivo_excedente") === 'REPOSICION_MALOGRADO')>Reposición de material malogrado</option></select></label>@endif
                                    <label><span>Observación</span><input type="text" name="detalles[{{ $indice }}][observacion]" value="{{ old("detalles.{$indice}.observacion") }}" maxlength="300" placeholder="Opcional" class="table-input"></label>
                                    <small class="field-warning" data-plan-warning hidden></small><small class="field-warning" data-reservation-warning hidden></small><small class="field-warning" data-committed-stock-warning hidden></small><small class="field-warning" data-last-tool-warning data-warning-text="no quedarán unidades disponibles de esta herramienta en Almacén" hidden></small>
                                </div></details>
                            </td>
                        </tr>
                    @endforeach

                    @if ($motivo === 'ORDEN_OPERACION')
                        @foreach ($materialesSinExistencia as $materialSinExistencia)
                            @php($productoSinExistencia = $materialSinExistencia['producto']) @php($pendienteSinExistencia = (float) $materialSinExistencia['pendiente']) @php($reservaSinExistencia = (float) $materialSinExistencia['reserva_pendiente'])
                            <tr class="output-row--no-stock" data-planned-no-stock><td data-label="Producto / repisa"><strong>{{ $productoSinExistencia?->codigo }}</strong><span>{{ $productoSinExistencia?->descripcion }}</span><span class="badge badge--neutral">Sin repisa disponible</span></td><td data-label="Disponibilidad"><div class="output-availability-stack"><span><small>Stock físico</small><strong>0</strong><small>Sin existencia disponible</small></span><span><small>Reserva pendiente</small><strong><x-ui.quantity :value="$reservaSinExistencia" /></strong></span><span><small>Disponible libre</small><strong @class(['availability-negative' => $materialSinExistencia['disponible_libre'] < 0])><x-ui.quantity :value="$materialSinExistencia['disponible_libre']" /></strong></span></div></td><td data-label="Plan de la orden"><span class="badge badge--warning">Pendiente</span><strong><x-ui.quantity :value="$pendienteSinExistencia" /></strong><small>Previsto <x-ui.quantity :value="$materialSinExistencia['previsto']" /> · Requerido <x-ui.quantity :value="$materialSinExistencia['requerido']" /></small><small>Entregado <x-ui.quantity :value="$materialSinExistencia['entregado']" /></small></td><td data-label="Tratamiento"><span class="badge badge--info">Consumo</span></td><td data-label="Cantidad"><span class="badge badge--warning">Sin stock</span><small>Abastecer antes de despachar.</small></td><td data-label="Control"><small>No hay stock físico disponible para registrar la salida.</small></td></tr>
                        @endforeach
                    @endif
                </tbody>
            </table>

            @if ($motivo === 'ORDEN_OPERACION' && $orden)
                <template data-output-extra-row-template>
                    <tr data-output-row data-output-motivo="ORDEN_OPERACION" data-reserva-orden="0" data-reservado-global="0" data-stock-total="0" data-herramientas-en-uso="0" data-planificado-orden="0" data-pendiente-orden="0" data-producto-id="" data-extra-output-row>
                        <td data-label="Producto / repisa" class="output-col-product"><input type="hidden" data-detail-field="inventario_id"><input type="hidden" data-detail-field="producto_id"><input type="hidden" data-detail-field="repisa_id"><strong data-extra-code></strong><span data-extra-description></span><span class="location-chip"><x-ui.icon name="shelf" :size="14" /><span data-extra-shelf></span></span><button type="button" class="button button--ghost button--small" data-remove-extra-row>Quitar</button></td>
                        <td data-label="Disponibilidad"><div class="output-availability-stack"><span><small>Stock físico</small><strong data-extra-stock></strong><small data-extra-unit></small></span><span><small>Reserva pendiente</small><strong>0</strong></span><span><small>Disponible libre</small><strong data-extra-available></strong></span></div></td>
                        <td data-label="Plan de la orden"><span class="badge badge--neutral">No planificado</span><small>No figura en materiales requeridos.</small></td>
                        <td data-label="Tratamiento"><select class="table-input" data-detail-field="tratamiento" data-output-treatment><option value="CONSUMO">Consumo</option><option value="USO_TEMPORAL">Uso temporal / herramienta</option></select></td>
                        <td data-label="Cantidad"><input type="number" value="0" min="0" step="0.001" class="table-input table-input--quantity" data-detail-field="cantidad" data-output-quantity data-output-stock="0" data-output-total-stock="0"></td>
                        <td data-label="Control"><details class="output-row-details" open><summary>Motivo, observación y alertas</summary><div class="output-row-details__content"><label><span>Motivo del exceso</span><select class="table-input" data-detail-field="motivo_excedente" data-output-excess-reason><option value="">Selecciona el motivo</option><option value="NECESIDAD_OPERATIVA">Necesidad operativa adicional</option><option value="REPOSICION_MALOGRADO">Reposición de material malogrado</option></select></label><label><span>Observación</span><input type="text" maxlength="300" placeholder="Motivo del adicional" class="table-input" data-detail-field="observacion"></label><small class="field-warning" data-plan-warning hidden></small><small class="field-warning" data-reservation-warning hidden></small><small class="field-warning" data-committed-stock-warning hidden></small><small class="field-warning" data-last-tool-warning data-warning-text="no quedarán unidades disponibles de esta herramienta en Almacén" hidden></small></div></details></td>
                    </tr>
                </template>
            @endif
        </div>
    @endif
</section>
