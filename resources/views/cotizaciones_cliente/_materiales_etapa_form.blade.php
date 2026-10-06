@php
    $materialesIniciales = old('materiales', [
        ['producto_id' => null, 'cantidad' => 1, 'costo_unitario' => null],
        ['producto_id' => null, 'cantidad' => 1, 'costo_unitario' => null],
    ]);
    $areaSeleccionadaId = old('cotizacion_area_id', request('area_id'));
    $areaSeleccionada = $cotizacion->todasLasAreas->where('estado', 'VIGENTE')
        ->firstWhere('id', (int) $areaSeleccionadaId);
    $areaInicial = old('area_nombre', $areaSeleccionada?->nombre ?: old('grupo_costo', request('area')));
    $monedaInicial = old('moneda', $cotizacion->moneda ?: 'PEN');
    $tipoCambioInicial = old(
        'tipo_cambio',
        (float) ($cotizacion->tipo_cambio ?: $componenteInicial?->tipo_cambio_comparacion) ?: null
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
    @if ($areaSeleccionadaId)
        <input type="hidden" name="cotizacion_area_id" value="{{ $areaSeleccionadaId }}">
    @endif

    <div class="bulk-material-stage">
        <div class="bulk-material-stage__controls">
            <label class="form-field bulk-material-stage__name">
                <span>Área <span class="required-mark">*</span></span>
                <input type="text" name="area_nombre" maxlength="150" value="{{ $areaInicial }}" list="areas_cotizacion_materiales" placeholder="Ej. SISTEMA NEUMÁTICO" @readonly($areaSeleccionada) required>
                <datalist id="areas_cotizacion_materiales">
                    @foreach ($cotizacion->todasLasAreas->whereNull('area_padre_id')->where('estado', 'VIGENTE') as $areaDisponible)
                        <option value="{{ $areaDisponible->nombre }}"></option>
                    @endforeach
                </datalist>
                @if ($areaSeleccionada)<small>{{ $areaSeleccionada->rutaVisible($cotizacion->todasLasAreas) }}</small>@endif
                @error('cotizacion_area_id')<small class="field-error">{{ $message }}</small>@enderror
                @error('area_nombre')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <label class="form-field">
                <span>Moneda</span>
                <select name="moneda" data-bulk-currency required>
                    @foreach (\App\Models\CotizacionPresupuesto::MONEDAS as $codigo => $nombre)
                        <option value="{{ $codigo }}" @selected($monedaInicial === $codigo)>{{ $codigo }} · {{ $nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-field">
                <span>IGV de compra</span>
                <select name="igv_modo" data-bulk-tax-mode required>
                    @foreach (\App\Models\CotizacionPresupuesto::MODOS_IGV as $codigo => $nombre)
                        <option value="{{ $codigo }}" @selected($igvModoInicial === $codigo)>{{ $nombre }}</option>
                    @endforeach
                </select>
                <small class="sr-only" data-bulk-tax-rate-label>{{ $igvModoInicial === 'NO_APLICA' ? 'No aplica' : '18%' }}</small>
            </label>
        </div>
        <input type="hidden" name="tipo_cambio" value="{{ sprintf('%.6F', (float) $tipoCambioInicial) }}">
        <input type="hidden" name="margen_porcentaje" value="{{ $margenConfigurado }}">
        <input type="hidden" name="igv_porcentaje" value="{{ $igvCompraInicial }}" data-bulk-tax-rate>
        <input type="hidden" name="igv_venta_porcentaje" value="{{ \App\Models\CotizacionPresupuesto::IGV_PORCENTAJE }}">
        <div class="bulk-material-rules" aria-label="Reglas financieras aplicadas" title="TC, margen e IGV de venta definidos en la cotización; el IGV de compra se elige arriba.">
            <span>TC <strong>{{ number_format((float) $tipoCambioInicial, 2) }}</strong></span>
            <span>Margen comercial <strong>{{ number_format($margenConfigurado, 2) }}%</strong></span>
            <span>IGV venta <strong>18%</strong></span>
        </div>
        <p class="bulk-material-defaults__help">Los costos cambian de moneda con el TC de la cotización.</p>
    </div>

    @error('materiales')<div class="notice notice--danger notice--block"><span>{{ $message }}</span></div>@enderror

    <div class="bulk-material-list__head" aria-hidden="true">
        <span>N.º</span><span>Producto</span><span>Cant.</span><span>Unid.</span><span>Costo unit.</span><span>Subtotal</span><span></span>
    </div>
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

    <details class="bulk-material-observation" @if(old('observacion') || $errors->has('observacion')) open @endif>
        <summary>+ Agregar observación</summary>
        <label class="form-field">
            <span>Observación común (opcional)</span>
            <textarea name="observacion" rows="2" maxlength="500" placeholder="Dato aplicable a todos los materiales de esta área">{{ old('observacion') }}</textarea>
            @error('observacion')<small class="field-error">{{ $message }}</small>@enderror
        </label>
    </details>

    <div class="bulk-material-actions">
        <div class="bulk-material-actions__figures" aria-label="Total del bloque antes del tratamiento del IGV">
            <span>Total del bloque</span>
            <strong data-bulk-total-pen>—</strong>
            <small data-bulk-total-usd>—</small>
        </div>
        <span data-bulk-material-count>{{ count($materialesIniciales) }} materiales</span>
        <button type="button" class="button button--ghost" data-add-material-row>
            <x-ui.icon name="plus" :size="17" />
            Agregar fila
        </button>
        <button type="submit" class="button button--primary">
            <x-ui.icon name="check-circle" :size="17" />
            Guardar todos los materiales
        </button>
    </div>

</form>
