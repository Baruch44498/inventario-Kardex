document.addEventListener('DOMContentLoaded', () => {
    const wizard = document.querySelector('[data-supplier-quote-wizard]');

    if (!wizard) return;

    const quoteForm = wizard.closest('form');
    const lines = wizard.querySelector('[data-supplier-quote-lines]');
    const addLineButton = wizard.querySelector('[data-add-supplier-quote-line]');
    const searchUrl = wizard.dataset.productSearchUrl;
    const createUrl = wizard.dataset.productCreateUrl;
    const modal = wizard.querySelector('[data-quick-product-modal]');
    const saveProductButton = modal?.querySelector('[data-save-quick-product]');
    const saveProductLabel = modal?.querySelector('[data-save-quick-product-label]');
    const saveProductSpinner = modal?.querySelector('[data-save-quick-product-spinner]');
    const generalError = modal?.querySelector('[data-quick-product-general-error]');
    const codeInput = modal?.querySelector('[data-quick-product-code]');
    const descriptionInput = modal?.querySelector('[data-quick-product-description]');
    const unitInput = modal?.querySelector('[data-quick-product-unit]');
    const brandInput = modal?.querySelector('[data-quick-product-brand]');
    const brandBox = brandInput?.closest('[data-remote-combobox]');
    const suggestionOutput = modal?.querySelector('[data-quick-product-suggestion]');
    const csrfToken = quoteForm?.querySelector('input[name="_token"]')?.value
        || document.querySelector('meta[name="csrf-token"]')?.content;
    const states = new WeakMap();
    let quickProductTarget = null;
    let focusBeforeModal = null;
    let comboboxSequence = 0;

    if (!quoteForm || !lines || !searchUrl || !createUrl) return;

    const allBoxes = () => Array.from(lines.querySelectorAll('[data-product-combobox]'));

    const setExpanded = (box, expanded) => {
        const search = box.querySelector('[data-product-search]');
        const results = box.querySelector('[data-product-results]');

        search?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (results) results.hidden = !expanded;
    };

    const closeResults = (box) => {
        const state = states.get(box);
        if (state) state.activeIndex = -1;
        setExpanded(box, false);
    };

    const updateProductValidity = (box) => {
        const search = box.querySelector('[data-product-search]');
        const productId = box.querySelector('[data-line-product]');

        if (!search || !productId) return;

        if (search.value.trim() !== '' && productId.value === '') {
            search.setCustomValidity('Selecciona un producto de los resultados.');
        } else {
            search.setCustomValidity('');
        }
    };

    const productAlreadySelected = (productId, currentBox) => allBoxes().some((box) => (
        box !== currentBox
        && box.querySelector('[data-line-product]')?.value === String(productId)
    ));

    const showBoxMessage = (box, message, type = 'error') => {
        box.querySelector('[data-product-inline-message]')?.remove();

        const messageNode = document.createElement('small');
        messageNode.dataset.productInlineMessage = '';
        messageNode.className = type === 'success'
            ? 'supplier-product-combobox__success'
            : 'field-error supplier-product-combobox__error';
        messageNode.textContent = message;
        box.appendChild(messageNode);

        if (type === 'success') {
            window.setTimeout(() => messageNode.remove(), 4500);
        }
    };

    const selectProduct = (box, product, successMessage = '') => {
        const search = box.querySelector('[data-product-search]');
        const productId = box.querySelector('[data-line-product]');
        const clearButton = box.querySelector('[data-product-clear]');

        if (!search || !productId) return false;

        if (productAlreadySelected(product.id, box)) {
            search.setCustomValidity('Este producto ya fue añadido a la cotización.');
            showBoxMessage(box, 'Este producto ya está seleccionado en otra fila.');
            search.reportValidity();
            return false;
        }

        productId.value = String(product.id);
        search.value = product.label;
        search.dataset.selectedLabel = product.label;
        search.setCustomValidity('');
        if (clearButton) clearButton.hidden = false;
        box.querySelector('[data-product-inline-message]')?.remove();
        closeResults(box);

        productId.dispatchEvent(new Event('input', { bubbles: true }));
        productId.dispatchEvent(new Event('change', { bubbles: true }));

        if (successMessage) showBoxMessage(box, successMessage, 'success');

        return true;
    };

    const clearProduct = (box, focus = false) => {
        const search = box.querySelector('[data-product-search]');
        const productId = box.querySelector('[data-line-product]');
        const clearButton = box.querySelector('[data-product-clear]');

        if (search) {
            search.value = '';
            search.dataset.selectedLabel = '';
            search.setCustomValidity('');
        }
        if (productId) {
            productId.value = '';
            productId.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (clearButton) clearButton.hidden = true;
        box.querySelector('[data-product-inline-message]')?.remove();
        closeResults(box);
        if (focus) search?.focus();
    };

    const createResultButton = (box, product, index) => {
        const button = document.createElement('button');
        const main = document.createElement('span');
        const code = document.createElement('strong');
        const description = document.createElement('span');
        const unit = document.createElement('small');

        button.type = 'button';
        button.className = 'supplier-product-result';
        button.dataset.productResultOption = String(index);
        button.setAttribute('role', 'option');
        button.setAttribute('aria-selected', 'false');
        code.textContent = product.codigo;
        description.textContent = product.descripcion;
        main.append(code, description);
        button.appendChild(main);

        if (product.unidad) {
            unit.textContent = product.unidad;
            button.appendChild(unit);
        }

        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => selectProduct(box, product));

        return button;
    };

    const paintActiveResult = (box) => {
        const state = states.get(box);
        const options = Array.from(box.querySelectorAll('[data-product-result-option]'));

        options.forEach((option, index) => {
            const active = index === state?.activeIndex;
            option.classList.toggle('is-active', active);
            option.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) option.scrollIntoView({ block: 'nearest' });
        });
    };

    const showSearchStatus = (box, message) => {
        const results = box.querySelector('[data-product-results]');
        if (!results) return;

        results.replaceChildren();
        const status = document.createElement('div');
        status.className = 'supplier-product-results__status';
        status.textContent = message;
        results.appendChild(status);
        setExpanded(box, true);
    };

    const openQuickProduct = (targetLine = null) => {
        if (!modal) return;

        focusBeforeModal = document.activeElement;
        quickProductTarget = targetLine?.closest('[data-supplier-quote-line]') || null;
        clearQuickProductErrors();
        codeInput.value = codeInput.dataset.nextCode || '';
        descriptionInput.value = '';
        unitInput.value = '';
        brandBox?.querySelector('[data-remote-combobox-clear]')?.click();
        modal.hidden = false;
        document.body.classList.add('modal-open');
        window.requestAnimationFrame(() => {
            codeInput.focus();
            codeInput.select();
        });
    };

    const renderProducts = (box, products) => {
        const results = box.querySelector('[data-product-results]');
        const state = states.get(box);
        if (!results || !state) return;

        results.replaceChildren();
        state.activeIndex = -1;

        if (products.length === 0) {
            const empty = document.createElement('div');
            const text = document.createElement('span');
            const createButton = document.createElement('button');

            empty.className = 'supplier-product-results__empty';
            text.textContent = 'No se encontró un producto activo con esa búsqueda.';
            createButton.type = 'button';
            createButton.className = 'button button--ghost button--small';
            createButton.textContent = 'Registrar producto nuevo';
            createButton.addEventListener('mousedown', (event) => event.preventDefault());
            createButton.addEventListener('click', () => openQuickProduct(box));
            empty.append(text, createButton);
            results.appendChild(empty);
        } else {
            products.forEach((product, index) => {
                results.appendChild(createResultButton(box, product, index));
            });
        }

        setExpanded(box, true);
    };

    const searchProducts = async (box) => {
        const search = box.querySelector('[data-product-search]');
        const state = states.get(box);
        const term = search?.value.trim() || '';

        if (!search || !state || term === '') {
            closeResults(box);
            return;
        }

        state.controller?.abort();
        state.controller = new AbortController();
        showSearchStatus(box, 'Buscando productos...');

        try {
            const url = new URL(searchUrl, window.location.origin);
            url.searchParams.set('q', term);
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: state.controller.signal,
            });

            if (!response.ok) throw new Error('No se pudo consultar el catálogo.');

            const payload = await response.json();
            if (search.value.trim() === term) {
                renderProducts(box, payload.productos || []);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                showSearchStatus(box, 'No se pudo buscar. Intenta nuevamente.');
            }
        }
    };

    const initCombobox = (box) => {
        if (box.dataset.productComboboxReady === 'true') return;

        const search = box.querySelector('[data-product-search]');
        const productId = box.querySelector('[data-line-product]');
        const clearButton = box.querySelector('[data-product-clear]');
        const results = box.querySelector('[data-product-results]');
        if (!search || !productId || !results) return;

        box.dataset.productComboboxReady = 'true';
        search.dataset.selectedLabel = productId.value ? search.value : '';
        comboboxSequence += 1;
        results.id = `supplier-product-results-${comboboxSequence}`;
        search.setAttribute('aria-controls', results.id);
        states.set(box, { timer: null, controller: null, activeIndex: -1 });
        updateProductValidity(box);

        search.addEventListener('input', () => {
            const state = states.get(box);

            if (search.value !== search.dataset.selectedLabel) {
                productId.value = '';
                if (clearButton) clearButton.hidden = search.value === '';
                productId.dispatchEvent(new Event('change', { bubbles: true }));
            }

            box.querySelector('[data-product-inline-message]')?.remove();
            updateProductValidity(box);
            window.clearTimeout(state.timer);
            state.timer = window.setTimeout(() => searchProducts(box), 250);
        });

        search.addEventListener('focus', () => {
            if (!productId.value && search.value.trim() !== '') searchProducts(box);
        });

        search.addEventListener('keydown', (event) => {
            const state = states.get(box);
            const options = Array.from(box.querySelectorAll('[data-product-result-option]'));

            if (event.key === 'ArrowDown' && options.length > 0) {
                event.preventDefault();
                state.activeIndex = Math.min(state.activeIndex + 1, options.length - 1);
                paintActiveResult(box);
            } else if (event.key === 'ArrowUp' && options.length > 0) {
                event.preventDefault();
                state.activeIndex = Math.max(state.activeIndex - 1, 0);
                paintActiveResult(box);
            } else if (event.key === 'Enter' && state.activeIndex >= 0) {
                event.preventDefault();
                options[state.activeIndex]?.click();
            } else if (event.key === 'Escape') {
                closeResults(box);
            }
        });

        search.addEventListener('blur', () => {
            window.setTimeout(() => closeResults(box), 160);
        });

        clearButton?.addEventListener('click', () => clearProduct(box, true));
    };

    const clearQuickProductErrors = () => {
        generalError.hidden = true;
        modal?.querySelectorAll('[data-quick-product-error]').forEach((node) => {
            node.textContent = '';
            node.hidden = true;
        });
        modal?.querySelectorAll('input, select, textarea').forEach((control) => {
            control.classList.remove('is-invalid');
        });
    };

    const showQuickProductErrors = (errors, fallback = '') => {
        clearQuickProductErrors();
        let painted = false;

        Object.entries(errors || {}).forEach(([field, messages]) => {
            const error = modal?.querySelector(`[data-quick-product-error="${field}"]`);
            const control = modal?.querySelector(`[data-quick-product-${field === 'unidad_medida_id'
                ? 'unit'
                : field === 'marca_principal_id'
                    ? 'brand'
                    : field}]`);

            if (error) {
                error.textContent = Array.isArray(messages) ? messages[0] : messages;
                error.hidden = false;
                control?.classList.add('is-invalid');
                painted = true;
            }
        });

        if (!painted || fallback) {
            generalError.querySelector('span').textContent = fallback
                || 'Revisa los datos e intenta nuevamente.';
            generalError.hidden = false;
        }
    };

    const closeQuickProduct = () => {
        if (!modal) return;

        brandBox?.querySelector('[data-remote-combobox-clear]')?.click();
        modal.hidden = true;
        quickProductTarget = null;
        if (!document.querySelector('.modal-backdrop:not([hidden])')) {
            document.body.classList.remove('modal-open');
        }
        focusBeforeModal?.focus?.();
    };

    const targetBoxForNewProduct = () => {
        const targetBox = quickProductTarget?.querySelector('[data-product-combobox]');
        if (targetBox?.querySelector('[data-line-product]')?.value === '') {
            return targetBox;
        }

        const emptyBox = allBoxes().find((box) => (
            box.querySelector('[data-line-product]')?.value === ''
        ));
        if (emptyBox) return emptyBox;

        addLineButton?.click();
        const newBox = allBoxes().at(-1);
        if (newBox) initCombobox(newBox);

        return newBox;
    };

    const saveQuickProduct = async () => {
        const payload = {
            codigo: codeInput.value.trim(),
            descripcion: descriptionInput.value.trim(),
            unidad_medida_id: unitInput.value,
            marca_principal_id: brandInput.value || null,
        };
        const localErrors = {};

        if (!payload.codigo) localErrors.codigo = ['Ingresa el código del producto.'];
        if (!payload.descripcion) localErrors.descripcion = ['Ingresa la descripción del producto.'];
        if (!payload.unidad_medida_id) {
            localErrors.unidad_medida_id = ['Selecciona la unidad de medida.'];
        }

        if (Object.keys(localErrors).length > 0) {
            showQuickProductErrors(localErrors);
            return;
        }

        clearQuickProductErrors();
        saveProductButton.disabled = true;
        saveProductButton.setAttribute('aria-busy', 'true');
        saveProductLabel.textContent = 'Registrando...';
        saveProductSpinner.hidden = false;

        try {
            const response = await fetch(createUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json().catch(() => ({}));

            if (response.status === 422) {
                showQuickProductErrors(result.errors, result.message || '');
                return;
            }
            if (!response.ok || !result.producto) {
                throw new Error(result.message || 'No se pudo registrar el producto.');
            }

            const targetBox = targetBoxForNewProduct();
            if (targetBox) {
                selectProduct(targetBox, result.producto, 'Producto nuevo seleccionado.');
            }

            if (result.siguiente_codigo) {
                codeInput.dataset.nextCode = result.siguiente_codigo;
                suggestionOutput.textContent = result.siguiente_codigo;
            }

            closeQuickProduct();
        } catch (error) {
            showQuickProductErrors({}, error.message || 'No se pudo registrar el producto.');
        } finally {
            saveProductButton.disabled = false;
            saveProductButton.removeAttribute('aria-busy');
            saveProductLabel.textContent = 'Registrar y seleccionar';
            saveProductSpinner.hidden = true;
        }
    };

    allBoxes().forEach(initCombobox);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;
                if (node.matches('[data-product-combobox]')) initCombobox(node);
                node.querySelectorAll?.('[data-product-combobox]').forEach(initCombobox);
            });
        });
    });
    observer.observe(lines, { childList: true, subtree: true });

    wizard.querySelector('[data-open-quick-product]')?.addEventListener(
        'click',
        () => openQuickProduct()
    );
    modal?.querySelectorAll('[data-close-quick-product]').forEach((button) => {
        button.addEventListener('click', closeQuickProduct);
    });
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) closeQuickProduct();
    });
    modal?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeQuickProduct();
        } else if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA') {
            event.preventDefault();
            saveQuickProduct();
        }
    });
    saveProductButton?.addEventListener('click', saveQuickProduct);

    document.addEventListener('click', (event) => {
        allBoxes().forEach((box) => {
            if (!box.contains(event.target)) closeResults(box);
        });
    });
});
