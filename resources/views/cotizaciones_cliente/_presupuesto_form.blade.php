@php
    $producto = $partida->producto;
    $prefijo = $prefijo ?? 'presupuesto';
    $editando = $partida->exists;
    $tiposPresupuesto = \App\Models\CotizacionPresupuesto::TIPOS;
    $unidadesPresupuesto = \App\Models\CotizacionPresupuesto::UNIDADES;
    $monedasPresupuesto = \App\Models\CotizacionPresupuesto::MONEDAS;
    $modosIgvPresupuesto = \App\Models\CotizacionPresupuesto::MODOS_IGV;
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
            <label for="{{ $prefijo }}_producto_busqueda">Producto de inventario <small>(opcional)</small></label>
            <x-ui.remote-combobox
                name="producto_id"
                :search-id="$prefijo.'_producto_busqueda'"
                :value-id="$prefijo.'_producto_id'"
                :search-url="route('catalogos.productos.buscar')"
                :selected-id="old('producto_id', $producto?->id)"
                :selected-label="$producto ? $producto->codigo.' — '.$producto->descripcion : ''"
                placeholder="Código o descripción"
                empty-text="Producto no encontrado. También puedes registrar el material manualmente."
            />
            <small>Vincúlalo si existe; el material manual sigue permitido.</small>
            @error('producto_id')<small class="field-error">{{ $message }}</small>@enderror
        </div>

        <label class="form-field form-field--span-2">
            <span>Descripción <span class="required-mark">*</span></span>
            <input type="text" name="descripcion" maxlength="300" value="{{ old('descripcion', $partida->descripcion) }}" data-budget-description required>
            @error('descripcion')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="form-field">
            <span>Cantidad <span class="required-mark">*</span></span>
            <input type="number" name="cantidad" min="0.001" step="0.001" value="{{ old('cantidad', $partida->cantidad ?: 1) }}" data-budget-quantity required>
            @error('cantidad')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="form-field">
            <span>Unidad <span class="required-mark">*</span></span>
            <select name="unidad" required>
                @foreach ($unidadesPresupuesto as $codigo => $nombre)
                    <option value="{{ $codigo }}" @selected(old('unidad', $partida->unidad ?: 'GLOBAL') === $codigo)>{{ $nombre }}</option>
                @endforeach
            </select>
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

        <label class="form-field">
            <span>Tipo de cambio <span class="required-mark">*</span></span>
            <input type="number" name="tipo_cambio" min="0.1" max="100" step="0.000001" value="{{ old('tipo_cambio', $partida->tipo_cambio) }}" placeholder="Ej. 3.38" data-budget-exchange required>
            <small>PEN por 1 USD. Se usa también para mostrar partidas en soles en su equivalente USD.</small>
            @error('tipo_cambio')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="form-field">
            <span>Costo unitario <span class="required-mark">*</span></span>
            <input type="number" name="costo_unitario" min="0.0001" step="0.0001" value="{{ old('costo_unitario', $partida->costo_unitario) }}" data-budget-unit-cost required>
            <small>Importe en la moneda original seleccionada.</small>
            @error('costo_unitario')<small class="field-error">{{ $message }}</small>@enderror
        </label>

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
                    <option value="{{ $codigo }}" @selected(old('igv_modo', $partida->igv_modo ?: 'NO_APLICA') === $codigo)>{{ $nombre }}</option>
                @endforeach
            </select>
            @error('igv_modo')<small class="field-error">{{ $message }}</small>@enderror
        </label>

        <label class="form-field">
            <span>IGV (%)</span>
            <input type="number" name="igv_porcentaje" min="0" max="100" step="0.0001" value="{{ old('igv_porcentaje', $partida->igv_porcentaje ?? 18) }}" data-budget-tax-rate required>
            @error('igv_porcentaje')<small class="field-error">{{ $message }}</small>@enderror
        </label>

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
            <a href="{{ route('cotizaciones-cliente.presupuesto.show', $cotizacion) }}" class="button button--ghost">Cancelar</a>
        @endif
        <button type="submit" class="button button--primary">
            <x-ui.icon name="check-circle" :size="17" />
            {{ $editando ? 'Guardar cambios' : 'Agregar partida' }}
        </button>
    </div>
</form>
