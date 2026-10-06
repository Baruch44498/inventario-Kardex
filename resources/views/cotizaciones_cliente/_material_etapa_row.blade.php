@php
    $productoFila = ! empty($material['producto_id'])
        ? \App\Models\Producto::query()->with('unidadMedida')->find($material['producto_id'])
        : null;
@endphp
<article class="bulk-material-row" data-material-row>
    <span class="bulk-material-row__number" data-material-row-number>{{ is_numeric($indice) ? $indice + 1 : '' }}</span>
    <div class="form-field bulk-material-row__product">
        <label class="bulk-material-row__field-label" for="material_{{ $indice }}_busqueda">Producto <span class="required-mark">*</span></label>
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
        <span class="bulk-material-row__field-label">Cantidad <span class="required-mark">*</span></span>
        <input type="number" name="materiales[{{ $indice }}][cantidad]" min="{{ $productoFila && ! $productoFila->permite_fraccionamiento ? '1' : '0.001' }}" step="{{ $productoFila && ! $productoFila->permite_fraccionamiento ? '1' : '0.001' }}" value="{{ $material['cantidad'] ?? 1 }}" required data-material-quantity>
        @error('materiales.'.$indice.'.cantidad')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <div class="bulk-material-row__unit">
        <span class="bulk-material-row__field-label">Unidad</span>
        <span class="bulk-material-row__unit-value" data-material-unit>{{ $productoFila?->unidadMedida?->codigo ?: 'Automática' }}</span>
    </div>
    <label class="form-field bulk-material-row__cost">
        <span class="bulk-material-row__field-label"><span data-material-cost-label>Costo unitario ({{ $monedaInicial === 'USD' ? 'US$' : 'S/' }})</span> <span class="required-mark">*</span></span>
        <input type="number" name="materiales[{{ $indice }}][costo_unitario]" min="0.0001" step="0.0001" value="{{ $material['costo_unitario'] ?? '' }}" required data-material-cost>
        @error('materiales.'.$indice.'.costo_unitario')<small class="field-error">{{ $message }}</small>@enderror
    </label>
    <div class="bulk-material-row__subtotal">
        <span class="bulk-material-row__field-label">Subtotal</span>
        <strong data-material-subtotal>—</strong>
        <small class="bulk-material-row__equivalent" data-material-subtotal-equivalent>—</small>
        <small class="sr-only" data-material-unit-equivalent>—</small>
    </div>
    <button type="button" class="button button--ghost bulk-material-row__remove" data-remove-material-row aria-label="Quitar material" title="Quitar material">
        &times;
    </button>
</article>
