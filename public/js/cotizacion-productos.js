document.addEventListener('DOMContentLoaded', () => {
    const wizard = document.querySelector('[data-supplier-quote-wizard]');

    if (!wizard) return;

    const quoteForm = wizard.closest('form');
    const lines = wizard.querySelector('[data-supplier-quote-lines]');
    const addLineButton = wizard.querySelector('[data-add-supplier-quote-line]');
    const searchUrl = wizard.dataset.productSearchUrl;
    const linkingUrl = wizard.dataset.productLinkingUrl;
    const createUrl = wizard.dataset.productCreateUrl;
    const requisitionInput = wizard.querySelector('#requisicion_id');
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
    let confirmSimilarProduct = false;

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

    const selectProduct = (box, product, successMessage = '', source = 'CATALOGO') => {
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
        box.dispatchEvent(new CustomEvent('supplier-product-selected', {
            bubbles: true,
            detail: { product, source },
        }));

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
        box.dispatchEvent(new CustomEvent('supplier-product-cleared', { bubbles: true }));
        if (clearButton) clearButton.hidden = true;
        box.querySelector('[data-product-inline-message]')?.remove();
        closeResults(box);
        if (focus) search?.focus();
    };

    const linkingControls = (line) => ({
        panel: line.querySelector('[data-product-linking]'),
        type: line.querySelector('[data-line-link-type]'),
        requested: line.querySelector('[data-line-requisition-detail]'),
        origin: line.querySelector('[data-line-link-origin]'),
        confirmed: line.querySelector('[data-line-link-confirmed]'),
        status: line.querySelector('[data-linking-status]'),
        message: line.querySelector('[data-linking-message]'),
        suggestions: line.querySelector('[data-linking-suggestions]'),
    });

    const requestedOptions = (select) => Array.from(select?.options || [])
        .filter((option) => option.value !== '');

    const paintLinking = (line) => {
        const controls = linkingControls(line);
        if (!controls.panel || !controls.type || !controls.requested) return;

        const hasRequisition = Boolean(requisitionInput?.value);
        const productId = line.querySelector('[data-line-product]')?.value || '';
        const type = hasRequisition ? controls.type.value : 'ADICIONAL';
        const requestedOption = controls.requested.selectedOptions?.[0];
        const requestedProductId = requestedOption?.dataset.productId || '';

        controls.panel.hidden = !hasRequisition;
        controls.requested.disabled = !hasRequisition || type === 'ADICIONAL';
        controls.requested.required = hasRequisition && type !== 'ADICIONAL';
        controls.requested.setCustomValidity('');

        if (!hasRequisition || type === 'ADICIONAL') {
            controls.requested.value = '';
        } else if (!controls.requested.value) {
            controls.requested.setCustomValidity(
                type === 'ALTERNATIVA'
                    ? 'Selecciona qué producto solicitado reemplaza esta alternativa.'
                    : 'Selecciona la línea del requerimiento que corresponde al producto.'
            );
        } else if (type === 'SOLICITADO' && productId !== requestedProductId) {
            controls.requested.setCustomValidity(
                'El producto ofrecido no coincide con la línea solicitada seleccionada.'
            );
        } else if (type === 'ALTERNATIVA' && productId === requestedProductId) {
            controls.requested.setCustomValidity(
                'El mismo producto solicitado no debe marcarse como alternativa.'
            );
        }

        if (controls.status) {
            controls.status.textContent = type === 'SOLICITADO'
                ? 'Solicitado'
                : type === 'ALTERNATIVA'
                    ? 'Alternativa'
                    : 'Adicional';
            controls.status.className = 'badge ' + (
                type === 'SOLICITADO'
                    ? 'badge--success'
                    : type === 'ALTERNATIVA'
                        ? 'badge--warning'
                        : 'badge--neutral'
            );
        }

        if (controls.message) {
            controls.message.textContent = type === 'SOLICITADO'
                ? 'El producto ofrecido coincide con el solicitado.'
                : type === 'ALTERNATIVA'
                    ? 'La alternativa se compara contra la línea seleccionada sin modificar el requerimiento original.'
                    : 'El producto forma parte de la oferta, pero no cubre una línea del requerimiento.';
        }
    };

    const setRequestedLines = (line, requirementLines) => {
        const { requested } = linkingControls(line);
        if (!requested || !Array.isArray(requirementLines)) return;

        const previous = requested.value;
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Seleccionar línea del requerimiento';
        requested.replaceChildren(placeholder);

        requirementLines.forEach((requirementLine) => {
            const option = document.createElement('option');
            option.value = String(requirementLine.id);
            option.dataset.productId = String(requirementLine.producto_id);
            option.textContent = requirementLine.label
                + ' · solicitado: '
                + Number(requirementLine.cantidad || 0).toLocaleString('es-PE', {
                    maximumFractionDigits: 2,
                });
            requested.appendChild(option);
        });

        if (requested.querySelector(`option[value="${CSS.escape(previous)}"]`)) {
            requested.value = previous;
        }
    };

    const relationOrigin = (line, source) => {
        if (source === 'AUTOMATICA') return 'AUTOMATICA';
        if (source === 'ALTA') return 'ALTA';
        if (line.dataset.importedCode || line.dataset.importedDescription) {
            return 'CONFIRMADA';
        }
        return 'MANUAL';
    };

    const applySelectedProductLink = (line, product, source = 'CATALOGO') => {
        const controls = linkingControls(line);
        if (!controls.type || !controls.requested) return;

        const exactRequested = requestedOptions(controls.requested).find(
            (option) => option.dataset.productId === String(product.id)
        );

        if (requisitionInput?.value && exactRequested) {
            controls.type.value = 'SOLICITADO';
            controls.requested.value = exactRequested.value;
        } else {
            controls.type.value = 'ADICIONAL';
            controls.requested.value = '';
        }

        if (controls.origin) controls.origin.value = relationOrigin(line, source);
        if (controls.confirmed) controls.confirmed.value = '1';
        paintLinking(line);
    };

    const renderLinkingSuggestions = (line, candidates) => {
        const { suggestions } = linkingControls(line);
        const box = line.querySelector('[data-product-combobox]');
        if (!suggestions || !box) return;

        suggestions.replaceChildren();
        if (!Array.isArray(candidates) || candidates.length === 0) {
            suggestions.hidden = true;
            return;
        }

        const title = document.createElement('strong');
        title.textContent = 'Posibles coincidencias — confirma una si corresponde';
        suggestions.appendChild(title);

        candidates.forEach((candidate) => {
            const button = document.createElement('button');
            const label = document.createElement('span');
            const meta = document.createElement('small');
            button.type = 'button';
            button.className = 'supplier-product-linking__suggestion';
            label.textContent = candidate.codigo + ' — ' + candidate.descripcion;
            meta.textContent = candidate.ambito === 'REQUERIMIENTO'
                ? 'Producto del requerimiento'
                : 'Producto existente en catálogo';
            button.append(label, meta);
            button.addEventListener('click', () => {
                const product = candidate.producto || {
                    id: candidate.producto_id,
                    codigo: candidate.codigo,
                    descripcion: candidate.descripcion,
                    unidad: candidate.unidad,
                    label: candidate.label,
                };
                if (!selectProduct(box, product, 'Coincidencia confirmada.', 'CONFIRMADA')) {
                    return;
                }

                const controls = linkingControls(line);
                controls.type.value = candidate.tipo_vinculacion_propuesto || 'ADICIONAL';
                controls.requested.value = candidate.requisicion_detalle_id
                    ? String(candidate.requisicion_detalle_id)
                    : '';
                if (controls.origin) controls.origin.value = 'CONFIRMADA';
                if (controls.confirmed) controls.confirmed.value = '1';
                suggestions.hidden = true;
                paintLinking(line);
            });
            suggestions.appendChild(button);
        });

        suggestions.hidden = false;
    };

    const loadLinking = async (line) => {
        if (!linkingUrl) return;

        const code = line.dataset.importedCode || '';
        const description = line.dataset.importedDescription || '';
        const url = new URL(linkingUrl, window.location.origin);
        if (code) url.searchParams.set('codigo', code);
        if (description) url.searchParams.set('descripcion', description);
        if (requisitionInput?.value) {
            url.searchParams.set('requisicion_id', requisitionInput.value);
        }

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!response.ok) throw new Error('No se pudo revisar la vinculación.');

            const result = await response.json();
            setRequestedLines(line, result.lineas_requerimiento || []);

            const currentProduct = line.querySelector('[data-line-product]');
            const box = line.querySelector('[data-product-combobox]');
            if (result.producto && !currentProduct?.value && box) {
                selectProduct(box, result.producto, 'Producto detectado con seguridad.', 'AUTOMATICA');
                const controls = linkingControls(line);
                controls.type.value = result.tipo_vinculacion || 'ADICIONAL';
                controls.requested.value = result.requisicion_detalle_id
                    ? String(result.requisicion_detalle_id)
                    : '';
                if (controls.origin) controls.origin.value = 'AUTOMATICA';
                if (controls.confirmed) controls.confirmed.value = '1';
            }

            renderLinkingSuggestions(line, result.candidatos || []);
            paintLinking(line);
        } catch (error) {
            const { message } = linkingControls(line);
            if (message) {
                message.textContent = 'No se pudieron cargar sugerencias. Puedes vincular el producto manualmente.';
            }
        }
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
        confirmSimilarProduct = false;
        clearQuickProductErrors();
        codeInput.value = codeInput.dataset.nextCode || '';
        descriptionInput.value = quickProductTarget?.dataset.importedDescription || '';
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
            confirmar_similitud: confirmSimilarProduct,
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
                if (result.producto_existente) {
                    const targetBox = targetBoxForNewProduct();
                    if (targetBox) {
                        selectProduct(
                            targetBox,
                            result.producto_existente,
                            'Se seleccionó el producto existente; no se creó un duplicado.',
                            'CONFIRMADA'
                        );
                    }
                    closeQuickProduct();
                    return;
                }

                if (result.requiere_confirmacion_similitud) {
                    confirmSimilarProduct = true;
                    const similares = (result.productos_similares || [])
                        .map((producto) => producto.codigo + ' — ' + producto.descripcion)
                        .join(' | ');
                    showQuickProductErrors(
                        result.errors,
                        similares
                            ? 'Revisa: ' + similares + '. Si ninguno corresponde, vuelve a presionar Crear.'
                            : result.message
                    );
                    saveProductLabel.textContent = 'Crear de todos modos';
                    return;
                }

                showQuickProductErrors(result.errors, result.message || '');
                return;
            }
            if (!response.ok || !result.producto) {
                throw new Error(result.message || 'No se pudo registrar el producto.');
            }

            const targetBox = targetBoxForNewProduct();
            if (targetBox) {
                selectProduct(targetBox, result.producto, 'Producto nuevo seleccionado.', 'ALTA');
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
            saveProductLabel.textContent = confirmSimilarProduct
                ? 'Crear de todos modos'
                : 'Registrar y seleccionar';
            saveProductSpinner.hidden = true;
        }
    };

    allBoxes().forEach(initCombobox);

    lines.addEventListener('supplier-product-selected', (event) => {
        const line = event.target.closest('[data-supplier-quote-line]');
        if (!line || !event.detail?.product) return;
        applySelectedProductLink(line, event.detail.product, event.detail.source);
    });

    lines.addEventListener('supplier-product-cleared', (event) => {
        const line = event.target.closest('[data-supplier-quote-line]');
        if (!line) return;

        const controls = linkingControls(line);
        if (controls.type) controls.type.value = 'ADICIONAL';
        if (controls.requested) controls.requested.value = '';
        if (controls.confirmed) {
            controls.confirmed.value = line.dataset.importedCode
                || line.dataset.importedDescription ? '0' : '1';
        }
        paintLinking(line);
    });

    lines.addEventListener('change', (event) => {
        const line = event.target.closest('[data-supplier-quote-line]');
        if (!line) return;

        if (event.target.matches('[data-line-link-type], [data-line-requisition-detail]')) {
            const controls = linkingControls(line);
            if (controls.confirmed) controls.confirmed.value = '1';
            if (controls.origin) {
                controls.origin.value = line.dataset.importedCode
                    || line.dataset.importedDescription ? 'CONFIRMADA' : 'MANUAL';
            }
            paintLinking(line);
        }
    });

    lines.querySelectorAll('[data-supplier-quote-line]').forEach((line) => {
        paintLinking(line);
        loadLinking(line);
    });

    requisitionInput?.addEventListener('change', () => {
        lines.querySelectorAll('[data-supplier-quote-line]').forEach((line) => {
            loadLinking(line);
        });
    });

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;
                if (node.matches('[data-product-combobox]')) initCombobox(node);
                node.querySelectorAll?.('[data-product-combobox]').forEach(initCombobox);
                if (node.matches('[data-supplier-quote-line]')) {
                    paintLinking(node);
                    loadLinking(node);
                }
                node.querySelectorAll?.('[data-supplier-quote-line]').forEach((line) => {
                    paintLinking(line);
                    loadLinking(line);
                });
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
