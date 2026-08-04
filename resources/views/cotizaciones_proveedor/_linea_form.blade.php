@php
    $modoDescuento = ($linea['descuento_modo'] ?? 'SIN_DESCUENTO') === 'APLICAR'
        ? 'APLICAR'
        : 'SIN_DESCUENTO';
    $tipoDescuento = $linea['descuento_tipo'] ?? '';
    $modoIgv = $linea['igv_modo'] ?? 'AGREGAR';
    $productoSeleccionado = $productos->firstWhere(
        'id',
        (int) ($linea['producto_id'] ?? 0)
    );
    $unidadSeleccionada = $productoSeleccionado?->unidadMedida;
    $etiquetaProducto = $productoSeleccionado
        ? $productoSeleccionado->codigo.' — '.$productoSeleccionado->descripcion
            .($unidadSeleccionada
                ? ' · '.($unidadSeleccionada->abreviatura
                    ?? $unidadSeleccionada->codigo
                    ?? $unidadSeleccionada->nombre)
                : '')
        : '';
@endphp

<div class="supplier-quote-line" data-supplier-quote-line>
    <div class="supplier-quote-line__index"><span>{{ $numero }}</span></div>

    <div class="supplier-quote-line__fields">
        <div class="form-field supplier-quote-line__product">
            <label for="producto_busqueda_{{ $indice }}">
                Producto <span class="required-mark">*</span>
            </label>
            <div class="supplier-product-combobox" data-product-combobox>
                <div class="supplier-product-combobox__control">
                    <x-ui.icon name="search" :size="17" />
                    <input id="producto_busqueda_{{ $indice }}" type="search"
                        value="{{ $etiquetaProducto }}" placeholder="Código o descripción"
                        autocomplete="off" required role="combobox"
                        aria-autocomplete="list" aria-expanded="false"
                        data-product-search>
                    <button type="button" class="supplier-product-combobox__clear"
                        title="Limpiar producto" aria-label="Limpiar producto"
                        data-product-clear @if (! $productoSeleccionado) hidden @endif>
                        <x-ui.icon name="close" :size="15" />
                    </button>
                </div>
                <input type="hidden" name="detalles[{{ $indice }}][producto_id]"
                    value="{{ $linea['producto_id'] ?? '' }}" data-line-product>
                <div class="supplier-product-combobox__results" role="listbox"
                    data-product-results hidden></div>
            </div>
            <small>Escribe el código o parte de la descripción y selecciona un resultado.</small>
            @error("detalles.{$indice}.producto_id")
                <small class="field-error" role="alert">{{ $message }}</small>
            @enderror
        </div>

        <label class="form-field">
            <span>Cantidad</span>
            <input name="detalles[{{ $indice }}][cantidad]" type="number"
                min="0.001" step="0.001" value="{{ $linea['cantidad'] ?? 1 }}"
                required data-line-quantity>
        </label>

        <label class="form-field">
            <span>Precio informado</span>
            <input name="detalles[{{ $indice }}][precio_unitario]" type="number"
                min="0" step="0.0001" value="{{ $linea['precio_unitario'] ?? '' }}"
                required data-line-price>
        </label>

        <label class="form-field">
            <span>Tratamiento del IGV</span>
            <select name="detalles[{{ $indice }}][igv_modo]" required data-line-tax-mode>
                <option value="INCLUIDO" @selected($modoIgv === 'INCLUIDO')>
                    El precio ya incluye IGV
                </option>
                <option value="AGREGAR" @selected($modoIgv === 'AGREGAR')>
                    Agregar IGV al precio
                </option>
                <option value="NO_APLICA" @selected($modoIgv === 'NO_APLICA')>
                    No aplica IGV
                </option>
            </select>
        </label>

        <div class="form-field supplier-quote-discount-question">
            <span>¿El proveedor detalla un descuento?</span>
            <label class="supplier-quote-switch">
                <input type="checkbox" data-line-discount-switch
                    @checked($modoDescuento === 'APLICAR')>
                <span class="supplier-quote-switch__track" aria-hidden="true"></span>
                <span data-line-discount-answer>
                    {{ $modoDescuento === 'APLICAR' ? 'Sí, lo detalla' : 'No' }}
                </span>
            </label>
            <input type="hidden" name="detalles[{{ $indice }}][descuento_modo]"
                value="{{ $modoDescuento }}" data-line-discount-mode>
        </div>

        <div class="supplier-quote-line__discount-fields"
            data-line-discount-fields @if ($modoDescuento !== 'APLICAR') hidden @endif>
            <label class="form-field">
                <span>Tipo de descuento</span>
                <select name="detalles[{{ $indice }}][descuento_tipo]" data-line-discount-type>
                    <option value="">Seleccionar</option>
                    <option value="PORCENTAJE" @selected($tipoDescuento === 'PORCENTAJE')>
                        Porcentaje
                    </option>
                    <option value="MONTO" @selected($tipoDescuento === 'MONTO')>
                        Monto por unidad
                    </option>
                </select>
            </label>

            <label class="form-field">
                <span data-line-discount-value-label>Valor del descuento</span>
                <input name="detalles[{{ $indice }}][descuento_valor]" type="number"
                    min="0" step="0.0001" value="{{ $linea['descuento_valor'] ?? '' }}"
                    data-line-discount-value placeholder="Ej. 10">
            </label>

            <small data-line-discount-help>
                El descuento indicado se aplicará antes de calcular el IGV.
            </small>
        </div>

        <label class="form-field">
            <span>Marca ofrecida</span>
            <input name="detalles[{{ $indice }}][marca_ofertada]" type="text"
                maxlength="120" value="{{ $linea['marca_ofertada'] ?? '' }}"
                placeholder="Opcional">
        </label>

        <label class="form-field supplier-quote-line__observation">
            <span>Observación</span>
            <input name="detalles[{{ $indice }}][observacion]" type="text"
                maxlength="300" value="{{ $linea['observacion'] ?? '' }}"
                placeholder="Presentación, plazo o detalle">
        </label>

        <div class="supplier-quote-line__calculation">
            <div><span>Base sin IGV</span><strong data-line-base>—</strong></div>
            <div><span>IGV</span><strong data-line-tax>—</strong></div>
            <div class="supplier-quote-line__calculation-main">
                <span>Total línea</span><strong data-line-total>—</strong>
            </div>
        </div>
    </div>

    <button type="button" class="icon-button icon-button--danger"
        title="Quitar producto" data-remove-supplier-quote-line>
        <x-ui.icon name="close" :size="17" />
    </button>
</div>
