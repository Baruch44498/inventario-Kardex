@php
    $materialesIniciales = old('materiales', [
        ['producto_id' => null, 'cantidad' => 1, 'costo_unitario' => null],
        ['producto_id' => null, 'cantidad' => 1, 'costo_unitario' => null],
    ]);
    $areaInicial = old('area_nombre', old('grupo_costo', request('area')));
    $monedaInicial = old('moneda', $cotizacion->moneda ?: 'PEN');
    $tipoCambioInicial = old(
        'tipo_cambio',
        (float) ($componenteInicial?->tipo_cambio_comparacion ?: $cotizacion->tipo_cambio) ?: null
    );
    $igvModoInicial = old('igv_modo', 'NO_APLICA');
    $igvCompraInicial = $igvModoInicial === 'NO_APLICA' ? 0 : \App\Models\CotizacionPresupuesto::IGV_PORCENTAJE;
    $margenConfigurado = (float) $cotizacion->margen_cliente_porcentaje;
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
            <span>Área de la orden <span class="required-mark">*</span></span>
            <input type="text" name="area_nombre" maxlength="150" value="{{ $areaInicial }}" list="areas_cotizacion_materiales" placeholder="Ej. SISTEMA NEUMÁTICO" required>
            <datalist id="areas_cotizacion_materiales">
                @foreach ($cotizacion->todasLasAreas as $areaDisponible)
                    <option value="{{ $areaDisponible->nombre }}"></option>
                @endforeach
            </datalist>
            <small>Todos los materiales de abajo quedarán planificados dentro de esta área.</small>
            @error('area_nombre')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <div class="bulk-material-defaults bulk-material-defaults--simplified">
            <label class="form-field">
                <span>Moneda</span>
                <select name="moneda" data-bulk-currency required>
                    @foreach (\App\Models\CotizacionPresupuesto::MONEDAS as $codigo => $nombre)
                        <option value="{{ $codigo }}" @selected($monedaInicial === $codigo)>{{ $codigo }} · {{ $nombre }}</option>
                    @endforeach
                </select>
            </label>
            <input type="hidden" name="tipo_cambio" value="{{ sprintf('%.6F', (float) $tipoCambioInicial) }}">
            <input type="hidden" name="margen_porcentaje" value="{{ $margenConfigurado }}">
            <label class="form-field">
                <span>IGV de compra</span>
                <select name="igv_modo" data-bulk-tax-mode required>
                    @foreach (\App\Models\CotizacionPresupuesto::MODOS_IGV as $codigo => $nombre)
                        <option value="{{ $codigo }}" @selected($igvModoInicial === $codigo)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </label>
            <input type="hidden" name="igv_porcentaje" value="{{ $igvCompraInicial }}" data-bulk-tax-rate>
            <input type="hidden" name="igv_venta_porcentaje" value="{{ \App\Models\CotizacionPresupuesto::IGV_PORCENTAJE }}">
            <div class="bulk-material-rules" aria-label="Reglas financieras aplicadas">
                <div><span>Tipo de cambio</span><strong>{{ number_format((float) $tipoCambioInicial, 2) }}</strong></div>
                <div><span>Margen comercial</span><strong>{{ number_format($margenConfigurado, 2) }}%</strong></div>
                <div><span>IGV compra</span><strong data-bulk-tax-rate-label>{{ $igvModoInicial === 'NO_APLICA' ? 'No aplica' : '18%' }}</strong></div>
                <div><span>IGV venta</span><strong>18%</strong></div>
                <small>Valores definidos por la cotización y la configuración general. No se modifican por área.</small>
            </div>
        </div>
        <p class="bulk-material-defaults__help">La moneda y el tratamiento del precio se aplicarán a todas las filas. Los demás valores son automáticos.</p>
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
