(() => {
    'use strict';

    const PAGE_SIZE = 15;
    const normalize = (value) => String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('es')
        .trim();

    const paginate = (rows, { query = '', state = '', shortageOnly = false, page = 1 } = {}) => {
        const term = normalize(query);
        const matches = rows.filter((row) => (
            (!term || row.searchText.includes(term))
            && (!state || row.state === state)
            && (!shortageOnly || row.shortage)
        ));
        const lastPage = Math.max(1, Math.ceil(matches.length / PAGE_SIZE));
        const currentPage = Math.min(lastPage, Math.max(1, Number(page) || 1));
        const first = (currentPage - 1) * PAGE_SIZE;

        return {
            matches,
            visible: matches.slice(first, first + PAGE_SIZE),
            page: currentPage,
            lastPage,
            first: matches.length ? first + 1 : 0,
            last: Math.min(first + PAGE_SIZE, matches.length),
        };
    };

    const pageWindow = (page, lastPage) => {
        const shown = [];
        for (let number = 1; number <= lastPage; number += 1) {
            if (number <= 2 || number >= lastPage - 1 || Math.abs(number - page) <= 1) {
                if (shown.length && number > shown[shown.length - 1] + 1) shown.push(null);
                shown.push(number);
            }
        }
        return shown;
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { PAGE_SIZE, normalize, paginate, pageWindow };
    }
    if (typeof document === 'undefined') return;

    const init = () => {
        document.querySelectorAll('[data-operation-line-list]').forEach((section) => {
            if (section.dataset.operationLineReady === 'true') return;
            const tools = section.querySelector('[data-operation-line-tools]');
            const search = section.querySelector('[data-operation-line-search]');
            const state = section.querySelector('[data-operation-line-state]');
            const shortage = section.querySelector('[data-operation-line-shortage]');
            const clear = section.querySelector('[data-operation-line-clear]');
            const footer = section.querySelector('[data-operation-line-footer]');
            const range = section.querySelector('[data-operation-line-range]');
            const empty = section.querySelector('[data-operation-line-empty]');
            const pages = section.querySelector('[data-operation-line-pages]');
            const numbers = section.querySelector('[data-operation-line-numbers]');
            const previous = section.querySelector('[data-operation-line-prev]');
            const next = section.querySelector('[data-operation-line-next]');
            const tableWrap = section.querySelector('.required-materials-table-wrap, .reservation-table-wrap');
            const elements = Array.from(section.querySelectorAll('[data-operation-line-row]'));

            if (!tools || !search || !state || !clear || !footer || !range || !empty
                || !pages || !numbers || !previous || !next || !tableWrap || !elements.length) return;

            const rows = elements.map((element) => ({
                element,
                searchText: normalize(element.dataset.operationLineSearchText),
                state: element.dataset.operationLineState,
                shortage: element.dataset.operationLineShortage === '1',
            }));
            const isLong = rows.length > PAGE_SIZE;
            tableWrap.classList.toggle('operation-line-scroll', isLong);
            let currentPage = 1;
            let result;

            const pageButton = (number) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'pagination__button';
                button.textContent = String(number);
                button.setAttribute('aria-label', `Página ${number} de ${result.lastPage}`);
                if (number === currentPage) button.setAttribute('aria-current', 'page');
                button.addEventListener('click', () => changePage(number, button));
                return button;
            };

            const render = () => {
                result = paginate(rows, {
                    query: search.value,
                    state: state.value,
                    shortageOnly: shortage?.checked === true,
                    page: currentPage,
                });
                currentPage = result.page;
                const visible = new Set(result.visible);
                rows.forEach((row) => { row.element.hidden = !visible.has(row); });
                const filtered = normalize(search.value) || state.value || shortage?.checked;
                range.textContent = result.matches.length
                    ? `Mostrando ${result.first}–${result.last} de ${result.matches.length} productos`
                    : 'Mostrando 0 de 0 productos';
                if (filtered) range.textContent += ` · ${result.matches.length} coincidencias de ${rows.length}`;
                empty.hidden = result.matches.length !== 0;
                clear.hidden = !filtered;
                pages.hidden = result.lastPage <= 1;
                previous.disabled = currentPage === 1;
                next.disabled = currentPage === result.lastPage;

                numbers.replaceChildren(...pageWindow(currentPage, result.lastPage).map((number) => {
                    if (number !== null) return pageButton(number);
                    const separator = document.createElement('span');
                    separator.textContent = '…';
                    separator.setAttribute('aria-hidden', 'true');
                    return separator;
                }));
            };

            const changePage = (page, sourceButton = null) => {
                if (page === currentPage) return;
                const retainFocus = sourceButton && document.activeElement === sourceButton;
                currentPage = page;
                render();
                tableWrap.scrollTop = 0;
                if (retainFocus) numbers.querySelector('[aria-current="page"]')?.focus({ preventScroll: true });
                section.scrollIntoView({ block: 'start', inline: 'nearest', behavior: 'auto' });
            };

            const updateFilters = () => { currentPage = 1; render(); tableWrap.scrollTop = 0; };
            search.addEventListener('input', updateFilters);
            state.addEventListener('change', updateFilters);
            shortage?.addEventListener('change', updateFilters);
            clear.addEventListener('click', () => {
                search.value = '';
                state.value = '';
                if (shortage) shortage.checked = false;
                updateFilters();
                search.focus();
            });
            previous.addEventListener('click', () => changePage(currentPage - 1));
            next.addEventListener('click', () => changePage(currentPage + 1));

            render();
            tools.hidden = !isLong && !shortage;
            footer.hidden = !isLong && !shortage;
            section.dataset.operationLineReady = 'true';
        });
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
