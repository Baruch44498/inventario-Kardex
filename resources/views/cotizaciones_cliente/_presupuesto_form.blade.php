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
    $areaSeleccionada = old('cotizacion_area_id', $partida->cotizacion_area_id);
    $nombreAreaInicial = old('area_nombre', $areaSeleccionada ? '' : $areaActual);
    $igvModoActual = old('igv_modo', $partida->igv_modo ?: 'NO_APLICA');
    $igvCompraActual = $igvModoActual === 'NO_APLICA'
        ? 0
        : \App\Models\CotizacionPresupuesto::IGV_PORCENTAJE;
    $margenConfigurado = (float) $cotizacion->margen_cliente_porcentaje;
    $tipoCambioConfigurado = (float) ($cotizacion->tipo_cambio ?: $partida->tipo_cambio);
    $componentePrincipalFormulario = $cotizacion->componentes->first(
        fn ($componente) => (int) $componente->tipo_orden_id === (int) $cotizacion->tipo_orden_id
    ) ?: $cotizacion->componentes->first();
@endphp

<form method="POST" action="{{ $accion }}" class="budget-cost-form" data-budget-form>
    @csrf
    @if ($editando)
        @method('PUT')
    @endif
    @if ($cotizacion->proforma_id === null && $cotizacion->componentes->isNotEmpty())
        <input type="hidden" name="componente_id" value="{{ $editando ? $partida->componente_id : $componentePrincipalFormulario?->id }}">
    @endif

    <div class="budget-cost-layout">
        <div class="budget-cost-layout__main">
            <section class="budget-cost-section" aria-labelledby="{{ $prefijo }}_identificacion">
                <h2 id="{{ $prefijo }}_identificacion">Qué se costea</h2>
                <div class="budget-cost-identification">
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
                        <small>Incluye EPP y consumibles controlados en Kardex.</small>
                        @error('producto_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <div class="form-field budget-cost-identification__full" data-budget-area-field>
                        <label for="{{ $prefijo }}_area_existente">Área de la orden <span data-budget-area-required class="required-mark">*</span></label>
                        <select id="{{ $prefijo }}_area_existente" name="cotizacion_area_id" data-budget-area-select>
                            <option value="">Sin área (solo servicios)</option>
                            @foreach ($cotizacion->todasLasAreas->where('estado', 'VIGENTE') as $areaDisponible)
                                <option value="{{ $areaDisponible->id }}" @selected((int) $areaSeleccionada === $areaDisponible->id)>{{ $areaDisponible->rutaVisible($cotizacion->todasLasAreas) }}</option>
                            @endforeach
                            <option value="" data-budget-create-area @selected(! $areaSeleccionada && filled($nombreAreaInicial))>+ Crear nueva área…</option>
                        </select>
                        <div class="form-field budget-cost-new-area" data-budget-new-area>
                            <label for="{{ $prefijo }}_area_nueva">Nombre de la nueva área</label>
                            <input id="{{ $prefijo }}_area_nueva" type="text" name="area_nombre" maxlength="150" value="{{ $nombreAreaInicial }}" placeholder="Ej. SISTEMA NEUMÁTICO">
                            @error('area_nombre')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                        <small data-budget-area-help>Selecciona un área existente o escribe una nueva.</small>
                        @error('cotizacion_area_id')<small class="field-error">{{ $message }}</small>@enderror
                    </div>

                    <label class="form-field budget-cost-identification__full" data-budget-service-field hidden>
                        <span>¿Quién ejecutará el servicio? <span class="required-mark">*</span></span>
                        <select name="ejecucion_servicio" data-budget-service-execution>
                            @foreach ($ejecucionesServicio as $codigo => $nombre)
                                @continue($codigo === 'POR_DEFINIR')
                                <option value="{{ $codigo }}" @selected(old('ejecucion_servicio', $partida->ejecucion_servicio ?: 'EXTERNO') === $codigo)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                        <small>Solo “Servicio ejecutado por HIDROIL” podrá generar una OS hija.</small>
                        @error('ejecucion_servicio')<small class="field-error">{{ $message }}</small>@enderror
                    </label>

                    <label class="form-field budget-cost-identification__full">
                        <span>Descripción <span class="required-mark">*</span></span>
                        <input type="text" name="descripcion" maxlength="300" value="{{ old('descripcion', $partida->descripcion) }}" data-budget-description required>
                        @error('descripcion')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                </div>
            </section>

            <section class="budget-cost-section" aria-labelledby="{{ $prefijo }}_calculo">
                <h2 id="{{ $prefijo }}_calculo">Cálculo</h2>
                <div class="budget-cost-calculation">
                    <label class="form-field">
                        <span>Cantidad <span class="required-mark">*</span></span>
                        <input type="number" name="cantidad" min="0.001" step="0.001" value="{{ old('cantidad', $partida->cantidad ?: 1) }}" data-budget-quantity required>
                        @error('cantidad')<small class="field-error">{{ $message }}</small>@enderror
                    </label>

                    <label class="form-field" data-budget-unit-field data-product-unit-code="{{ $unidadProductoCodigo }}" data-product-unit-label="{{ $unidadProductoNombre }}" data-product-allows-fraction="{{ $producto?->permite_fraccionamiento ? 'true' : 'false' }}">
                        <span>Unidad <span class="required-mark">*</span></span>
                        <select name="unidad" data-budget-unit required>
                            @foreach ($unidadesPresupuesto as $codigo => $nombre)
                                <option value="{{ $codigo }}" data-budget-unit-option data-compatible-types="{{ $tiposPorUnidad->get($codigo, '') }}" @selected(old('unidad', $partida->unidad ?: 'GLOBAL') === $codigo)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                        <small class="budget-cost-calculation__help" data-budget-unit-help data-budget-quantity-hint>Unidad y cantidad se ajustan al tipo de costo.</small>
                        @error('unidad')<small class="field-error">{{ $message }}</small>@enderror
                    </label>

                    <label class="form-field">
                        <span>Moneda <span class="required-mark">*</span></span>
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
                        @error('costo_unitario')<small class="field-error">{{ $message }}</small>@enderror
                    </label>

                    <label class="form-field">
                        <span>IGV de compra <span class="required-mark">*</span></span>
                        <select name="igv_modo" data-budget-tax-mode required>
                            @foreach ($modosIgvPresupuesto as $codigo => $nombre)
                                <option value="{{ $codigo }}" @selected($igvModoActual === $codigo)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                        <small class="sr-only" data-budget-tax-rate-label>{{ $igvModoActual === 'NO_APLICA' ? 'No aplica' : '18%' }}</small>
                        @error('igv_modo')<small class="field-error">{{ $message }}</small>@enderror
                    </label>

                    <input type="hidden" name="margen_porcentaje" value="{{ $margenConfigurado }}" data-budget-margin>
                    <input type="hidden" name="igv_porcentaje" value="{{ $igvCompraActual }}" data-budget-tax-rate>
                    <input type="hidden" name="igv_venta_porcentaje" value="{{ \App\Models\CotizacionPresupuesto::IGV_PORCENTAJE }}" data-budget-sale-tax-rate>
                    <label class="form-field budget-cost-calculation__social" data-budget-social-field>
                        <span>Carga social (%)</span>
                        <input type="number" name="carga_social_porcentaje" min="0" max="999.9999" step="0.0001" value="{{ old('carga_social_porcentaje', $partida->carga_social_porcentaje ?: 0) }}" data-budget-social>
                        <small>Solo mano de obra: PLAME, AFP u otras cargas.</small>
                        @error('carga_social_porcentaje')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                </div>
            </section>

            <details class="budget-cost-observation" @if(old('observacion', $partida->observacion) || $errors->has('observacion')) open @endif>
                <summary>+ Agregar observación</summary>
                <label class="form-field">
                    <span>Observación</span>
                    <textarea name="observacion" rows="2" maxlength="500">{{ old('observacion', $partida->observacion) }}</textarea>
                    @error('observacion')<small class="field-error">{{ $message }}</small>@enderror
                </label>
            </details>
        </div>

        <aside class="budget-cost-result" data-budget-preview aria-label="Resultado estimado">
            <h2>Resultado</h2>
            <p data-budget-preview-text>Completa cantidad, costo y tipo de cambio.</p>
            <div class="budget-cost-result__cards" data-budget-result-cards hidden>
                <div><span>Costo total PEN</span><strong data-budget-result-cost-pen>—</strong>
                    @if($editando)<small data-budget-before-value="{{ $partida->costo_total_soles }}" data-budget-before-currency="PEN">Antes: <x-ui.money :value="$partida->costo_total_soles" currency="PEN" /></small>@endif
                </div>
                <div><span>Venta total PEN (incluye IGV 18%)</span><strong data-budget-result-sale-pen>—</strong>
                    @if($editando)<small data-budget-before-value="{{ $partida->precio_venta_total_soles }}" data-budget-before-currency="PEN">Antes: <x-ui.money :value="$partida->precio_venta_total_soles" currency="PEN" /></small>@endif
                </div>
                <div><span>Utilidad neta PEN</span><strong data-budget-result-utility-pen>—</strong>
                    @if($editando)<small data-budget-before-value="{{ $partida->utilidad_estimada_soles }}" data-budget-before-currency="PEN">Antes: <x-ui.money :value="$partida->utilidad_estimada_soles" currency="PEN" /></small>@endif
                </div>
                <div><span>Costo USD</span><strong data-budget-result-cost-usd>—</strong>
                    @if($editando)<small data-budget-before-value="{{ $partida->costo_total_dolares }}" data-budget-before-currency="USD">Antes: <x-ui.money :value="$partida->costo_total_dolares" currency="USD" /></small>@endif
                </div>
            </div>
            <div class="budget-locked-rules" aria-label="Reglas financieras automáticas">
                <span>TC <strong>{{ number_format($tipoCambioConfigurado, 2) }}</strong></span>
                <span>Margen <strong>{{ number_format($margenConfigurado, 2) }}%</strong></span>
                <span>IGV venta <strong>18%</strong></span>
            </div>
        </aside>
    </div>

    <div class="form-actions budget-cost-actions">
        @if ($editando)
            <a href="{{ route('cotizaciones-cliente.presupuesto.show', ['cotizacionCliente' => $cotizacion, 'paso' => 'revision', 'grupo_partidas' => request('grupo_partidas'), 'partidas_page' => request('partidas_page')]) }}#detalle-area-presupuesto" class="button button--ghost">Cancelar</a>
        @else
            <a href="{{ request()->fullUrl().'#nuevo-costo' }}" class="button button--ghost">Cancelar</a>
        @endif
        <button type="submit" class="button button--primary">
            <x-ui.icon name="check-circle" :size="17" />
            {{ $editando ? 'Guardar cambios' : 'Agregar partida' }}
        </button>
    </div>
</form>
