@php
    $materialesIniciales = old('materiales', [
        ['producto_id' => null, 'cantidad' => 1, 'costo_unitario' => null],
        ['producto_id' => null, 'cantidad' => 1, 'costo_unitario' => null],
    ]);
    $monedaInicial = old('moneda', $cotizacion->moneda ?: 'PEN');
    $tipoCambioInicial = old(
        'tipo_cambio',
        (float) ($componenteInicial?->tipo_cambio_comparacion ?: $cotizacion->tipo_cambio) ?: null
    );
@endphp

<form
    method="POST"
    action="{{ route('cotizaciones-cliente.presupuesto.materiales.store', $cotizacion) }}"
    class="bulk-material-form"
    data-bulk-material-form
    data-product-search-url="{{ route('catalogos.productos.buscar') }}"
>
    @csrf
    <input type="hidden" name="componente_id" value="{{ old('componente_id', $componenteInicial?->id) }}">

    <div class="bulk-material-stage">
        <label class="form-field bulk-material-stage__name">
            <span>Etapa o grupo de trabajo <span class="required-mark">*</span></span>
            <input type="text" name="grupo_costo" maxlength="150" value="{{ old('grupo_costo') }}" placeholder="Ej. Estructura del tanque" required>
            <small>Se escribe una sola vez y se aplicará a todos los materiales de abajo.</small>
            @error('grupo_costo')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <div class="bulk-material-defaults">
            <label class="form-field">
                <span>Moneda</span>
                <select name="moneda" data-bulk-currency required>
                    @foreach (\App\Models\CotizacionPresupuesto::MONEDAS as $codigo => $nombre)
                        <option value="{{ $codigo }}" @selected($monedaInicial === $codigo)>{{ $codigo }} · {{ $nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-field">
                <span>Tipo de cambio</span>
                <input type="number" name="tipo_cambio" min="0.1" max="100" step="0.000001" value="{{ $tipoCambioInicial }}" required>
            </label>
            <label class="form-field">
                <span>Margen (%)</span>
                <input type="number" name="margen_porcentaje" min="0" max="999.9999" step="0.0001" value="{{ old('margen_porcentaje', 0) }}" required>
            </label>
            <label class="form-field">
                <span>IGV de compra</span>
                <select name="igv_modo" required>
                    @foreach (\App\Models\CotizacionPresupuesto::MODOS_IGV as $codigo => $nombre)
                        <option value="{{ $codigo }}" @selected(old('igv_modo', 'NO_APLICA') === $codigo)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-field">
                <span>IGV compra (%)</span>
                <input type="number" name="igv_porcentaje" min="0" max="100" step="0.0001" value="{{ old('igv_porcentaje', 18) }}" required>
            </label>
            <label class="form-field">
                <span>IGV venta (%)</span>
                <input type="number" name="igv_venta_porcentaje" min="0" max="100" step="0.0001" value="{{ old('igv_venta_porcentaje', 18) }}" required>
            </label>
        </div>
        <p class="bulk-material-defaults__help">Estos valores se aplicarán a todas las filas del bloque.</p>
    </div>

    @error('materiales')<div class="notice notice--danger notice--block"><span>{{ $message }}</span></div>@enderror

    <div class="bulk-material-list" data-bulk-material-list>
        @foreach ($materialesIniciales as $indice => $material)
            @include('cotizaciones_cliente._material_etapa_row', [
                'indice' => $indice,
                'material' => $material,
            ])
        @endforeach
    </div>

    <template data-bulk-material-template>
        @include('cotizaciones_cliente._material_etapa_row', [
            'indice' => '__INDEX__',
            'material' => ['producto_id' => null, 'cantidad' => 1, 'costo_unitario' => null],
        ])
    </template>

    <div class="bulk-material-actions">
        <button type="button" class="button button--ghost" data-add-material-row>
            <x-ui.icon name="plus" :size="17" />
            Agregar otra fila
        </button>
        <span data-bulk-material-count></span>
        <button type="submit" class="button button--primary">
            <x-ui.icon name="check-circle" :size="17" />
            Guardar todos los materiales
        </button>
    </div>

    <label class="form-field">
        <span>Observación común (opcional)</span>
        <textarea name="observacion" rows="2" maxlength="500" placeholder="Dato aplicable a todos los materiales de esta etapa">{{ old('observacion') }}</textarea>
    </label>
</form>
