(() => {
    const form = document.querySelector('[data-product-master-form]');
    const description = form?.querySelector('#descripcion');
    const warning = form?.querySelector('[data-product-similarity-warning]');
    const list = warning?.querySelector('[data-product-similarity-list]');
    if (!form || !description || !warning || !list) return;

    const hint = warning.querySelector('small');
    const submitButton = form.querySelector('[data-submit-button]');
    let checkedValue = '';
    let checkedMatches = false;
    let confirmedValue = '';
    let controller = null;
    let timer = null;
    let submitting = false;

    const hideWarning = () => {
        warning.hidden = true;
        warning.style.display = 'none';
        list.replaceChildren();
        if (hint) hint.textContent = 'Esta advertencia no bloquea el registro.';
    };

    const checkSimilarity = async (value) => {
        if (value === checkedValue) return checkedMatches;
        controller?.abort();
        const request = new AbortController();
        controller = request;

        try {
            const response = await fetch(form.dataset.similarityUrl, {
                method: 'POST',
                signal: request.signal,
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
            if (!response.ok) throw new Error('No se pudo comprobar la descripción.');

            const data = await response.json();
            if (request !== controller || description.value.trim() !== value) return null;

            checkedValue = value;
            checkedMatches = Boolean(data.hay_similares);
            hideWarning();
            if (!checkedMatches) return false;

            (data.productos_similares || []).forEach((product) => {
                const item = document.createElement('li');
                const unit = product.unidad ? ` · ${product.unidad}` : '';
                item.textContent = `${product.codigo} — ${product.descripcion}${unit}`;
                list.appendChild(item);
            });
            warning.hidden = false;
            warning.style.display = '';
            return true;
        } catch (error) {
            if (error.name === 'AbortError' || request !== controller) return null;
            throw error;
        }
    };

    const checkInBackground = () => {
        const value = description.value.trim();
        if (value.length < 4 || value === checkedValue) return;
        checkSimilarity(value).catch(() => {
            // El envío muestra el error si todavía no se puede verificar.
        });
    };

    description.addEventListener('input', () => {
        clearTimeout(timer);
        controller?.abort();
        checkedValue = '';
        confirmedValue = '';
        description.setCustomValidity('');
        hideWarning();
        if (description.value.trim().length >= 4) {
            timer = setTimeout(checkInBackground, 450);
        }
    });

    description.addEventListener('blur', () => {
        clearTimeout(timer);
        checkInBackground();
    });

    form.addEventListener('submit', async (event) => {
        if (submitting) return;
        event.preventDefault();
        if (form.dataset.submitting === 'true') return;

        description.setCustomValidity('');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        clearTimeout(timer);
        const value = description.value.trim();
        const submitter = event.submitter || submitButton;
        if (value.length >= 4) {
            let hasSimilar;
            try {
                hasSimilar = await checkSimilarity(value);
            } catch (error) {
                description.setCustomValidity('No se pudo comprobar si existe un producto similar. Vuelve a intentar.');
                description.reportValidity();
                return;
            }
            if (hasSimilar === null) return;
            if (hasSimilar && confirmedValue !== value) {
                confirmedValue = value;
                if (hint) hint.textContent = 'Revisa las coincidencias. Si es otro producto, pulsa Guardar de nuevo para confirmar.';
                warning.setAttribute('tabindex', '-1');
                warning.scrollIntoView({ block: 'center' });
                warning.focus({ preventScroll: true });
                return;
            }
        }

        submitting = true;
        form.requestSubmit(submitter);
    }, { capture: true });
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
    let fractionalTouched = Boolean(form.dataset.productExceptId);

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
        rows.querySelectorAll('[data-presentation-row]').forEach((row) => {
            const label = row.querySelector('[data-presentation-base-unit]');
            const preview = row.querySelector('[data-presentation-preview]');
            const name = row.querySelector('[data-presentation-field="nombre"]')?.value.trim();
            const factor = Number(row.querySelector('[data-presentation-field="factor_conversion"]')?.value || 0);
            if (label) label.textContent = baseUnit();
            if (preview) {
                preview.textContent = name && factor > 0
                    ? `1 ${name} = ${factor.toFixed(2)} ${baseUnit()}`
                    : 'Completa ambos campos para ver la conversión.';
            }
        });
    };

    const bindRow = (row) => {
        row.querySelector('[data-remove-presentation]')?.addEventListener('click', () => row.remove());
        row.querySelector('[data-presentation-field="nombre"]')?.addEventListener('input', refreshUnits);
        row.querySelector('[data-presentation-field="factor_conversion"]')?.addEventListener('input', refreshUnits);
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
            fractional.checked = ['MTS', 'GLN', 'LT'].includes(baseUnit());
        }
    });
    refreshUnits();
})();
