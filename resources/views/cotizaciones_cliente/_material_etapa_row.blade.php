@php
    $productoFila = ! empty($material['producto_id'])
        ? \App\Models\Producto::query()->with('unidadMedida')->find($material['producto_id'])
        : null;
@endphp
<article class="bulk-material-row" data-material-row>
    <span class="bulk-material-row__number" data-material-row-number></span>
    <div class="form-field bulk-material-row__product">
        <label for="material_{{ $indice }}_busqueda">Producto <span class="required-mark">*</span></label>
        <x-ui.remote-combobox
            :name="'materiales['.$indice.'][producto_id]'"
            :search-id="'material_'.$indice.'_busqueda'"
            :value-id="'material_'.$indice.'_producto_id'"
            :search-url="route('catalogos.productos.buscar')"
            :selected-id="$material['producto_id'] ?? null"
            :selected-label="$productoFila ? $productoFila->codigo.' — '.$productoFila->descripcion : ''"
            placeholder="Código o descripción"
            empty-text="Producto no encontrado. Regístralo primero en almacén."
            :required="true"
        />
        @error('materiales.'.$indice.'.producto_id')<small class="field-error">{{ $message }}</small>@enderror
    </div>
    <label class="form-field bulk-material-row__quantity">
        <span>Cantidad <span class="required-mark">*</span></span>
        <input type="number" name="materiales[{{ $indice }}][cantidad]" min="{{ $productoFila && ! $productoFila->permite_fraccionamiento ? '1' : '0.001' }}" step="{{ $productoFila && ! $productoFila->permite_fraccionamiento ? '1' : '0.001' }}" value="{{ $material['cantidad'] ?? 1 }}" required data-material-quantity>
        @error('materiales.'.$indice.'.cantidad')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <label class="form-field bulk-material-row__unit">
        <span>Unidad</span>
        <input type="text" value="{{ $productoFila?->unidadMedida?->codigo ?: 'Automática' }}" readonly data-material-unit>
    </label>
    <label class="form-field bulk-material-row__cost">
        <span>Costo unitario <span class="required-mark">*</span></span>
        <input type="number" name="materiales[{{ $indice }}][costo_unitario]" min="0.0001" step="0.0001" value="{{ $material['costo_unitario'] ?? '' }}" required data-material-cost>
        @error('materiales.'.$indice.'.costo_unitario')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <div class="bulk-material-row__subtotal">
        <span>Subtotal</span>
        <strong data-material-subtotal>—</strong>
    </div>
    <button type="button" class="button button--danger button--small bulk-material-row__remove" data-remove-material-row aria-label="Quitar material" title="Quitar material">
        &times;
    </button>
</article>
