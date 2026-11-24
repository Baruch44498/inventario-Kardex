(() => {
    const form = document.querySelector('[data-periodic-shelf-picker]');
    if (!form) return;

    const search = form.querySelector('#periodic-shelf-prefix');
    const results = form.querySelector('[data-shelf-results]');
    const searchStatus = form.querySelector('[data-shelf-search-status]');
    const selectResults = form.querySelector('[data-select-periodic-shelves]');
    const clearSearch = form.querySelector('[data-clear-shelf-search]');
    const retry = form.querySelector('[data-retry-shelf-search]');
    const count = form.querySelector('[data-shelf-count]');
    const summary = form.querySelector('[data-shelf-summary]');
    const skip = form.querySelector('[data-shelf-skip]');
    const summaryLabel = form.querySelector('[data-shelf-summary-label]');
    const summaryItems = form.querySelector('[data-shelf-summary-items]');
    const clearSelection = form.querySelector('[data-clear-shelf-selection]');
    const selectionStatus = form.querySelector('[data-shelf-selection-status]');
    const errors = form.querySelector('[data-shelf-errors]');
    const fields = form.querySelector('[data-shelf-fields]');
    const submit = form.querySelector('[data-open-shelf-count]');
    const submitLabel = form.querySelector('[data-open-shelf-label]');
    const limit = Number(form.dataset.shelfLimit);

    const selected = new Map();
    const shelfFromOption = (option) => ({
        id: option.value,
        code: option.dataset.code,
        unavailable: option.hasAttribute('data-unavailable'),
    });

    // Keep the original field order so enhancing the form does not mark it as edited.
    form.querySelectorAll('[data-shelf-option]').forEach((option) => {
        if (option.checked) selected.set(option.value, shelfFromOption(option));
        option.removeAttribute('name');
    });
    form.querySelector('[data-restored-shelves]')?.remove();

    let submitting = false;
    let selectionMessage = '';

    const showSelectionMessage = (message) => {
        selectionStatus.textContent = message;
        selectionStatus.hidden = message === '';
    };

    const updateResultOptions = () => {
        const options = Array.from(results.querySelectorAll('[data-shelf-option]'));
        options.forEach((option) => { option.checked = selected.has(option.value); });
        selectResults.hidden = options.length === 0;
        skip.hidden = options.length <= 12 || selected.size === 0;
        selectResults.textContent = options.every((option) => option.checked)
            ? 'Deseleccionar resultados'
            : 'Seleccionar resultados';
    };

    const updateSelection = () => {
        const items = [];
        const inputs = [];
        const codes = [];
        let hasUnavailable = false;

        selected.forEach(({ id, code, unavailable }) => {
            const field = document.createElement('input');
            field.type = 'hidden';
            field.name = 'repisa_ids[]';
            field.value = id;
            inputs.push(field);
            codes.push(code);
            hasUnavailable ||= unavailable;

            const item = document.createElement('li');
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'button button--ghost';
            remove.dataset.removeShelf = id;
            remove.textContent = unavailable ? code + ' · No disponible ×' : code + ' ×';
            remove.setAttribute('aria-label', 'Quitar repisa ' + code + (unavailable ? ', no disponible' : ''));
            item.append(remove);
            items.push(item);
        });

        fields.replaceChildren(...inputs);
        summaryItems.replaceChildren(...items);
        summary.hidden = selected.size === 0;
        if (summary.hidden) summary.open = false;
        summaryLabel.textContent = 'Ver selección · ' + codes.slice(0, 3).join(', ')
            + (codes.length > 3 ? ' +' + (codes.length - 3) : '');
        clearSelection.hidden = selected.size === 0;
        count.textContent = selected.size === 1
            ? '1 repisa seleccionada'
            : selected.size + ' repisas seleccionadas';

        const unavailableMessage = hasUnavailable && errors.hidden ? 'Quita las repisas que ya no están disponibles.' : '';
        showSelectionMessage(selectionMessage || unavailableMessage);
        submit.disabled = submitting || !errors.hidden || hasUnavailable || selected.size === 0 || selected.size > limit;
        submitLabel.textContent = selected.size === 0 ? 'Abrir conteo'
            : 'Abrir conteo de ' + selected.size + (selected.size === 1 ? ' repisa' : ' repisas');

        updateResultOptions();
    };

    const changeSelection = (message = '') => {
        errors.hidden = true;
        selectionMessage = message;
        updateSelection();
    };

    results.addEventListener('change', (event) => {
        const option = event.target;
        if (!option.matches('[data-shelf-option]')) return;

        if (!option.checked) {
            selected.delete(option.value);
        } else if (selected.size < limit) {
            selected.set(option.value, shelfFromOption(option));
        } else {
            option.checked = false;
            showSelectionMessage('Puedes seleccionar hasta ' + limit + ' repisas.');
            return;
        }

        changeSelection();
    });

    selectResults.addEventListener('click', () => {
        const options = Array.from(results.querySelectorAll('[data-shelf-option]'));
        if (options.every((option) => selected.has(option.value))) {
            options.forEach((option) => selected.delete(option.value));
            changeSelection();
            return;
        }

        const remaining = options.filter((option) => !selected.has(option.value));
        const available = Math.max(0, limit - selected.size);
        remaining.slice(0, available).forEach((option) => selected.set(option.value, shelfFromOption(option)));
        const message = remaining.length > available
            ? 'La selección admite hasta ' + limit + ' repisas. Se excluyeron ' + (remaining.length - available)
                + ' de los resultados visibles.'
            : '';
        if (available > 0) {
            changeSelection(message);
        } else {
            showSelectionMessage(message);
        }
    });

    summaryItems.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-shelf]');
        if (!button) return;

        const index = Array.from(summaryItems.children).indexOf(button.parentElement);
        selected.delete(button.dataset.removeShelf);
        changeSelection();
        const buttons = summaryItems.querySelectorAll('button');
        (buttons[Math.min(index, buttons.length - 1)] ?? search).focus();
    });

    clearSelection.addEventListener('click', () => {
        selected.clear();
        changeSelection();
        search.focus();
    });

    const createOption = (shelf) => {
        const card = document.createElement('label');
        card.className = 'periodic-shelf-picker__item';

        const option = document.createElement('input');
        option.type = 'checkbox';
        option.value = String(shelf.id);
        option.dataset.shelfOption = '';
        option.dataset.code = shelf.codigo;

        const body = document.createElement('span');
        const code = document.createElement('strong');
        code.textContent = shelf.codigo;
        body.append(code);
        if (shelf.descripcion) {
            const description = document.createElement('small');
            description.textContent = shelf.descripcion;
            body.append(description);
        }
        card.append(option, body);
        return card;
    };

    const normalizedPrefix = () => search.value.trim().replace(/\s+/g, ' ');
    let currentPrefix = normalizedPrefix();
    let version = 0;
    let timer;
    let controller;

    const lookup = async (prefix, requestVersion) => {
        controller = new AbortController();
        const url = new URL(search.form.action);
        url.searchParams.set('prefijo', prefix);

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('No se pudo consultar el catálogo');

            const { repisas, total } = await response.json();
            if (requestVersion !== version) return;

            results.replaceChildren(...repisas.map(createOption));
            results.hidden = repisas.length === 0;
            updateResultOptions();
            searchStatus.textContent = total === 0 ? 'No hay repisas activas con ese código.'
                : total > repisas.length ? total + ' coincidencias. Se muestran las primeras ' + repisas.length
                    + '. Seleccionar resultados solo incluye las repisas visibles.'
                : total === 1 ? '1 coincidencia' : total + ' coincidencias';
        } catch (error) {
            if (requestVersion !== version || error.name === 'AbortError') return;
            searchStatus.textContent = 'No se pudo consultar las repisas. Intenta de nuevo.';
            retry.hidden = false;
        } finally {
            if (requestVersion === version) results.removeAttribute('aria-busy');
        }
    };

    const queueLookup = (immediate = false) => {
        const prefix = normalizedPrefix();
        clearSearch.hidden = search.value.length === 0;
        if (prefix === currentPrefix && !immediate) return;

        currentPrefix = prefix;
        const requestVersion = ++version;
        clearTimeout(timer);
        controller?.abort();

        const url = new URL(window.location.href);
        url.searchParams.delete('repisa_ids[]');
        if (prefix) url.searchParams.set('prefijo', prefix);
        else url.searchParams.delete('prefijo');
        window.history.replaceState(window.history.state, '', url);

        results.replaceChildren();
        results.hidden = true;
        retry.hidden = true;
        updateResultOptions();
        if (!prefix) {
            results.removeAttribute('aria-busy');
            searchStatus.textContent = 'Busca un código para ver las repisas activas.';
            return;
        }

        results.setAttribute('aria-busy', 'true');
        searchStatus.textContent = 'Buscando repisas…';
        if (immediate) lookup(prefix, requestVersion);
        else timer = setTimeout(() => lookup(prefix, requestVersion), 250);
    };

    search.addEventListener('input', () => queueLookup());
    search.form.addEventListener('submit', (event) => {
        event.preventDefault();
        queueLookup(true);
    });
    retry.addEventListener('click', () => {
        search.focus();
        queueLookup(true);
    });
    clearSearch.addEventListener('click', () => {
        search.value = '';
        queueLookup();
        search.focus();
    });
    clearSearch.hidden = search.value.length === 0;

    form.addEventListener('submit', (event) => {
        if (submit.disabled || submitting) {
            event.preventDefault();
            return;
        }

        submitting = true;
        submit.disabled = true;
        submitLabel.textContent = 'Abriendo conteo…';
        form.setAttribute('aria-busy', 'true');
    });

    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        submitting = false;
        form.removeAttribute('aria-busy');
        updateSelection();
    });

    form.querySelector('[data-shelf-cancel-fallback]').hidden = true;
    form.querySelector('[data-cancel-form]').hidden = false;
    updateSelection();
})();
