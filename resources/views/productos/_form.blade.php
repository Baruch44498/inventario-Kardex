@csrf

@if (isset($producto))
    @method('PUT')
@endif

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
                value="{{ old('codigo', $producto->codigo ?? '') }}"
                maxlength="50"
                placeholder="Ej. FILT-001"
                required
                autocomplete="off"
                aria-invalid="{{ $errors->has('codigo') ? 'true' : 'false' }}"
                @if ($errors->has('codigo')) aria-describedby="codigo-error" @endif
                @class(['is-invalid' => $errors->has('codigo')])
            >
        </div>
        <small>Usa un código único, breve y reconocible.</small>
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

    <div class="form-field">
        <label for="id_marca_principal">Marca principal</label>
        <div class="input-with-icon input-with-icon--select">
            <span class="input-with-icon__symbol">
                <x-ui.icon name="tag" :size="18" />
            </span>
            <select
                id="id_marca_principal"
                name="id_marca_principal"
                aria-invalid="{{ $errors->has('id_marca_principal') ? 'true' : 'false' }}"
                @if ($errors->has('id_marca_principal')) aria-describedby="marca-error" @endif
                @class(['is-invalid' => $errors->has('id_marca_principal')])
            >
                <option value="">Sin marca</option>
                @foreach ($marcas as $marca)
                    <option
                        value="{{ $marca->id_marca }}"
                        @selected(
                            (string) old(
                                'id_marca_principal',
                                $producto->id_marca_principal ?? ''
                            ) === (string) $marca->id_marca
                        )
                    >
                        {{ $marca->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
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
        @error('descripcion')
            <small id="descripcion-error" class="field-error" role="alert">{{ $message }}</small>
        @enderror
    </div>
</div>

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
