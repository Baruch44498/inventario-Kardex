@csrf

@if (isset($repisa))
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
                value="{{ old('codigo', $repisa->codigo ?? '') }}"
                maxlength="40"
                placeholder="Ej. JB077"
                required
                autocomplete="off"
                aria-invalid="{{ $errors->has('codigo') ? 'true' : 'false' }}"
                @if ($errors->has('codigo')) aria-describedby="codigo-error" @endif
                @class(['is-invalid' => $errors->has('codigo')])
            >
        </div>
        <small>Ingresa el mismo código visible físicamente en el almacén.</small>
        @error('codigo')
            <small id="codigo-error" class="field-error" role="alert">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-field form-field--switch">
        <span class="form-label">Estado</span>
        <label class="switch-field">
            <input type="hidden" name="estado" value="0">
            <input
                type="checkbox"
                name="estado"
                value="1"
                @checked((bool) old('estado', $repisa->estado ?? true))
            >
            <span class="switch-field__control"></span>
            <span>Repisa activa</span>
        </label>
    </div>

    <div class="form-field form-grid__full">
        <label for="descripcion">Descripción</label>
        <div class="input-with-icon input-with-icon--textarea">
            <span class="input-with-icon__symbol">
                <x-ui.icon name="align-left" :size="18" />
            </span>
            <textarea
                id="descripcion"
                name="descripcion"
                rows="4"
                maxlength="180"
                placeholder="Ej. Zona de filtros y repuestos hidráulicos"
                aria-invalid="{{ $errors->has('descripcion') ? 'true' : 'false' }}"
                @if ($errors->has('descripcion')) aria-describedby="descripcion-error" @endif
                @class(['is-invalid' => $errors->has('descripcion')])
            >{{ old('descripcion', $repisa->descripcion ?? '') }}</textarea>
        </div>
        <div class="field-meta">
            <small>Indica qué tipo de productos o zona identifica esta ubicación.</small>
            <small data-character-count="descripcion">0 / 180</small>
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
        data-cancel-url="{{ route('repisas.index') }}"
    >
        Cancelar
    </button>

    <button
        type="submit"
        class="button button--primary"
        data-submit-button
        data-loading-text="{{ isset($repisa) ? 'Guardando...' : 'Registrando...' }}"
    >
        <span data-submit-icon>
            <x-ui.icon name="check" :size="18" />
        </span>
        <span class="button-spinner" data-submit-spinner hidden></span>
        <span data-submit-label>
            {{ isset($repisa) ? 'Guardar cambios' : 'Registrar repisa' }}
        </span>
    </button>
</div>
