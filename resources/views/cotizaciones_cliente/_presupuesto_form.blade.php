@php
    $producto = $partida->producto;
    $prefijo = $prefijo ?? 'presupuesto';
    $editando = $partida->exists;
    $tiposPresupuesto = \App\Models\CotizacionPresupuesto::TIPOS;
    $unidadesPresupuesto = \App\Models\CotizacionPresupuesto::UNIDADES;
    $unidadesPorTipo = \App\Models\CotizacionPresupuesto::UNIDADES_POR_TIPO;
    $tiposPorUnidad = collect($unidadesPorTipo)
        ->flatMap(fn (array $unidades, string $tipo) => collect($unidades)
            ->map(fn (string $unidad) => [$unidad, $tipo]))
        ->groupBy(fn (array $asignacion) => $asignacion[0])
        ->map(fn ($asignaciones) => $asignaciones->pluck(1)->implode(','));
    $unidadProductoCodigo = $producto
        ? \App\Models\CotizacionPresupuesto::unidadDeProducto($producto)
        : null;
    $unidadProductoNombre = $producto?->unidadMedida?->nombre;
    $monedasPresupuesto = \App\Models\CotizacionPresupuesto::MONEDAS;
    $modosIgvPresupuesto = \App\Models\CotizacionPresupuesto::MODOS_IGV;
    $ejecucionesServicio = \App\Models\CotizacionPresupuesto::EJECUCIONES_SERVICIO;
    $areaActual = $partida->area?->nombre ?: $partida->grupo_costo;
    $igvModoActual = old('igv_modo', $partida->igv_modo ?: 'NO_APLICA');
    $igvCompraActual = $igvModoActual === 'NO_APLICA'
        ? 0
        : \App\Models\CotizacionPresupuesto::IGV_PORCENTAJE;
    $margenConfigurado = (float) $cotizacion->margen_cliente_porcentaje;
    $tipoCambioConfigurado = (float) ($cotizacion->tipo_cambio ?: $partida->tipo_cambio);
@endphp

