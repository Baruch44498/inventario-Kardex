document.addEventListener('DOMContentLoaded', () => {
    const normalize = (value) => (value || '').replace(/\s+/g, ' ').trim();

    // Reutiliza el filtro real de cada listado, incluidos sus otros filtros y permisos.
    // Los combobox y buscadores de productos ya tienen sugerencias propias.
    document.querySelectorAll('form[method="GET"] input[type="search"][name="q"]').forEach((input, index) => {
        if (input.closest('[data-remote-combobox]')) return;

        const form = input.form;
        const anchor = input.closest('.input-with-icon') || input.parentElement;
        if (!form || !anchor) return;

        anchor.classList.add('search-suggest-anchor');
        anchor.closest('.filter-panel')?.classList.add('search-suggest-panel');

        const list = document.createElement('div');
        const listId = `search-suggest-results-${index}`;
        list.id = listId;
        list.className = 'search-suggest-results';
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', 'Coincidencias de la búsqueda');
        list.hidden = true;
        anchor.appendChild(list);

        input.autocomplete = 'off';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-controls', listId);
        input.setAttribute('aria-expanded', 'false');

        let timer;
        let controller;
        let requestId = 0;
        let active = -1;

        const options = () => Array.from(list.querySelectorAll('[role="option"]'));

        const close = () => {
            list.hidden = true;
            active = -1;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
        };

        const open = () => {
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };

        const status = (message) => {
            list.replaceChildren();
            active = -1;
            const notice = document.createElement('p');
            notice.className = 'search-suggest-status';
            notice.textContent = message;
            list.appendChild(notice);
            open();
        };

        const setActive = (position) => {
            const items = options();
            if (!items.length) return;
            active = (position + items.length) % items.length;
            items.forEach((item, itemIndex) => {
                const selected = itemIndex === active;
                item.classList.toggle('is-active', selected);
                item.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
            input.setAttribute('aria-activedescendant', items[active].id);
            items[active].scrollIntoView({ block: 'nearest' });
        };

        const highlight = (element, value, term) => {
            const position = value.toLocaleLowerCase().indexOf(term.toLocaleLowerCase());
            if (position < 0) {
                element.textContent = value;
                return;
            }
            element.append(document.createTextNode(value.slice(0, position)));
            const mark = document.createElement('mark');
            mark.textContent = value.slice(position, position + term.length);
            element.append(mark, document.createTextNode(value.slice(position + term.length)));
        };

        const resultsFromPage = (html) => {
            const page = new DOMParser().parseFromString(html, 'text/html');
            const tableSelector = input.dataset.suggestTable || 'table.data-table';
            const rows = page.querySelectorAll(`${tableSelector} tbody > tr:not([data-table-details-row])`);
            const found = [];
            const seen = new Set();

            for (const row of rows) {
                if (row.closest('.inventory-tools-panel') || row.hidden) continue;

                const cells = Array.from(row.children).filter((cell) => cell.tagName === 'TD');
                const preferredCell = input.dataset.suggestCell !== undefined
                    ? cells[Number(input.dataset.suggestCell)]
                    : null;
                const link = preferredCell ? null : row.querySelector('a.table-primary-link');
                const title = normalize(preferredCell?.querySelector('strong')?.textContent || preferredCell?.textContent)
                    || normalize(link?.textContent)
                    || cells.map((cell) => normalize(cell.querySelector('strong')?.textContent || cell.textContent))
                        .find(Boolean);
                if (!title) continue;

                const details = cells.map((cell) => normalize(cell.textContent))
                    .map((value) => value.startsWith(title) ? normalize(value.slice(title.length)) : value)
                    .filter(Boolean)
                    .slice(0, 3)
                    .join(' · ');
                const searchValue = normalize(row.querySelector('[data-suggest-value]')?.dataset.suggestValue) || title;
                if (seen.has(searchValue)) continue;
                seen.add(searchValue);
                found.push({ title, details: details.slice(0, 140), searchValue });
                if (found.length === 6) break;
            }

            return found;
        };

        const render = (items, term) => {
            if (!items.length) {
                status('No hay coincidencias con los filtros actuales.');
                return;
            }

            list.replaceChildren();
            active = -1;
            items.forEach((item, itemIndex) => {
                const option = document.createElement('button');
                const title = document.createElement('strong');
                option.id = `${listId}-option-${itemIndex}`;
                option.type = 'button';
                option.className = 'search-suggest-option';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                highlight(title, item.title, term);
                option.appendChild(title);

                if (item.details) {
                    const details = document.createElement('small');
                    highlight(details, item.details, term);
                    option.appendChild(details);
                }

                option.addEventListener('mousedown', (event) => event.preventDefault());
                option.addEventListener('click', () => {
                    input.value = item.searchValue;
                    close();
                    form.requestSubmit();
                });
                list.appendChild(option);
            });
            open();
        };

        const search = async (term, signature) => {
            const currentRequest = ++requestId;
            controller = new AbortController();
            status('Buscando coincidencias...');

            try {
                const url = new URL(form.action, window.location.origin);
                if (url.origin !== window.location.origin) return;
                for (const [key, value] of new FormData(form)) {
                    if (key !== 'page' && typeof value === 'string') url.searchParams.set(key, value);
                }
                url.searchParams.delete('page');

                const response = await fetch(url, {
                    headers: { Accept: 'text/html' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error('Error de búsqueda');

                const html = await response.text();
                const currentSignature = Array.from(new FormData(form)).map(([key, value]) => `${key}=${value}`).join('&');
                if (currentRequest === requestId && input.value.trim() === term && currentSignature === signature
                    && document.activeElement === input) {
                    render(resultsFromPage(html), term);
                }
            } catch (error) {
                if (error.name !== 'AbortError' && currentRequest === requestId && document.activeElement === input) {
                    status('No se pudieron cargar las coincidencias. Puedes usar Filtrar.');
                }
            }
        };

        const cancel = () => {
            clearTimeout(timer);
            controller?.abort();
            requestId += 1;
            close();
        };

        const schedule = () => {
            cancel();
            const term = input.value.trim();
            if (term.length < 2) {
                return;
            }
            const signature = Array.from(new FormData(form)).map(([key, value]) => `${key}=${value}`).join('&');
            timer = window.setTimeout(() => search(term, signature), 380);
        };

        input.addEventListener('input', schedule);
        input.addEventListener('focus', schedule);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                cancel();
            } else if (!list.hidden && options().length && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
                event.preventDefault();
                setActive(active + (event.key === 'ArrowDown' ? 1 : -1));
            } else if (event.key === 'Enter' && active >= 0 && !list.hidden) {
                event.preventDefault();
                options()[active]?.click();
            }
        });
        input.addEventListener('blur', () => window.setTimeout(cancel, 120));
        form.addEventListener('change', (event) => {
            if (event.target !== input && document.activeElement === input) schedule();
        });
        form.addEventListener('submit', close);
    });
});
