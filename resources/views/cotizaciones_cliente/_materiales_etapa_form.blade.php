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
        <label class="form-field bulk-material-stage__name">
            <span>Área de la orden <span class="required-mark">*</span></span>
            <input type="text" name="area_nombre" maxlength="150" value="{{ $areaInicial }}" list="areas_cotizacion_materiales" placeholder="Ej. SISTEMA NEUMÁTICO" @readonly($areaSeleccionada) required>
            <datalist id="areas_cotizacion_materiales">
                @foreach ($cotizacion->todasLasAreas->whereNull('area_padre_id')->where('estado', 'VIGENTE') as $areaDisponible)
                    <option value="{{ $areaDisponible->nombre }}"></option>
                @endforeach
            </datalist>
            <small>@if ($areaSeleccionada) Agregando a {{ $areaSeleccionada->rutaVisible($cotizacion->todasLasAreas) }}. @else Todos los materiales de abajo quedarán planificados dentro de esta área. @endif</small>
            @error('cotizacion_area_id')<small class="field-error">{{ $message }}</small>@enderror
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
                <small>El tipo de cambio, margen e IGV de venta vienen de la cotización. El IGV de compra se elige para este bloque.</small>
            </div>
        </div>
        <p class="bulk-material-defaults__help">Ingresa los costos en la moneda elegida. Al cambiarla, los importes se convierten con el tipo de cambio de la cotización; cada fila muestra ambas monedas.</p>
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

    <div class="bulk-material-totals" aria-label="Suma de los costos ingresados antes del tratamiento del IGV">
        <span>Costos ingresados en soles <strong data-bulk-total-pen>—</strong></span>
        <span>Costos ingresados en dólares <strong data-bulk-total-usd>—</strong></span>
    </div>

    <template data-bulk-material-template>
        @include('cotizaciones_cliente._material_etapa_row', [
            'indice' => '__INDEX__',
            'material' => ['producto_id' => null, 'cantidad' => 1, 'costo_unitario' => null],
        ])
    </template>

    <label class="form-field bulk-material-observation">
        <span>Observación común (opcional)</span>
        <textarea name="observacion" rows="2" maxlength="500" placeholder="Dato aplicable a todos los materiales de esta área">{{ old('observacion') }}</textarea>
    </label>

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

</form>
