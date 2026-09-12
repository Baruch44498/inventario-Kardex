@php
    $lineasGuardadas = $cotizacion->detalles->map(fn ($detalle) => [
            'producto_id' => $detalle->producto_id,
            'cantidad' => $detalle->cantidad,
            'precio_unitario' => $detalle->precio_unitario,
            'precio_sugerido' => $detalle->precio_sugerido,
            'costo_referencia' => $detalle->costo_referencia,
            'igv_modo' => $detalle->igv_modo,
        ])->all();
    $lineasIniciales = old('detalles', $lineasGuardadas !== []
        ? $lineasGuardadas
        : [[
            'producto_id' => '',
            'cantidad' => 1,
            'precio_unitario' => '',
            'igv_modo' => 'AGREGAR',
        ]]);
    $margenCliente = (float) ($clienteSeleccionado?->tipoCliente?->porcentaje_ganancia ?? 0);
    $direccionSeleccionada = (int) old(
        'cliente_direccion_id',
        $cotizacion->cliente_direccion_id
    );
    $monedaSeleccionada = old('moneda', $cotizacion->moneda);
    $faltaDireccionFiscal = $clienteSeleccionado?->requiereDireccionFiscal()
        && ! $clienteSeleccionado->tieneDireccionFiscalActiva();
@endphp

@if ($errors->any())
    <section class="notice notice--danger notice--block commercial-form-errors">
        <x-ui.icon name="error" :size="20" />
        <div><strong>Revisa la cotización</strong><span>{{ $errors->first() }}</span></div>
    </section>
@endif

