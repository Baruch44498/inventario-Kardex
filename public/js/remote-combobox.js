document.addEventListener('DOMContentLoaded', () => {
    const boxes = Array.from(document.querySelectorAll('[data-remote-combobox]'));
    const states = new WeakMap();
    let sequence = 0;

    const close = (box) => {
        const results = box.querySelector('[data-remote-combobox-results]');
        const search = box.querySelector('[data-remote-combobox-search]');
        const state = states.get(box);

        if (state) state.activeIndex = -1;
        if (results) results.hidden = true;
        search?.setAttribute('aria-expanded', 'false');
    };

    const setValidity = (box) => {
        const search = box.querySelector('[data-remote-combobox-search]');
        const value = box.querySelector('[data-remote-combobox-value]');
        if (!search || !value) return;

        const hasTextWithoutSelection = search.value.trim() !== '' && value.value === '';
        const missingRequired = box.dataset.required === 'true' && value.value === '';

        search.setCustomValidity(
            hasTextWithoutSelection
                ? 'Selecciona una opción de los resultados.'
                : missingRequired
                    ? 'Selecciona una opción.'
                    : ''
        );
    };

    const clear = (box, focus = false) => {
        const search = box.querySelector('[data-remote-combobox-search]');
        const value = box.querySelector('[data-remote-combobox-value]');
        const clearButton = box.querySelector('[data-remote-combobox-clear]');

        if (search) {
            search.value = '';
            search.dataset.selectedLabel = '';
        }
        if (value) {
            value.value = '';
            value.dispatchEvent(new Event('input', { bubbles: true }));
            value.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (clearButton) clearButton.hidden = true;
        close(box);
        setValidity(box);
        if (focus) search?.focus();
    };

    const select = (box, item) => {
        const search = box.querySelector('[data-remote-combobox-search]');
        const value = box.querySelector('[data-remote-combobox-value]');
        const clearButton = box.querySelector('[data-remote-combobox-clear]');
        if (!search || !value) return;

        search.value = item.label;
        search.dataset.selectedLabel = item.label;
        value.value = String(item.id);
        if (clearButton) clearButton.hidden = false;
        close(box);
        setValidity(box);

        value.dispatchEvent(new Event('input', { bubbles: true }));
        value.dispatchEvent(new Event('change', { bubbles: true }));
        box.dispatchEvent(new CustomEvent('remote-combobox:selected', {
            bubbles: true,
            detail: item,
        }));
    };

    const paintActive = (box) => {
        const state = states.get(box);
        const options = Array.from(box.querySelectorAll('[data-remote-combobox-option]'));

        options.forEach((option, index) => {
            const active = index === state?.activeIndex;
            option.classList.toggle('is-active', active);
            option.setAttribute('aria-selected', active ? 'true' : 'false');
            if (active) option.scrollIntoView({ block: 'nearest' });
        });
    };

    const showStatus = (box, text) => {
        const results = box.querySelector('[data-remote-combobox-results]');
        const search = box.querySelector('[data-remote-combobox-search]');
        if (!results || !search) return;

        results.replaceChildren();
        const status = document.createElement('div');
        status.className = 'remote-combobox__status';
        status.textContent = text;
        results.appendChild(status);
        results.hidden = false;
        search.setAttribute('aria-expanded', 'true');
    };

    const render = (box, items) => {
        const results = box.querySelector('[data-remote-combobox-results]');
        const search = box.querySelector('[data-remote-combobox-search]');
        const state = states.get(box);
        if (!results || !search || !state) return;

        results.replaceChildren();
        state.activeIndex = -1;

        if (items.length === 0) {
            showStatus(box, box.dataset.emptyText || 'No se encontraron coincidencias.');
            return;
        }

        items.forEach((item, index) => {
            const option = document.createElement('button');
            const label = document.createElement('strong');

            option.type = 'button';
            option.className = 'remote-combobox__option';
            option.dataset.remoteComboboxOption = String(index);
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            label.textContent = item.label;
            option.appendChild(label);

            if (item.description) {
                const description = document.createElement('small');
                description.textContent = item.description;
                option.appendChild(description);
            }

            option.addEventListener('mousedown', (event) => event.preventDefault());
            option.addEventListener('click', () => select(box, item));
            results.appendChild(option);
        });

        results.hidden = false;
        search.setAttribute('aria-expanded', 'true');
    };

    const searchItems = async (box) => {
        const search = box.querySelector('[data-remote-combobox-search]');
        const state = states.get(box);
        if (!search || !state) return;

        const term = search.value.trim();
        state.controller?.abort();
        state.controller = new AbortController();
        showStatus(box, 'Buscando...');

        try {
            const url = new URL(box.dataset.searchUrl, window.location.origin);
            if (term !== '') url.searchParams.set('q', term);

            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: state.controller.signal,
            });

            if (!response.ok) throw new Error('No se pudo consultar el catálogo.');

            const payload = await response.json();
            if (search.value.trim() === term) render(box, payload.items || []);
        } catch (error) {
            if (error.name !== 'AbortError') {
                showStatus(box, 'No se pudo buscar. Intenta nuevamente.');
            }
        }
    };

    const initialize = (box) => {
        if (states.has(box)) return;

        const search = box.querySelector('[data-remote-combobox-search]');
        const value = box.querySelector('[data-remote-combobox-value]');
        const results = box.querySelector('[data-remote-combobox-results]');
        const clearButton = box.querySelector('[data-remote-combobox-clear]');
        if (!search || !value || !results) return;

        sequence += 1;
        results.id = `remote-combobox-results-${sequence}`;
        search.setAttribute('aria-controls', results.id);
        search.dataset.selectedLabel = value.value ? search.value : '';
        states.set(box, { timer: null, controller: null, activeIndex: -1 });
        setValidity(box);

        search.addEventListener('input', () => {
            const state = states.get(box);
            if (search.value !== search.dataset.selectedLabel) {
                value.value = '';
                if (clearButton) clearButton.hidden = search.value === '';
                value.dispatchEvent(new Event('change', { bubbles: true }));
            }

            setValidity(box);
            window.clearTimeout(state.timer);
            state.timer = window.setTimeout(() => searchItems(box), 250);
        });

        search.addEventListener('focus', () => searchItems(box));
        search.addEventListener('blur', () => window.setTimeout(() => close(box), 160));
        search.addEventListener('keydown', (event) => {
            const state = states.get(box);
            const options = Array.from(box.querySelectorAll('[data-remote-combobox-option]'));

            if (event.key === 'ArrowDown' && options.length > 0) {
                event.preventDefault();
                state.activeIndex = Math.min(state.activeIndex + 1, options.length - 1);
                paintActive(box);
            } else if (event.key === 'ArrowUp' && options.length > 0) {
                event.preventDefault();
                state.activeIndex = Math.max(state.activeIndex - 1, 0);
                paintActive(box);
            } else if (event.key === 'Enter' && state.activeIndex >= 0) {
                event.preventDefault();
                options[state.activeIndex]?.click();
            } else if (event.key === 'Escape') {
                close(box);
            }
        });

        clearButton?.addEventListener('click', () => clear(box, true));
    };

    boxes.forEach(initialize);

    window.HidroilRemoteCombobox = {
        initialize,
        clear,
    };

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const formBoxes = Array.from(form.querySelectorAll('[data-remote-combobox]'))
                .filter((box) => !box.closest('[hidden]'));
            formBoxes.forEach(setValidity);

            const invalid = formBoxes
                .map((box) => box.querySelector('[data-remote-combobox-search]'))
                .find((search) => search && !search.checkValidity());

            if (invalid) {
                event.preventDefault();
                invalid.reportValidity();
                invalid.focus();
            }
        });
    });
});