<form method="POST" action="{{ $accion }}" data-budget-form>
    @csrf
    @if ($editando)
        @method('PUT')
    @endif

    <div class="operation-form-grid">
        @if ($cotizacion->proforma_id === null)
            <label class="form-field">
                <span>Componente <span class="required-mark">*</span></span>
                <select name="componente_id" required>
                    <option value="">Selecciona el trabajo</option>
                    @foreach ($cotizacion->componentes as $componente)
                        <option value="{{ $componente->id }}" @selected((int) old('componente_id', $partida->componente_id) === $componente->id)>
                            {{ $componente->tipoOrden?->codigo }} {{ $componente->orden_secuencia }} · {{ $componente->descripcion_componente }}
                        </option>
                    @endforeach
                </select>
                @error('componente_id')<small class="field-error">{{ $message }}</small>@enderror
            </label>
        @endif

        <label class="form-field">
            <span>Tipo de costo <span class="required-mark">*</span></span>
            <select name="tipo_costo" data-budget-type required>
                <option value="">Selecciona un tipo</option>
                @foreach ($tiposPresupuesto as $codigo => $nombre)
                    <option value="{{ $codigo }}" @selected(old('tipo_costo', $partida->tipo_costo) === $codigo)>{{ $nombre }}</option>
                @endforeach
            </select>
            @error('tipo_costo')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <div class="form-field" data-budget-product-field>
            <label for="{{ $prefijo }}_producto_busqueda">Producto que saldrá de almacén <span class="required-mark">*</span></label>
            <x-ui.remote-combobox
                name="producto_id"
                :search-id="$prefijo.'_producto_busqueda'"
                :value-id="$prefijo.'_producto_id'"
                :search-url="route('catalogos.productos.buscar')"
                :selected-id="old('producto_id', $producto?->id)"
                :selected-label="$producto ? $producto->codigo.' — '.$producto->descripcion : ''"
                placeholder="Código o descripción"
                empty-text="Producto no encontrado. Debe registrarse primero en el catálogo de almacén."
            />
            <small>También los EPP y consumibles se eligen aquí porque su salida será controlada por el Kardex.</small>
            @error('producto_id')<small class="field-error">{{ $message }}</small>@enderror
        </div>

        <label class="form-field form-field--span-2" data-budget-area-field>
            <span>Área de la orden <span data-budget-area-required class="required-mark">*</span></span>
            <input type="text" name="area_nombre" maxlength="150" value="{{ old('area_nombre', old('grupo_costo', $areaActual)) }}" list="{{ $prefijo }}_areas_cotizacion" placeholder="Ej. SISTEMA NEUMÁTICO">
            <datalist id="{{ $prefijo }}_areas_cotizacion">
                @foreach ($cotizacion->todasLasAreas as $areaDisponible)
                    <option value="{{ $areaDisponible->nombre }}"></option>
                @endforeach
            </datalist>
            <small data-budget-area-help>En materiales es obligatorio. En servicios permite relacionar el costo con el área que lo utiliza.</small>
            @error('area_nombre')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="form-field form-field--span-2" data-budget-service-field hidden>
            <span>¿Quién ejecutará el servicio? <span class="required-mark">*</span></span>
            <select name="ejecucion_servicio" data-budget-service-execution>
                @foreach ($ejecucionesServicio as $codigo => $nombre)
                    @continue($codigo === 'POR_DEFINIR')
                    <option value="{{ $codigo }}" @selected(old('ejecucion_servicio', $partida->ejecucion_servicio ?: 'EXTERNO') === $codigo)>{{ $nombre }}</option>
                @endforeach
            </select>
            <small>Solo “Servicio ejecutado por HIDROIL” podrá generar una OS hija de la orden principal.</small>
            @error('ejecucion_servicio')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="form-field form-field--span-2">
            <span>Descripción <span class="required-mark">*</span></span>
            <input type="text" name="descripcion" maxlength="300" value="{{ old('descripcion', $partida->descripcion) }}" data-budget-description required>
            @error('descripcion')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="form-field">
            <span>Cantidad <span class="required-mark">*</span></span>
            <input type="number" name="cantidad" min="0.001" step="0.001" value="{{ old('cantidad', $partida->cantidad ?: 1) }}" data-budget-quantity required>
            <small data-budget-quantity-hint>Admite hasta tres decimales.</small>
            @error('cantidad')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label
            class="form-field"
            data-budget-unit-field
            data-product-unit-code="{{ $unidadProductoCodigo }}"
            data-product-unit-label="{{ $unidadProductoNombre }}"
            data-product-allows-fraction="{{ $producto?->permite_fraccionamiento ? 'true' : 'false' }}"
        >
            <span>Unidad <span class="required-mark">*</span></span>
            <select name="unidad" data-budget-unit required>
                @foreach ($unidadesPresupuesto as $codigo => $nombre)
                    <option
                        value="{{ $codigo }}"
                        data-budget-unit-option
                        data-compatible-types="{{ $tiposPorUnidad->get($codigo, '') }}"
                        @selected(old('unidad', $partida->unidad ?: 'GLOBAL') === $codigo)
                    >{{ $nombre }}</option>
                @endforeach
            </select>
            <small data-budget-unit-help>Las opciones cambian según el tipo de costo.</small>
            @error('unidad')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="form-field">
            <span>Moneda original <span class="required-mark">*</span></span>
            <select name="moneda" data-budget-currency required>
                @foreach ($monedasPresupuesto as $codigo => $nombre)
                    <option value="{{ $codigo }}" @selected(old('moneda', $partida->moneda ?: 'PEN') === $codigo)>{{ $codigo }} · {{ $nombre }}</option>
                @endforeach
            </select>
            @error('moneda')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <input type="hidden" name="tipo_cambio" value="{{ sprintf('%.6F', $tipoCambioConfigurado) }}" data-budget-exchange>

        <label class="form-field">
            <span>Costo unitario <span class="required-mark">*</span></span>
            <input type="number" name="costo_unitario" min="0.0001" step="0.0001" value="{{ old('costo_unitario', $partida->costo_unitario) }}" data-budget-unit-cost required>
            <small>Importe en la moneda original seleccionada.</small>
            @error('costo_unitario')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <input type="hidden" name="margen_porcentaje" value="{{ $margenConfigurado }}" data-budget-margin>

        <label class="form-field" data-budget-social-field>
            <span>Carga social (%)</span>
            <input type="number" name="carga_social_porcentaje" min="0" max="999.9999" step="0.0001" value="{{ old('carga_social_porcentaje', $partida->carga_social_porcentaje ?: 0) }}" data-budget-social>
            <small>Solo se aplica a mano de obra: PLAME, AFP u otras cargas.</small>
            @error('carga_social_porcentaje')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="form-field">
            <span>Tratamiento de IGV <span class="required-mark">*</span></span>
            <select name="igv_modo" data-budget-tax-mode required>
                @foreach ($modosIgvPresupuesto as $codigo => $nombre)
                    <option value="{{ $codigo }}" @selected($igvModoActual === $codigo)>{{ $nombre }}</option>
                @endforeach
            </select>
            @error('igv_modo')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <input type="hidden" name="igv_porcentaje" value="{{ $igvCompraActual }}" data-budget-tax-rate>
        <input type="hidden" name="igv_venta_porcentaje" value="{{ \App\Models\CotizacionPresupuesto::IGV_PORCENTAJE }}" data-budget-sale-tax-rate>

        <div class="budget-locked-rules form-field--span-2" aria-label="Reglas financieras automáticas">
            <div><span>Tipo de cambio</span><strong>{{ number_format($tipoCambioConfigurado, 2) }}</strong></div>
            <div><span>Margen comercial</span><strong>{{ number_format($margenConfigurado, 2) }}%</strong></div>
            <div><span>IGV compra</span><strong data-budget-tax-rate-label>{{ $igvModoActual === 'NO_APLICA' ? 'No aplica' : '18%' }}</strong></div>
            <div><span>IGV venta</span><strong>18%</strong></div>
            <small>Estos valores provienen de la cotización y de la configuración general.</small>
        </div>

        <label class="form-field form-field--span-2">
            <span>Observación</span>
            <textarea name="observacion" rows="2" maxlength="500">{{ old('observacion', $partida->observacion) }}</textarea>
            @error('observacion')<small class="field-error">{{ $message }}</small>@enderror
        </label>
    </div>

    <div class="notice notice--info notice--block" data-budget-preview>
        <x-ui.icon name="activity" :size="18" />
        <div>
            <strong>Vista previa calculada</strong>
            <span data-budget-preview-text>Completa cantidad, costo y tipo de cambio.</span>
        </div>
    </div>

    <div class="form-actions">
        @if ($editando)
            <a href="{{ route('cotizaciones-cliente.presupuesto.show', ['cotizacionCliente' => $cotizacion, 'paso' => 'revision']) }}" class="button button--ghost">Cancelar</a>
        @endif
        <button type="submit" class="button button--primary">
            <x-ui.icon name="check-circle" :size="17" />
            {{ $editando ? 'Guardar cambios' : 'Agregar partida' }}
        </button>
    </div>
</form>
