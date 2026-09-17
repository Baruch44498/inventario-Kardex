<section id="paso-productos" class="panel" data-flow-step-section="3">
    <div class="panel-heading panel-heading--split">
        <div>
            <p class="eyebrow">Productos y ubicación</p>
            <h2>{{ match($motivo) { 'COMPRA' => 'Productos recibidos', 'DEVOLUCION_MATERIAL_MALOGRADO' => 'Material malogrado que se registra', default => 'Productos que regresan al stock' } }}</h2>
            <p>El sistema solo permite ingresar hasta la cantidad pendiente del documento original. Las devoluciones y reposiciones conservan la trazabilidad de la salida.</p>
        </div>
        @if ($motivo === 'COMPRA' && $filas->isNotEmpty())
            <button type="button" class="button button--ghost button--small" data-fill-pending>
                <x-ui.icon name="check-circle" :size="16" /> Recibir todo lo pendiente
            </button>
        @endif
    </div>

    @error('detalles')
        <div class="notice notice--danger notice--block"><x-ui.icon name="error" :size="18" /><span>{{ $message }}</span></div>
    @enderror

    @if ($filas->isEmpty())
        <div class="empty-table-state empty-table-state--document-lines">
            <div class="document-lines-empty">
                <span class="empty-state__icon empty-state__icon--success document-lines-empty__icon"><x-ui.icon name="check-circle" :size="30" /></span>
                <div class="document-lines-empty__copy"><strong>No hay cantidades pendientes</strong><p>El origen seleccionado ya fue recibido, devuelto o repuesto completamente.</p></div>
            </div>
        </div>
    @else
        <div class="table-wrap table-wrap--wide table-wrap--responsive">
            <table class="data-table entry-lines-table">
                <thead>
                    <tr>
                        <th class="table-sticky--start">Producto</th>
                        <th>Pendiente</th>
                        <th>Cantidad que ingresa</th>
                        <th>Repisa destino</th>
                        @if ($motivo === 'COMPRA')<th>Costo inventario (S/)</th><th>Lote / vencimiento</th>@endif
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $indice => $fila)
                        @php
                            $producto = $fila['producto'];
                            $repisaId = old("detalles.{$indice}.repisa_id", $fila['repisa_default_id']);
                            $repisaSeleccionada = $repisaId ? $repisasSeleccionadas->get((int) $repisaId) : null;
                        @endphp
                        <tr data-entry-row>
                            <td class="table-sticky--start">
                                <input type="hidden" name="detalles[{{ $indice }}][producto_id]" value="{{ $producto->id }}">
                                @if ($fila['orden_compra_detalle_id'])<input type="hidden" name="detalles[{{ $indice }}][orden_compra_detalle_id]" value="{{ $fila['orden_compra_detalle_id'] }}">@endif
                                @if ($fila['nota_salida_detalle_id'])<input type="hidden" name="detalles[{{ $indice }}][nota_salida_detalle_id]" value="{{ $fila['nota_salida_detalle_id'] }}">@endif
                                @if ($fila['proforma_detalle_id'])<input type="hidden" name="detalles[{{ $indice }}][proforma_detalle_id]" value="{{ $fila['proforma_detalle_id'] }}">@endif
                                @if ($motivo !== 'COMPRA')<input type="hidden" name="detalles[{{ $indice }}][costo_unitario]" value="{{ $fila['costo_default'] }}">@endif
                                <strong>{{ $producto->codigo }}</strong>
                                <span>{{ $producto->descripcion }}</span>
                                <small>{{ $producto->unidadMedida?->codigo ?? 'UND' }}</small>
                            </td>
                            <td><strong><x-ui.quantity :value="$fila['pendiente']" /></strong></td>
                            <td>
                                @if ($motivo === 'COMPRA' && $fila['presentacion_nombre'])
                                    @php($modoRecepcion = old("detalles.{$indice}.unidad_recepcion", 'PRESENTACION'))
                                    <div
                                        data-reception-measure
                                        data-pending-base="{{ $fila['pendiente'] }}"
                                        data-pending-presentation="{{ $fila['pendiente_presentacion'] }}"
                                        data-factor="{{ $fila['factor_conversion'] }}"
                                        data-base-unit="{{ $producto->unidadMedida?->codigo ?? 'UND' }}"
                                        data-base-step="{{ $producto->permite_fraccionamiento ? '0.01' : '1' }}"
                                        data-presentation-name="{{ $fila['presentacion_nombre'] }}"
                                    >
                                        <select name="detalles[{{ $indice }}][unidad_recepcion]" class="table-input" data-reception-mode>
                                            <option value="PRESENTACION" @selected($modoRecepcion === 'PRESENTACION')>{{ $fila['presentacion_nombre'] }}</option>
                                            <option value="BASE" @selected($modoRecepcion === 'BASE')>{{ $producto->unidadMedida?->codigo ?? 'UND' }} sueltas</option>
                                        </select>
                                        <input
                                            type="number"
                                            name="detalles[{{ $indice }}][cantidad_recepcion]"
                                            value="{{ old("detalles.{$indice}.cantidad_recepcion", 0) }}"
                                            min="0"
                                            step="0.001"
                                            class="table-input"
                                            data-entry-quantity
                                        >
                                        <small class="table-field-help" data-reception-conversion></small>
                                    </div>
                                @else
                                    <input type="number" name="detalles[{{ $indice }}][cantidad]" value="{{ old("detalles.{$indice}.cantidad", 0) }}" min="0" max="{{ $fila['pendiente'] }}" step="{{ $producto->permite_fraccionamiento ? '0.01' : '1' }}" class="table-input" data-entry-quantity data-pending-value="{{ $fila['pendiente'] }}">
                                    <small class="table-field-help">{{ $producto->permite_fraccionamiento ? 'Admite decimales, por ejemplo 3.20.' : 'Solo admite cantidades enteras.' }}</small>
                                @endif
                                @error("detalles.{$indice}.cantidad")<small class="field-error table-field-error">{{ $message }}</small>@enderror
                                @error("detalles.{$indice}.cantidad_recepcion")<small class="field-error table-field-error">{{ $message }}</small>@enderror
                            </td>
                            <td>
                                @if ($motivo === 'DEVOLUCION_MATERIAL_MALOGRADO')
                                    <input type="hidden" name="detalles[{{ $indice }}][repisa_id]" value="{{ $fila['repisa_default_id'] }}">
                                    <span class="badge badge--warning">No vuelve a stock</span>
                                    <small>Repisa original: {{ $repisaSeleccionada?->codigo ?? '—' }}</small>
                                @else
                                    <x-ui.remote-combobox
                                        :name="'detalles['.$indice.'][repisa_id]'"
                                        :search-id="'repisa_busqueda_'.$indice"
                                        :value-id="'repisa_id_'.$indice"
                                        :search-url="route('catalogos.repisas.buscar')"
                                        :selected-id="$repisaSeleccionada?->id"
                                        :selected-label="$repisaSeleccionada ? $repisaSeleccionada->codigo.($repisaSeleccionada->descripcion ? ' — '.$repisaSeleccionada->descripcion : '') : ''"
                                        placeholder="Código de repisa"
                                        empty-text="No se encontró una repisa activa."
                                    />
                                    @error("detalles.{$indice}.repisa_id")<small class="field-error table-field-error">{{ $message }}</small>@enderror
                                @endif
                            </td>
                            @if ($motivo === 'COMPRA')
                                <td>
                                    <input type="number" name="detalles[{{ $indice }}][costo_unitario]" value="{{ $fila['costo_default'] }}" min="0.0001" step="0.0001" class="table-input table-input--readonly" readonly aria-readonly="true">
                                    <small class="table-field-help">{{ $facturaSeleccionada ? 'Costo real de la factura' : 'Costo provisional de la OC' }}, con IGV incluido y en soles.</small>
                                </td>
                                <td>
                                    <div class="entry-lot-fields">
                                        <input type="text" name="detalles[{{ $indice }}][lote]" value="{{ old("detalles.{$indice}.lote") }}" maxlength="80" placeholder="Lote" class="table-input">
                                        <input type="date" name="detalles[{{ $indice }}][fecha_vencimiento]" value="{{ old("detalles.{$indice}.fecha_vencimiento") }}" class="table-input">
                                    </div>
                                </td>
                            @endif
                            <td><input type="text" name="detalles[{{ $indice }}][observacion]" value="{{ old("detalles.{$indice}.observacion") }}" maxlength="300" placeholder="Opcional" class="table-input"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
