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
                <span class="form-label">Presentaciones de compra</span>
                <small>Convierte rollos, cajas o bidones a la unidad base del inventario.</small>
            </div>
            <button type="button" class="button button--ghost button--small" data-add-presentation>
                <x-ui.icon name="plus" :size="16" /> Agregar presentación
            </button>
        </div>

        <div class="notice notice--info notice--block">
            <x-ui.icon name="info" :size="18" />
            <div>
                <strong>El stock siempre se guarda en la unidad base</strong>
                <span>Ejemplo: “Rollo” con factor 100 convierte 1 rollo en 100 metros.</span>
            </div>
        </div>

        <div class="product-presentations__rows" data-presentation-rows>
            @foreach ($presentacionesIniciales as $indice => $presentacion)
                <div class="product-presentation-row" data-presentation-row>
                    <input type="hidden" name="presentaciones[{{ $indice }}][id]" value="{{ $presentacion['id'] ?? '' }}" data-presentation-field="id">
                    <label class="form-field">
                        <span>Nombre</span>
                        <input type="text" name="presentaciones[{{ $indice }}][nombre]" value="{{ $presentacion['nombre'] ?? '' }}" maxlength="80" placeholder="Ej. Rollo" required data-presentation-field="nombre">
                    </label>
                    <label class="form-field">
                        <span>Equivale a</span>
                        <input type="number" name="presentaciones[{{ $indice }}][factor_conversion]" value="{{ $presentacion['factor_conversion'] ?? '' }}" min="0.001" step="0.001" required data-presentation-field="factor_conversion">
                        <small><span data-presentation-base-unit>unidades base</span> por presentación</small>
                    </label>
                    <label class="switch-field product-presentation-row__check">
                        <input type="hidden" name="presentaciones[{{ $indice }}][es_predeterminada]" value="0" data-presentation-hidden-default>
                        <input type="checkbox" name="presentaciones[{{ $indice }}][es_predeterminada]" value="1" @checked((bool) ($presentacion['es_predeterminada'] ?? false)) data-presentation-default>
                        <span class="switch-field__control"></span>
                        <span>Predeterminada</span>
                    </label>
                    <label class="switch-field product-presentation-row__check">
                        <input type="hidden" name="presentaciones[{{ $indice }}][estado]" value="0" data-presentation-hidden-state>
                        <input type="checkbox" name="presentaciones[{{ $indice }}][estado]" value="1" @checked((bool) ($presentacion['estado'] ?? true)) data-presentation-state>
                        <span class="switch-field__control"></span>
                        <span>Activa</span>
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
                <label class="form-field"><span>Nombre</span><input type="text" maxlength="80" placeholder="Ej. Rollo" required data-presentation-field="nombre"></label>
                <label class="form-field"><span>Equivale a</span><input type="number" min="0.001" step="0.001" required data-presentation-field="factor_conversion"><small><span data-presentation-base-unit>unidades base</span> por presentación</small></label>
                <label class="switch-field product-presentation-row__check"><input type="hidden" value="0" data-presentation-hidden-default><input type="checkbox" value="1" data-presentation-default><span class="switch-field__control"></span><span>Predeterminada</span></label>
                <label class="switch-field product-presentation-row__check"><input type="hidden" value="0" data-presentation-hidden-state><input type="checkbox" value="1" checked data-presentation-state><span class="switch-field__control"></span><span>Activa</span></label>
                <button type="button" class="icon-button icon-button--danger" title="Quitar presentación" aria-label="Quitar presentación" data-remove-presentation><x-ui.icon name="close" :size="16" /></button>
            </div>
        </template>

        @error('presentaciones')<small class="field-error" role="alert">{{ $message }}</small>@enderror
        @error('presentaciones.*')<small class="field-error" role="alert">{{ $message }}</small>@enderror
    </section>
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

(() => {
    const form = document.querySelector('[data-product-master-form]');
    const wrapper = form?.querySelector('[data-product-presentations]');
    const rows = wrapper?.querySelector('[data-presentation-rows]');
    const template = wrapper?.querySelector('[data-presentation-template]');
    const unit = form?.querySelector('#id_unidad_medida');
    const fractional = form?.querySelector('[data-product-fractional]');
    if (!form || !wrapper || !rows || !template || !unit || !fractional) return;

    let nextIndex = rows.querySelectorAll('[data-presentation-row]').length;
    let fractionalTouched = {{ isset($producto) ? 'true' : 'false' }};

    const baseUnit = () => unit.selectedOptions?.[0]?.dataset.unitCode || 'unidad base';

    const renameRow = (row, index) => {
        row.querySelectorAll('[data-presentation-field]').forEach((input) => {
            input.name = `presentaciones[${index}][${input.dataset.presentationField}]`;
        });
        row.querySelector('[data-presentation-hidden-default]').name = `presentaciones[${index}][es_predeterminada]`;
        row.querySelector('[data-presentation-default]').name = `presentaciones[${index}][es_predeterminada]`;
        row.querySelector('[data-presentation-hidden-state]').name = `presentaciones[${index}][estado]`;
        row.querySelector('[data-presentation-state]').name = `presentaciones[${index}][estado]`;
    };

    const refreshUnits = () => {
        rows.querySelectorAll('[data-presentation-base-unit]').forEach((label) => {
            label.textContent = baseUnit();
        });
    };

    const bindRow = (row) => {
        row.querySelector('[data-remove-presentation]')?.addEventListener('click', () => row.remove());
        row.querySelector('[data-presentation-default]')?.addEventListener('change', (event) => {
            if (!event.target.checked) return;
            rows.querySelectorAll('[data-presentation-default]').forEach((checkbox) => {
                if (checkbox !== event.target) checkbox.checked = false;
            });
        });
    };

    rows.querySelectorAll('[data-presentation-row]').forEach((row, index) => {
        renameRow(row, index);
        bindRow(row);
    });

    wrapper.querySelector('[data-add-presentation]')?.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('[data-presentation-row]');
        renameRow(row, nextIndex++);
        bindRow(row);
        rows.appendChild(row);
        refreshUnits();
        row.querySelector('[data-presentation-field="nombre"]')?.focus();
    });

    fractional.addEventListener('change', () => { fractionalTouched = true; });
    unit.addEventListener('change', () => {
        refreshUnits();
        if (!fractionalTouched) {
            fractional.checked = ['M', 'KG', 'LT'].includes(baseUnit());
        }
    });
    refreshUnits();
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
