@php
    $editando = isset($proforma);
    $lineasIniciales = old(
        'detalles',
        $editando
            ? $proforma->detalles->map(fn ($detalle) => [
                'producto_id' => $detalle->producto_id,
                'cantidad' => $detalle->cantidad,
                'igv_modo' => $detalle->igv_modo,
                'observacion' => $detalle->observacion,
            ])->all()
            : [[
                'producto_id' => '',
                'cantidad' => 1,
                'igv_modo' => 'AGREGAR',
                'observacion' => '',
            ]]
    );
    $margenCliente = (float) ($clienteSeleccionado?->tipoCliente?->porcentaje_ganancia ?? 0);
@endphp

@if ($errors->any())
    <section class="notice notice--danger notice--block commercial-form-errors">
        <x-ui.icon name="error" :size="20" />
        <div>
            <strong>Revisa la información antes de guardar</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    </section>
@endif

<div class="commercial-document"
    data-commercial-document
    data-document-mode="proforma"
    data-product-search-url="{{ route('catalogos.productos.buscar') }}"
    data-margin="{{ $margenCliente }}">
    <section class="panel commercial-form-panel">
        <header class="panel-heading">
            <p class="eyebrow">Paso 1</p>
            <h2>Cliente de la venta directa</h2>
            <p>Almacén prepara esta proforma para que Logística genere la cotización y luego una orden de venta.</p>
        </header>

        <div class="form-grid">
            <input type="hidden" name="tipo_origen" value="VENTA_DIRECTA">

            <div class="form-field form-grid__full" data-client-field>
                <label for="cliente_busqueda">Cliente <span class="required-mark">*</span></label>
                <x-ui.remote-combobox
                    name="cliente_id"
                    search-id="cliente_busqueda"
                    value-id="cliente_id"
                    :search-url="route('catalogos.clientes.buscar')"
                    :selected-id="old('cliente_id', $clienteSeleccionado?->id)"
                    :selected-label="$clienteSeleccionado
                        ? $clienteSeleccionado->documentoVisible().' — '.$clienteSeleccionado->nombreVisible()
                        : ''"
                    placeholder="Documento o nombre del cliente"
                    empty-text="Cliente no encontrado. Regístralo primero en Clientes."
                    :required="true"
                />
                <small>El tipo de cliente define el margen sugerido.</small>
            </div>

            <label class="form-field">
                <span>Fecha de emisión <span class="required-mark">*</span></span>
                <input type="date" name="fecha_emision"
                    value="{{ old('fecha_emision', isset($proforma) ? $proforma->fecha_emision->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
            </label>

            <label class="form-field">
                <span>Válida hasta</span>
                <input type="date" name="fecha_validez"
                    value="{{ old('fecha_validez', isset($proforma) && $proforma->fecha_validez ? $proforma->fecha_validez->format('Y-m-d') : '') }}">
            </label>
        </div>

        <input type="hidden" name="moneda" value="PEN">
        <p class="commercial-form-note">
            Los costos y precios sugeridos de Almacén se expresan en soles. Logística definirá la moneda y el tipo de cambio al cotizar.
        </p>
    </section>

    <section class="panel commercial-form-panel">
        <header class="commercial-lines-heading">
            <div>
                <p class="eyebrow">Paso 2</p>
                <h2>Productos solicitados</h2>
                <p>La disponibilidad es informativa; guardar la proforma no reserva ni descuenta stock.</p>
            </div>
            <button type="button" class="button button--ghost button--small" data-add-commercial-line>
                <x-ui.icon name="plus" :size="16" /> Agregar producto
            </button>
        </header>

        <div class="commercial-lines" data-commercial-lines>
            @foreach ($lineasIniciales as $indice => $linea)
                @php
                    $producto = $productosSeleccionados->get((int) ($linea['producto_id'] ?? 0));
                    $unidad = $producto?->unidadMedida?->abreviatura
                        ?? $producto?->unidadMedida?->codigo
                        ?? $producto?->unidadMedida?->nombre;
                    $costo = $producto?->costoPromedioActual() ?? 0;
                    $stock = $producto?->stockActualTotal() ?? 0;
                    $sugerido = round($costo * (1 + $margenCliente / 100), 4);
                @endphp
                <article class="commercial-line" data-commercial-line
                    data-cost-pen="{{ $costo }}" data-stock="{{ $stock }}" data-unit="{{ $unidad }}">
                    <div class="commercial-line__number" data-line-number>{{ $indice + 1 }}</div>
                    <div class="form-field commercial-line__product">
                        <label>Producto <span class="required-mark">*</span></label>
                        <x-ui.remote-combobox
                            :name="'detalles['.$indice.'][producto_id]'"
                            :search-id="'detalle_'.$indice.'_producto_busqueda'"
                            :value-id="'detalle_'.$indice.'_producto_id'"
                            :search-url="route('catalogos.productos.buscar')"
                            :selected-id="$producto?->id"
                            :selected-label="$producto ? $producto->codigo.' — '.$producto->descripcion : ''"
                            placeholder="Código o descripción"
                            empty-text="Producto no encontrado. Regístralo primero en Productos."
                            :required="true"
                            :value-attributes="['data-line-product-id' => true]"
                        />
                        <small data-product-meta>
                            {{ $producto ? ($unidad ?: 'Sin unidad').' · Stock '.number_format($stock, 3) : 'Selecciona un producto del catálogo.' }}
                        </small>
                    </div>
                    <label class="form-field commercial-line__quantity">
                        <span>Cantidad <span class="required-mark">*</span></span>
                        <input type="number" name="detalles[{{ $indice }}][cantidad]" min="0.001" step="0.001"
                            value="{{ $linea['cantidad'] ?? 1 }}" data-line-quantity required>
                    </label>
                    <div class="commercial-line__suggestion">
                        <span>Costo referencial</span>
                        <strong data-line-cost>S/ {{ number_format($costo, 2) }}</strong>
                        <small>Margen {{ number_format($margenCliente, 2) }} %</small>
                    </div>
                    <div class="commercial-line__suggestion">
                        <span>Precio sugerido</span>
                        <strong data-line-suggested>{{ number_format($sugerido, 2) }}</strong>
                        <small>Logística podrá modificarlo</small>
                    </div>
                    <label class="form-field commercial-line__tax">
                        <span>IGV</span>
                        <select name="detalles[{{ $indice }}][igv_modo]" data-line-tax>
                            <option value="AGREGAR" @selected(($linea['igv_modo'] ?? 'AGREGAR') === 'AGREGAR')>Agregar 18 %</option>
                            <option value="INCLUIDO" @selected(($linea['igv_modo'] ?? '') === 'INCLUIDO')>Incluido</option>
                            <option value="NO_APLICA" @selected(($linea['igv_modo'] ?? '') === 'NO_APLICA')>No aplica</option>
                        </select>
                    </label>
                    <label class="form-field commercial-line__observation">
                        <span>Observación</span>
                        <input type="text" name="detalles[{{ $indice }}][observacion]" maxlength="300"
                            value="{{ $linea['observacion'] ?? '' }}" placeholder="Opcional">
                    </label>
                    <button type="button" class="commercial-line__remove" data-remove-commercial-line
                        aria-label="Quitar producto" title="Quitar producto">&times;</button>
                </article>
            @endforeach
        </div>
    </section>

    <section class="commercial-bottom-grid">
        <article class="panel commercial-form-panel">
            <header class="panel-heading">
                <p class="eyebrow">Paso 3</p>
                <h2>Condiciones</h2>
            </header>
            <div class="form-grid form-grid--single">
                <label class="form-field"><span>Condiciones de pago</span><textarea name="condiciones_pago" maxlength="500">{{ old('condiciones_pago', $proforma->condiciones_pago ?? '') }}</textarea></label>
                <label class="form-field"><span>Condiciones de entrega</span><textarea name="condiciones_entrega" maxlength="500">{{ old('condiciones_entrega', $proforma->condiciones_entrega ?? '') }}</textarea></label>
                <label class="form-field"><span>Observación interna</span><textarea name="observacion" maxlength="500">{{ old('observacion', $proforma->observacion ?? '') }}</textarea></label>
            </div>
        </article>

        <aside class="panel commercial-total-card">
            <p class="eyebrow">Estimación sugerida</p>
            <div><span>Base</span><strong data-document-subtotal>0.00</strong></div>
            <div><span>IGV</span><strong data-document-tax>0.00</strong></div>
            <div class="commercial-total-card__main"><span>Total</span><strong data-document-total>0.00</strong></div>
            <small>No mueve stock ni crea un movimiento de Kardex.</small>
        </aside>
    </section>

    <div class="form-actions commercial-form-actions">
        <button type="button" class="button button--ghost" data-cancel-form data-cancel-url="{{ route('proformas.index') }}">Cancelar</button>
        <button type="submit" class="button button--primary" data-submit-button data-loading-text="Guardando...">
            <span data-submit-icon><x-ui.icon name="save" :size="18" /></span>
            <span class="button-spinner" data-submit-spinner hidden></span>
            <span data-submit-label>{{ $editando ? 'Guardar cambios' : 'Guardar borrador' }}</span>
        </button>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('js/proforma-documentos.js') }}"></script>
@endpush