<div class="commercial-document"
    data-commercial-document
    data-document-mode="quote"
    data-product-search-url="{{ route('catalogos.productos.buscar') }}"
    data-client-relations-url="{{ route('catalogos.clientes.relaciones') }}"
    data-margin="{{ $margenCliente }}">
    <section class="panel commercial-form-panel">
        <header class="panel-heading">
            <p class="eyebrow">Datos comerciales</p>
            <h2>Cliente, moneda y vigencia</h2>
            <p>El cambio de cliente recalcula la referencia, pero nunca sobrescribe automáticamente el precio negociado.</p>
        </header>
        <div class="form-grid">
            <div class="form-field form-grid__full">
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
                    empty-text="Cliente no encontrado. Regístralo primero."
                    :required="true"
                    :value-attributes="['data-commercial-client' => true]"
                />
            </div>
            <div
                class="notice notice--warning notice--block form-grid__full commercial-fiscal-warning"
                data-fiscal-warning
                @if (! $faltaDireccionFiscal) hidden @endif
            >
                <x-ui.icon name="warning" :size="18" />
                <span>
                    Este cliente con RUC no tiene una <strong>Dirección fiscal</strong>
                    activa. Corrige el registro del cliente antes de utilizar ese dato
                    en el documento.
                </span>
            </div>
            <label class="form-field"><span>Fecha de emisión <span class="required-mark">*</span></span><input type="date" name="fecha_emision" value="{{ old('fecha_emision', $cotizacion->fecha_emision->format('Y-m-d')) }}" required></label>
            <label class="form-field"><span>Válida hasta</span><input type="date" name="fecha_validez" value="{{ old('fecha_validez', $cotizacion->fecha_validez?->format('Y-m-d')) }}"></label>
            <label class="form-field">
                <span>Moneda <span class="required-mark">*</span></span>
                <select name="moneda" data-document-currency required>
                    <option value="PEN" @selected(old('moneda', $cotizacion->moneda) === 'PEN')>PEN — Soles</option>
                    <option value="USD" @selected(old('moneda', $cotizacion->moneda) === 'USD')>USD — Dólares</option>
                </select>
            </label>
            <label class="form-field" data-exchange-field @if ($monedaSeleccionada !== 'USD') hidden @endif>
                <span>Tipo de cambio (PEN por USD) <span class="required-mark">*</span></span>
                <input
                    type="number"
                    name="tipo_cambio"
                    min="0.000001"
                    step="0.000001"
                    value="{{ $monedaSeleccionada === 'USD'
                        ? old('tipo_cambio', $cotizacion->tipo_cambio)
                        : '' }}"
                    data-exchange-input
                    @required($monedaSeleccionada === 'USD')
                >
                @error('tipo_cambio')<small class="field-error">{{ $message }}</small>@enderror
            </label>
        </div>
    </section>

    <section class="panel commercial-form-panel">
        <header class="panel-heading">
            <p class="eyebrow">Venta cotizada</p>
            <h2>Información que heredará la Orden de Venta</h2>
            <p>La cotización aprobada generará una OV con este cliente, dirección y detalle de productos.</p>
        </header>

        <div class="form-grid">
            <label class="form-field">
                <span>Dirección de entrega</span>
                <select name="cliente_direccion_id" data-commercial-address>
                    <option value="">Sin dirección específica</option>
                    @foreach ($direcciones as $direccion)
                        <option value="{{ $direccion->id }}"
                            @selected($direccionSeleccionada === $direccion->id)>
                            {{ $direccion->es_fiscal ? 'Dirección fiscal — ' : '' }}
                            {{ $direccion->destino ?: $direccion->direccion ?: 'Dirección '.$direccion->id }}
                            {{ $direccion->ciudad ? ' · '.$direccion->ciudad : '' }}
                        </option>
                    @endforeach
                </select>
                <small>Opcional. Debe pertenecer al cliente seleccionado.</small>
                @error('cliente_direccion_id')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <label class="form-field form-grid__full">
                <span>Descripción de la venta <span class="required-mark">*</span></span>
                <textarea name="descripcion_trabajo" rows="4" minlength="5" maxlength="500"
                    placeholder="Describe el pedido o la venta solicitada por el cliente"
                    required>{{ old('descripcion_trabajo', $cotizacion->descripcion_trabajo) }}</textarea>
                <small>Este texto aparecerá en la cotización y permitirá identificar la OV.</small>
                @error('descripcion_trabajo')<small class="field-error">{{ $message }}</small>@enderror
            </label>
        </div>
    </section>

    <section class="panel commercial-form-panel">
        <header class="commercial-lines-heading">
            <div><p class="eyebrow">Detalle comercial</p><h2>Productos y precios</h2><p>Agrega los productos que se entregarán al cliente. El precio cotizado es editable.</p></div>
            <button type="button" class="button button--ghost button--small" data-add-commercial-line><x-ui.icon name="plus" :size="16" /> Agregar producto</button>
        </header>

        <div class="commercial-lines" data-commercial-lines>
            @foreach ($lineasIniciales as $indice => $linea)
                @php
                    $producto = $productosSeleccionados->get((int) ($linea['producto_id'] ?? 0));
                    $unidad = $producto?->unidadMedida?->abreviatura ?? $producto?->unidadMedida?->codigo ?? $producto?->unidadMedida?->nombre;
                    $costo = (float) ($linea['costo_referencia'] ?? 0);
                    $costoPen = (float) ($producto?->costoPromedioActual() ?? $costo);
                    $stock = $producto?->stockActualTotal() ?? 0;
                    $sugerido = (float) ($linea['precio_sugerido'] ?? round($costo * (1 + $margenCliente / 100), 2));
                @endphp
                <article class="commercial-line commercial-line--quote" data-commercial-line
                    data-cost-pen="{{ $costoPen }}" data-stock="{{ $stock }}" data-unit="{{ $unidad }}">
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
                            empty-text="Producto no encontrado. Regístralo primero."
                            :required="true"
                            :value-attributes="['data-line-product-id' => true]"
                        />
                        <small data-product-meta>{{ $producto ? ($unidad ?: 'Sin unidad').' · Stock '.number_format($stock, 2) : 'Selecciona un producto.' }}</small>
                    </div>
                    <label class="form-field commercial-line__quantity"><span>Cantidad <span class="required-mark">*</span></span><input type="number" name="detalles[{{ $indice }}][cantidad]" min="0.001" step="0.001" value="{{ $linea['cantidad'] ?? 1 }}" data-line-quantity required></label>
                    <label class="form-field commercial-line__price">
                        <span>Precio cotizado <span class="required-mark">*</span></span>
                        <input
                            type="number"
                            name="detalles[{{ $indice }}][precio_unitario]"
                            min="0.0001"
                            step="0.0001"
                            value="{{ filled($linea['precio_unitario'] ?? null)
                                ? number_format((float) $linea['precio_unitario'], 2, '.', '')
                                : '' }}"
                            data-line-price
                            required
                        >
                        <small class="commercial-price-help">
                            Sugerido:
                            <strong data-line-suggested>
                                {{ $monedaSeleccionada === 'USD' ? 'US$' : 'S/' }}
                                {{ number_format($sugerido, 2) }}
                            </strong>
                            · Margen: <span data-line-margin>{{ number_format($margenCliente, 2) }}</span> %
                        </small>
                    </label>
                    <label class="form-field commercial-line__tax"><span>IGV</span><select name="detalles[{{ $indice }}][igv_modo]" data-line-tax><option value="AGREGAR" @selected(($linea['igv_modo'] ?? 'AGREGAR') === 'AGREGAR')>Agregar 18 %</option><option value="INCLUIDO" @selected(($linea['igv_modo'] ?? '') === 'INCLUIDO')>Incluido</option><option value="NO_APLICA" @selected(($linea['igv_modo'] ?? '') === 'NO_APLICA')>No aplica</option></select></label>
                    <button type="button" class="commercial-line__remove" data-remove-commercial-line aria-label="Quitar producto" title="Quitar producto">&times;</button>
                </article>
            @endforeach
        </div>
    </section>

    <section class="commercial-bottom-grid">
        <article class="panel commercial-form-panel">
            <header class="panel-heading"><p class="eyebrow">Condiciones finales</p><h2>Acuerdos de la cotización</h2></header>
            <div class="form-grid form-grid--single">
                <label class="form-field"><span>Condiciones de pago</span><textarea name="condiciones_pago" maxlength="500">{{ old('condiciones_pago', $cotizacion->condiciones_pago) }}</textarea></label>
                <label class="form-field"><span>Condiciones de entrega</span><textarea name="condiciones_entrega" maxlength="500">{{ old('condiciones_entrega', $cotizacion->condiciones_entrega) }}</textarea></label>
                <label class="form-field"><span>Observación</span><textarea name="observacion" maxlength="500">{{ old('observacion', $cotizacion->observacion) }}</textarea></label>
            </div>
        </article>
        <aside class="panel commercial-total-card">
            <p class="eyebrow">Total cotizado</p>
            <div><span>Base</span><strong data-document-subtotal>0.00</strong></div>
            <div><span>IGV</span><strong data-document-tax>0.00</strong></div>
            <div class="commercial-total-card__main"><span>Total</span><strong data-document-total>0.00</strong></div>
            <small>La cotización sigue editable hasta aprobarla y generar la orden.</small>
        </aside>
    </section>

    <div class="form-actions commercial-form-actions">
        <button type="button" class="button button--ghost" data-cancel-form
            data-cancel-url="{{ $creandoDirecta
                ? route('cotizaciones-cliente.index')
                : route('cotizaciones-cliente.show', $cotizacion) }}">Cancelar</button>
        <button type="submit" class="button button--primary" data-submit-button data-loading-text="Guardando...">
            <span data-submit-icon><x-ui.icon name="save" :size="18" /></span><span class="button-spinner" data-submit-spinner hidden></span><span data-submit-label>{{ $creandoDirecta ? 'Crear VRS1 abierta' : 'Guardar versión abierta' }}</span>
        </button>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('js/proforma-documentos.js') }}"></script>
@endpush
