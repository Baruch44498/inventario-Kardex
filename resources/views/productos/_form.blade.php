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
</div>

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-product-master-form]');
    const description = form?.querySelector('#descripcion');
    const warning = form?.querySelector('[data-product-similarity-warning]');
    const list = warning?.querySelector('[data-product-similarity-list]');
    if (!form || !description || !warning || !list) return;

    const hideWarning = () => {
        warning.hidden = true;
        warning.style.display = 'none';
        list.replaceChildren();
    };
    let lastChecked = '';
    let controller = null;

    description.addEventListener('input', () => {
        controller?.abort();
        controller = null;
        if (description.value.trim() !== lastChecked) hideWarning();
    });

    description.addEventListener('blur', async () => {
        const value = description.value.trim();
        if (value.length < 4 || value === lastChecked) return;
        controller?.abort();
        controller = new AbortController();

        try {
            const response = await fetch(form.dataset.similarityUrl, {
                method: 'POST',
                signal: controller.signal,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    descripcion: value,
                    excepto_id: form.dataset.productExceptId || null,
                }),
            });
            if (!response.ok) return;

            const data = await response.json();
            lastChecked = value;
            hideWarning();
            if (!data.hay_similares) return;

            (data.productos_similares || []).forEach((product) => {
                const item = document.createElement('li');
                const unit = product.unidad ? ` · ${product.unidad}` : '';
                item.textContent = `${product.codigo} — ${product.descripcion}${unit}`;
                list.appendChild(item);
            });
            warning.hidden = false;
            warning.style.display = '';
        } catch (error) {
            if (error.name !== 'AbortError') hideWarning();
        }
    });
})();
</script>
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
