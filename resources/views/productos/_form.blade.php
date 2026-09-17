@csrf

@if (isset($producto))
    @method('PUT')
@endif

@php
    $presentacionesIniciales = collect(old(
        'presentaciones',
        isset($presentacionesProducto)
            ? $presentacionesProducto->map(fn ($item) => [
                'id' => $item->id,
                'nombre' => $item->nombre,
                'factor_conversion' => $item->factor_conversion,
                'es_predeterminada' => $item->es_predeterminada,
                'estado' => $item->estado,
            ])->all()
            : []
    ))->values();
@endphp

<div class="form-grid">
    <div class="form-field">
        <label for="codigo">Código <span class="required-mark">*</span></label>
        <div class="input-with-icon">
            <span class="input-with-icon__symbol">
                <x-ui.icon name="hash" :size="18" />
            </span>
            <input
                id="codigo"
                name="codigo"
                type="text"
                value="{{ old('codigo', $producto->codigo ?? ($codigoSugerido ?? '')) }}"
                maxlength="50"
                placeholder="{{ isset($producto) ? 'Código único del producto' : 'Se sugiere el siguiente código' }}"
                required
                autocomplete="off"
                aria-invalid="{{ $errors->has('codigo') ? 'true' : 'false' }}"
                @if ($errors->has('codigo')) aria-describedby="codigo-error" @endif
                @class(['is-invalid' => $errors->has('codigo')])
            >
        </div>
        <small>{{ isset($producto) ? 'Usa un código único, breve y reconocible.' : 'Código sugerido automáticamente. Puedes cambiarlo antes de registrar.' }}</small>
        @error('codigo')
            <small id="codigo-error" class="field-error" role="alert">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="id_unidad_medida">
            Unidad de medida <span class="required-mark">*</span>
        </label>
        <div class="input-with-icon input-with-icon--select">
            <span class="input-with-icon__symbol">
                <x-ui.icon name="ruler" :size="18" />
            </span>
            <select
                id="id_unidad_medida"
                name="id_unidad_medida"
                required
                aria-invalid="{{ $errors->has('id_unidad_medida') ? 'true' : 'false' }}"
                @if ($errors->has('id_unidad_medida')) aria-describedby="unidad-error" @endif
                @class(['is-invalid' => $errors->has('id_unidad_medida')])
            >
                <option value="">Selecciona una unidad</option>
                @foreach ($unidades as $unidad)
                    <option
                        value="{{ $unidad->id_unidad_medida }}"
                        data-unit-code="{{ $unidad->codigo }}"
                        @selected(
                            (string) old(
                                'id_unidad_medida',
                                $producto->id_unidad_medida ?? ''
                            ) === (string) $unidad->id_unidad_medida
                        )
                    >
                        {{ $unidad->codigo }} · {{ $unidad->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        @error('id_unidad_medida')
            <small id="unidad-error" class="field-error" role="alert">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field form-field--switch">
        <span class="form-label">Cantidad fraccionaria</span>
        <label class="switch-field">
            <input type="hidden" name="permite_fraccionamiento" value="0">
            <input
                type="checkbox"
                name="permite_fraccionamiento"
                value="1"
                data-product-fractional
                @checked((bool) old(
                    'permite_fraccionamiento',
                    $producto->permite_fraccionamiento ?? false
                ))
            >
            <span class="switch-field__control"></span>
            <span>Permite cortes o consumos con decimales</span>
        </label>
        <small>Ejemplo: 1.50 m o 3.20 m. Déjalo desactivado para unidades indivisibles.</small>
        @error('permite_fraccionamiento')
            <small class="field-error" role="alert">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field">
        <label for="marca_principal_busqueda">Marca principal</label>
        <x-ui.remote-combobox
            name="id_marca_principal"
            search-id="marca_principal_busqueda"
            value-id="id_marca_principal"
            :search-url="route('catalogos.marcas.buscar')"
            :selected-id="$marcaSeleccionada?->id_marca"
            :selected-label="$marcaSeleccionada?->nombre ?? ''"
            placeholder="Nombre de marca"
            empty-text="No se encontró una marca activa."
        />
        @error('id_marca_principal')
            <small id="marca-error" class="field-error" role="alert">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field form-field--switch">
        <span class="form-label">Estado</span>
        <label class="switch-field">
            <input type="hidden" name="activo" value="0">
            <input
                type="checkbox"
                name="activo"
                value="1"
                @checked((bool) old('activo', $producto->activo ?? true))
            >
            <span class="switch-field__control"></span>
            <span>Producto activo</span>
        </label>
    </div>

    <div class="form-field form-grid__full">
        <label for="descripcion">
            Descripción <span class="required-mark">*</span>
        </label>
        <div class="input-with-icon input-with-icon--textarea">
            <span class="input-with-icon__symbol">
                <x-ui.icon name="align-left" :size="18" />
            </span>
            <textarea
                id="descripcion"
                name="descripcion"
                rows="5"
                maxlength="500"
                placeholder="Ej. Filtro hidráulico de retorno para mantenimiento preventivo"
                required
                aria-invalid="{{ $errors->has('descripcion') ? 'true' : 'false' }}"
                @if ($errors->has('descripcion')) aria-describedby="descripcion-error" @endif
                @class(['is-invalid' => $errors->has('descripcion')])
            >{{ old('descripcion', $producto->descripcion ?? '') }}</textarea>
        </div>
        <div class="field-meta">
            <small>Describe el producto de forma clara y sin abreviaturas ambiguas.</small>
            <small data-character-count="descripcion">0 / 500</small>
        </div>
        <div class="notice notice--warning notice--block product-similarity-warning"
            data-product-similarity-warning role="status" aria-live="polite" hidden style="display: none;">
            <x-ui.icon name="warning" :size="18" />
            <div>
                <strong>Posible producto duplicado</strong>
                <p>Encontramos productos con nombre similar. Verifica que no sea el mismo antes de guardar.</p>
                <ul data-product-similarity-list></ul>
                <small>Esta advertencia no bloquea el registro.</small>
            </div>
        </div>
        @error('descripcion')
            <small id="descripcion-error" class="field-error" role="alert">{{ $message }}</small>
        @enderror
    </div>

    <section class="form-field form-grid__full product-presentations" data-product-presentations>
        <div class="product-presentations__heading">
            <div>
                <div class="product-presentations__title-row">
                    <span class="form-label">Presentaciones de compra (opcional)</span>
                    <x-ui.collapsible-notice
                        class="product-presentations__help"
                        title="¿Cuándo debo agregar una presentación?"
                        label="Ver cómo funcionan las presentaciones de compra"
                    >
                        <p>Úsala cuando el proveedor entrega el producto en bolsa, caja, rollo, paquete, bidón u otro empaque.</p>
                        <p><strong>Ejemplo:</strong> producto Tornillo + unidad base UND + tipo de empaque Bolsa + contenido 30 = recibir 1 bolsa aumenta 30 UND en el Kardex.</p>
                        <p>Si compras directamente por UND, MTS, LT, GLN, BAL o KIT, no necesitas agregarla.</p>
                    </x-ui.collapsible-notice>
                </div>
                <small>Solo agrégala cuando el producto llegue en un empaque con varias unidades base.</small>
            </div>
            <button type="button" class="button button--ghost button--small" data-add-presentation>
                <x-ui.icon name="plus" :size="16" /> Agregar presentación
            </button>
        </div>

        <div class="product-presentations__rows" data-presentation-rows>
            @foreach ($presentacionesIniciales as $indice => $presentacion)
                <div class="product-presentation-row" data-presentation-row>
                    <input type="hidden" name="presentaciones[{{ $indice }}][id]" value="{{ $presentacion['id'] ?? '' }}" data-presentation-field="id">
                    <label class="form-field">
                        <span>Tipo de empaque</span>
                        <input type="text" name="presentaciones[{{ $indice }}][nombre]" value="{{ $presentacion['nombre'] ?? '' }}" maxlength="80" placeholder="Ej. Bolsa, Caja o Rollo" required data-presentation-field="nombre">
                        <small>Escribe solo el empaque; no repitas el nombre del producto.</small>
                    </label>
                    <label class="form-field">
                        <span>Contenido por empaque</span>
                        <input type="number" name="presentaciones[{{ $indice }}][factor_conversion]" value="{{ $presentacion['factor_conversion'] ?? '' }}" min="0.001" step="0.001" required data-presentation-field="factor_conversion">
                        <small><span data-presentation-base-unit>unidades base</span> dentro de cada empaque</small>
                        <small class="product-presentation-row__preview" data-presentation-preview></small>
                    </label>
                    <label class="switch-field product-presentation-row__check">
                        <input type="hidden" name="presentaciones[{{ $indice }}][es_predeterminada]" value="0" data-presentation-hidden-default>
                        <input type="checkbox" name="presentaciones[{{ $indice }}][es_predeterminada]" value="1" @checked((bool) ($presentacion['es_predeterminada'] ?? false)) data-presentation-default>
                        <span class="switch-field__control"></span>
                        <span>Usar por defecto</span>
                    </label>
                    <label class="switch-field product-presentation-row__check">
                        <input type="hidden" name="presentaciones[{{ $indice }}][estado]" value="0" data-presentation-hidden-state>
                        <input type="checkbox" name="presentaciones[{{ $indice }}][estado]" value="1" @checked((bool) ($presentacion['estado'] ?? true)) data-presentation-state>
                        <span class="switch-field__control"></span>
                        <span>Disponible</span>
                    </label>
                    <button type="button" class="icon-button icon-button--danger" title="Quitar presentación" aria-label="Quitar presentación" data-remove-presentation>
                        <x-ui.icon name="close" :size="16" />
                    </button>
                </div>
            @endforeach
        </div>

        <template data-presentation-template>
            <div class="product-presentation-row" data-presentation-row>
                <input type="hidden" data-presentation-field="id">
                <label class="form-field"><span>Tipo de empaque</span><input type="text" maxlength="80" placeholder="Ej. Bolsa, Caja o Rollo" required data-presentation-field="nombre"><small>Escribe solo el empaque; no repitas el nombre del producto.</small></label>
                <label class="form-field"><span>Contenido por empaque</span><input type="number" min="0.001" step="0.001" required data-presentation-field="factor_conversion"><small><span data-presentation-base-unit>unidades base</span> dentro de cada empaque</small><small class="product-presentation-row__preview" data-presentation-preview></small></label>
                <label class="switch-field product-presentation-row__check"><input type="hidden" value="0" data-presentation-hidden-default><input type="checkbox" value="1" data-presentation-default><span class="switch-field__control"></span><span>Usar por defecto</span></label>
                <label class="switch-field product-presentation-row__check"><input type="hidden" value="0" data-presentation-hidden-state><input type="checkbox" value="1" checked data-presentation-state><span class="switch-field__control"></span><span>Disponible</span></label>
                <button type="button" class="icon-button icon-button--danger" title="Quitar presentación" aria-label="Quitar presentación" data-remove-presentation><x-ui.icon name="close" :size="16" /></button>
            </div>
        </template>

        @error('presentaciones')<small class="field-error" role="alert">{{ $message }}</small>@enderror
        @error('presentaciones.*')<small class="field-error" role="alert">{{ $message }}</small>@enderror
    </section>
</div>

@push('scripts')
    <script src="{{ asset('js/product-form.js') }}" defer></script>
@endpush

<div class="form-actions">
    <button
        type="button"
        class="button button--ghost"
        data-cancel-form
        data-cancel-url="{{ route('productos.index') }}"
    >
        Cancelar
    </button>

    <button
        type="submit"
        class="button button--primary"
        data-submit-button
        data-loading-text="{{ isset($producto) ? 'Guardando...' : 'Registrando...' }}"
    >
        <span data-submit-icon>
            <x-ui.icon name="check" :size="18" />
        </span>
        <span class="button-spinner" data-submit-spinner hidden></span>
        <span data-submit-label>
            {{ isset($producto) ? 'Guardar cambios' : 'Registrar producto' }}
        </span>
    </button>
</div>
