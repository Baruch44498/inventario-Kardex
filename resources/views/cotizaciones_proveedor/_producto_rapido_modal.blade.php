<div class="modal-backdrop supplier-product-modal-backdrop"
    data-quick-product-modal hidden>
    <section class="quick-product-modal" role="dialog" aria-modal="true"
        aria-labelledby="quick-product-modal-title"
        aria-describedby="quick-product-modal-description">
        <header class="quick-product-modal__header">
            <div>
                <p class="eyebrow">Catálogo de productos</p>
                <h2 id="quick-product-modal-title">Registrar producto nuevo</h2>
                <p id="quick-product-modal-description">
                    Completa los datos mínimos sin abandonar la cotización.
                </p>
            </div>
            <button type="button" class="icon-button" aria-label="Cerrar"
                title="Cerrar" data-close-quick-product>
                <x-ui.icon name="close" :size="18" />
            </button>
        </header>

        <div class="quick-product-modal__notice">
            <x-ui.icon name="info" :size="19" />
            <p>
                Se creará activo, con stock 0 y sin movimiento de Kardex. El código
                se valida contra los productos ya cargados; si el Excel histórico aún
                no fue importado, confirma allí que el código no esté usado.
            </p>
        </div>

        <div class="notice notice--danger notice--block quick-product-modal__error"
            role="alert" data-quick-product-general-error hidden>
            <x-ui.icon name="error" :size="18" />
            <span>No se pudo registrar el producto.</span>
        </div>

        <div class="quick-product-modal__grid" data-quick-product-fields>
            <div class="form-field">
                <label for="quick_product_code">
                    Código <span class="required-mark">*</span>
                </label>
                <input id="quick_product_code" type="text" maxlength="50"
                    value="{{ $codigoProductoSugerido }}" autocomplete="off"
                    data-quick-product-code data-next-code="{{ $codigoProductoSugerido }}">
                <small>
                    Sugerido según el mayor código numérico registrado:
                    <strong data-quick-product-suggestion>{{ $codigoProductoSugerido }}</strong>
                </small>
                <small class="field-error" role="alert"
                    data-quick-product-error="codigo" hidden></small>
            </div>

            <div class="form-field">
                <label for="quick_product_unit">
                    Unidad de medida <span class="required-mark">*</span>
                </label>
                <select id="quick_product_unit" data-quick-product-unit>
                    <option value="">Seleccionar</option>
                    @foreach ($unidadesProducto as $unidad)
                        <option value="{{ $unidad->id }}">
                            {{ $unidad->codigo }} · {{ $unidad->nombre }}
                        </option>
                    @endforeach
                </select>
                <small class="field-error" role="alert"
                    data-quick-product-error="unidad_medida_id" hidden></small>
            </div>

            <div class="form-field">
                <label for="quick_product_brand_search">Marca principal</label>
                <x-ui.remote-combobox
                    name="quick_product_brand_selector"
                    search-id="quick_product_brand_search"
                    value-id="quick_product_brand"
                    :search-url="route('catalogos.marcas.buscar')"
                    placeholder="Nombre de marca"
                    empty-text="No se encontró una marca activa."
                    :value-attributes="['data-quick-product-brand' => '']"
                />
                <small class="field-error" role="alert"
                    data-quick-product-error="marca_principal_id" hidden></small>
            </div>

            <div class="form-field quick-product-modal__description">
                <label for="quick_product_description">
                    Descripción <span class="required-mark">*</span>
                </label>
                <textarea id="quick_product_description" rows="4" maxlength="500"
                    placeholder="Ej. Filtro hidráulico de retorno"
                    data-quick-product-description></textarea>
                <small class="field-error" role="alert"
                    data-quick-product-error="descripcion" hidden></small>
            </div>
        </div>

        <footer class="quick-product-modal__actions">
            <button type="button" class="button button--ghost"
                data-close-quick-product>Cancelar</button>
            <button type="button" class="button button--primary"
                data-save-quick-product>
                <x-ui.icon name="check" :size="18" />
                <span data-save-quick-product-label>Registrar y seleccionar</span>
                <span class="button-spinner" data-save-quick-product-spinner hidden></span>
            </button>
        </footer>
    </section>
</div>
