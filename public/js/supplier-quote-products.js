(() => {
    'use strict';

    const PAGE_SIZE = 15;

    const normalize = (value) => String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('es')
        .trim();

    const paginate = (rows, query, requestedPage = 1) => {
        const term = normalize(query);
        const matches = term
            ? rows.filter((row) => row.searchText.includes(term))
            : rows;
        const lastPage = Math.max(1, Math.ceil(matches.length / PAGE_SIZE));
        const page = Math.min(lastPage, Math.max(1, Number(requestedPage) || 1));
        const offset = (page - 1) * PAGE_SIZE;

        return {
            matches,
            visible: matches.slice(offset, offset + PAGE_SIZE),
            page,
            lastPage,
            first: matches.length ? offset + 1 : 0,
            last: Math.min(offset + PAGE_SIZE, matches.length),
        };
    };

    const pageWindow = (page, lastPage) => {
        const shown = [];
        for (let number = 1; number <= lastPage; number += 1) {
            if (number <= 2 || number >= lastPage - 1 || Math.abs(number - page) <= 1) {
                if (shown.length && number > shown[shown.length - 1] + 1) {
                    shown.push(null);
                }
                shown.push(number);
            }
        }
        return shown;
    };

    // La lógica de selección se prueba sin una dependencia de DOM adicional.
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { PAGE_SIZE, normalize, paginate, pageWindow };
    }

    if (typeof document === 'undefined') {
        return;
    }

    const init = () => {
        document.querySelectorAll('.supplier-quote-detail-lines').forEach((section) => {
            if (section.dataset.productNavigationReady === 'true') {
                return;
            }

            const tools = section.querySelector('[data-supplier-quote-product-tools]');
            const search = section.querySelector('[data-supplier-quote-product-search]');
            const clear = section.querySelector('[data-supplier-quote-product-clear]');
            const footer = section.querySelector('[data-supplier-quote-product-footer]');
            const range = section.querySelector('[data-supplier-quote-product-range]');
            const empty = section.querySelector('[data-supplier-quote-product-empty]');
            const pages = section.querySelector('[data-supplier-quote-product-pages]');
            const numbers = section.querySelector('[data-supplier-quote-product-numbers]');
            const previous = section.querySelector('[data-supplier-quote-product-prev]');
            const next = section.querySelector('[data-supplier-quote-product-next]');
            const elements = Array.from(section.querySelectorAll('[data-supplier-quote-product-row]'));

            if (!elements.length || !tools || !search || !clear || !footer || !range
                || !empty || !pages || !numbers || !previous || !next) {
                return;
            }

            const rows = elements.map((element) => ({
                element,
                searchText: normalize([
                    element.querySelector('.supplier-quote-detail-table__product strong')?.textContent,
                    element.querySelector('.supplier-quote-detail-table__product span')?.textContent,
                ].join(' ')),
            }));

            let currentPage = 1;
            let currentResult;

            const makePageButton = (number) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'pagination__button';
                button.textContent = String(number);
                button.setAttribute('aria-label', `Página ${number} de ${currentResult.lastPage}`);
                if (number === currentPage) {
                    button.setAttribute('aria-current', 'page');
                }
                button.addEventListener('click', () => goToPage(number, button));
                return button;
            };

            const render = () => {
                currentResult = paginate(rows, search.value, currentPage);
                currentPage = currentResult.page;
                const visible = new Set(currentResult.visible);
                rows.forEach((row) => { row.element.hidden = !visible.has(row); });

                range.textContent = currentResult.matches.length
                    ? `Mostrando ${currentResult.first}–${currentResult.last} de ${currentResult.matches.length} ${currentResult.matches.length === 1 ? 'producto' : 'productos'}`
                    : 'Mostrando 0 de 0 productos';
                empty.hidden = currentResult.matches.length !== 0;
                clear.hidden = search.value.length === 0;
                pages.hidden = currentResult.lastPage <= 1;
                previous.disabled = currentPage === 1;
                next.disabled = currentPage === currentResult.lastPage;

                const items = pageWindow(currentPage, currentResult.lastPage)
                    .map((number) => {
                        if (number !== null) {
                            return makePageButton(number);
                        }
                        const separator = document.createElement('span');
                        separator.textContent = '…';
                        separator.setAttribute('aria-hidden', 'true');
                        return separator;
                    });
                numbers.replaceChildren(...items);
            };

            const goToPage = (page, sourceButton = null) => {
                if (page === currentPage) {
                    return;
                }
                const retainFocus = sourceButton && document.activeElement === sourceButton;
                currentPage = page;
                render();
                if (retainFocus) {
                    numbers.querySelector('[aria-current="page"]')?.focus({ preventScroll: true });
                }
                section.scrollIntoView({ block: 'start', inline: 'nearest', behavior: 'auto' });
            };

            search.addEventListener('input', () => {
                currentPage = 1;
                render();
            });
            clear.addEventListener('click', () => {
                search.value = '';
                currentPage = 1;
                render();
                search.focus();
            });
            previous.addEventListener('click', () => goToPage(currentPage - 1));
            next.addEventListener('click', () => goToPage(currentPage + 1));

            render();
            tools.hidden = false;
            footer.hidden = false;
            section.dataset.productNavigationReady = 'true';
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
