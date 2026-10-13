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

    const initDiscard = () => {
        const modal = document.querySelector?.('[data-ui-modal="operation-form-discard"]');
        const dialog = modal?.querySelector('[role="dialog"]');
        const cancel = modal?.querySelector('[data-ui-modal-cancel]');
        const confirm = modal?.querySelector('[data-ui-modal-confirm]');
        if (!modal || !dialog || !cancel || !confirm) return;

        const snapshots = new WeakMap();
        const serialize = (form) => new URLSearchParams(new FormData(form)).toString();
        let pending = null;

        const close = (target) => {
            modal.hidden = true;
            document.body.classList.remove('modal-open');
            const focusTarget = target || pending?.trigger;
            pending = null;
            focusTarget?.focus({ preventScroll: true });
        };

        const open = (form, collapse, trigger) => {
            pending = { form, collapse, trigger };
            modal.hidden = false;
            document.body.classList.add('modal-open');
            window.requestAnimationFrame(() => cancel.focus());
        };

        const discardForms = Array.from(document.querySelectorAll('[data-operation-discard-form]'));
        discardForms.forEach((form) => {
            const details = form.closest('details');
            const panel = form.closest('[data-operation-adjust-row]');
            const summary = details?.querySelector(':scope > summary')
                || (panel && document.getElementById(panel.id.replace('operation-adjust-row-', 'operation-adjust-trigger-')));
            if (!summary) return;
            const collapse = () => {
                if (details) details.open = false;
                else {
                    panel.hidden = true;
                    summary.setAttribute('aria-expanded', 'false');
                }
            };
            snapshots.set(form, serialize(form));

            form.querySelector('[data-operation-discard-button]')?.addEventListener('click', (event) => {
                if (serialize(form) !== snapshots.get(form)) {
                    open(form, collapse, event.currentTarget);
                } else {
                    collapse();
                    summary.focus({ preventScroll: true });
                }
            });

            summary.addEventListener('click', (event) => {
                if ((details?.open || (panel && !panel.hidden)) && serialize(form) !== snapshots.get(form)) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    open(form, collapse, summary);
                } else if (panel && panel.hidden) {
                    const openForm = discardForms.find((other) => {
                        const otherPanel = other.closest('[data-operation-adjust-row]');
                        return other !== form && otherPanel && !otherPanel.hidden && serialize(other) !== snapshots.get(other);
                    });
                    if (openForm) {
                        event.preventDefault();
                        event.stopImmediatePropagation();
                        const otherPanel = openForm.closest('[data-operation-adjust-row]');
                        const otherTrigger = document.getElementById(otherPanel.id.replace('operation-adjust-row-', 'operation-adjust-trigger-'));
                        open(openForm, () => {
                            otherPanel.hidden = true;
                            otherTrigger?.setAttribute('aria-expanded', 'false');
                        }, summary);
                    }
                }
            });
        });

        cancel.addEventListener('click', () => close());
        confirm.addEventListener('click', () => {
            if (!pending) return;
            const { form, collapse } = pending;
            const summary = form.closest('details')?.querySelector(':scope > summary')
                || document.getElementById(form.closest('[data-operation-adjust-row]')?.id.replace('operation-adjust-row-', 'operation-adjust-trigger-'));
            form.reset();
            collapse();
            close(summary);
        });
        modal.addEventListener('click', (event) => {
            if (event.target === modal) close();
        });
        dialog.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') return;
            const focusable = [cancel, confirm].filter((button) => !button.disabled);
            if (event.shiftKey && document.activeElement === focusable[0]) {
                event.preventDefault();
                focusable[focusable.length - 1].focus();
            } else if (!event.shiftKey && document.activeElement === focusable[focusable.length - 1]) {
                event.preventDefault();
                focusable[0].focus();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                event.preventDefault();
                close();
            }
        });
    };

    const initCostAnnul = () => {
        const modal = document.querySelector?.('[data-ui-modal="operation-cost-annul"]');
        const dialog = modal?.querySelector('[role="dialog"]');
        const cancel = modal?.querySelector('[data-ui-modal-cancel]');
        const confirm = modal?.querySelector('[data-ui-modal-confirm]');
        if (!modal || !dialog || !cancel || !confirm) return;
        let pending = null;

        const close = (restoreFocus = true) => {
            modal.hidden = true;
            document.body.classList.remove('modal-open');
            if (restoreFocus) pending?.button?.focus({ preventScroll: true });
            pending = null;
        };

        document.querySelectorAll('[data-operation-annul]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.operationAnnulBypass === 'true') {
                    delete form.dataset.operationAnnulBypass;
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                pending = { form, button: event.submitter || form.querySelector('[type="submit"]') };
                modal.hidden = false;
                document.body.classList.add('modal-open');
                window.requestAnimationFrame(() => cancel.focus());
            }, { capture: true });
        });

        cancel.addEventListener('click', () => close());
        confirm.addEventListener('click', () => {
            if (!pending) return;
            const { form, button } = pending;
            close(false);
            if (!form.checkValidity()) {
                form.reportValidity();
                button?.focus({ preventScroll: true });
                return;
            }
            form.dataset.operationAnnulBypass = 'true';
            form.requestSubmit(button);
        });
        modal.addEventListener('click', (event) => {
            if (event.target === modal) close();
        });
        dialog.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') return;
            if (event.shiftKey && document.activeElement === cancel) {
                event.preventDefault();
                confirm.focus();
            } else if (!event.shiftKey && document.activeElement === confirm) {
                event.preventDefault();
                cancel.focus();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                event.preventDefault();
                close();
            }
        });
    };

    const init = () => {
        initDiscard();
        initCostAnnul();
        document.querySelectorAll('[data-operation-line-list]').forEach((section) => {
            if (section.dataset.operationLineReady === 'true') return;
            const tools = section.querySelector('[data-operation-line-tools]');
            const search = section.querySelector('[data-operation-line-search]');
            const state = section.querySelector('[data-operation-line-state]');
            const shortage = section.querySelector('[data-operation-line-shortage]');
            const clear = section.querySelector('[data-operation-line-clear]');
            const footer = section.querySelector('[data-operation-line-footer]');
            const range = section.querySelector('[data-operation-line-range]');
            const inlineRange = section.querySelector('[data-operation-line-range-inline]');
            const variationToggle = section.querySelector('[data-operation-variations-toggle]');
            const empty = section.querySelector('[data-operation-line-empty]');
            const pages = section.querySelector('[data-operation-line-pages]');
            const numbers = section.querySelector('[data-operation-line-numbers]');
            const previous = section.querySelector('[data-operation-line-prev]');
            const next = section.querySelector('[data-operation-line-next]');
            const tableWrap = section.querySelector('.required-materials-table-wrap, .reservation-table-wrap');
            const elements = Array.from(section.querySelectorAll('[data-operation-line-row]'));

            if (!tools || !search || !state || !clear || !footer || !range || !empty
                || !pages || !numbers || !previous || !next || !tableWrap || !elements.length) return;

            const rows = elements.map((element) => {
                const panel = element.nextElementSibling?.matches?.('[data-operation-adjust-row]')
                    ? element.nextElementSibling : null;
                return {
                    element,
                    panel,
                    toggle: element.querySelector?.('[data-operation-adjust-toggle]'),
                    searchText: normalize(element.dataset.operationLineSearchText),
                    state: element.dataset.operationLineState,
                    shortage: element.dataset.operationLineShortage === '1',
                };
            });
            const isLong = rows.length > PAGE_SIZE;
            const errorIndex = rows.findIndex((row) => row.panel && !row.panel.hidden);
            let currentPage = errorIndex < 0 ? 1 : Math.floor(errorIndex / PAGE_SIZE) + 1;
            let result;

            const variationColumns = Array.from(section.querySelectorAll('[data-operation-variation-column]'));
            if (variationToggle) {
                variationColumns.forEach((column) => { column.hidden = true; });
                rows.forEach((row) => { if (row.panel) row.panel.firstElementChild.colSpan = 6; });
                variationToggle.hidden = false;
                variationToggle.addEventListener('click', () => {
                    const showing = variationToggle.getAttribute('aria-pressed') !== 'true';
                    variationToggle.setAttribute('aria-pressed', String(showing));
                    variationToggle.textContent = showing ? 'Ocultar detalle de variaciones' : 'Ver detalle de variaciones';
                    variationColumns.forEach((column) => { column.hidden = !showing; });
                    rows.forEach((row) => { if (row.panel) row.panel.firstElementChild.colSpan = showing ? 8 : 6; });
                });
            }

            rows.forEach((row) => row.toggle?.addEventListener('click', () => {
                const opening = row.panel.hidden;
                rows.forEach((other) => {
                    if (!other.panel) return;
                    other.panel.hidden = true;
                    other.toggle?.setAttribute('aria-expanded', 'false');
                });
                if (opening) {
                    row.panel.hidden = false;
                    row.toggle.setAttribute('aria-expanded', 'true');
                }
            }));

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
                rows.forEach((row) => {
                    row.element.hidden = !visible.has(row);
                    if (row.panel) row.panel.hidden = !visible.has(row) || row.toggle?.getAttribute('aria-expanded') !== 'true';
                });
                const filtered = normalize(search.value) || state.value || shortage?.checked;
                range.textContent = result.matches.length
                    ? `Mostrando ${result.first}–${result.last} de ${result.matches.length} productos`
                    : 'Mostrando 0 de 0 productos';
                if (filtered) range.textContent += ` · ${result.matches.length} coincidencias de ${rows.length}`;
                if (inlineRange) inlineRange.textContent = range.textContent;
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
                if (retainFocus) numbers.querySelector('[aria-current="page"]')?.focus({ preventScroll: true });
                section.scrollIntoView({ block: 'start', inline: 'nearest', behavior: 'auto' });
            };

            const updateFilters = () => { currentPage = 1; render(); };
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
            tools.hidden = !isLong && !shortage && !inlineRange;
            footer.hidden = !isLong && !shortage && !inlineRange;
            section.dataset.operationLineReady = 'true';
        });

        document.querySelectorAll('[data-operation-comparison-list]').forEach((list) => {
            const elements = Array.from(list.querySelectorAll('[data-operation-comparison-row]'));
            const footer = list.parentElement?.querySelector('[data-operation-comparison-footer]');
            if (elements.length <= PAGE_SIZE || !footer) return;
            const groups = Array.from(list.querySelectorAll('[data-operation-comparison-group]'));
            const range = footer.querySelector('[data-operation-comparison-range]');
            const numbers = footer.querySelector('[data-operation-comparison-numbers]');
            const previous = footer.querySelector('[data-operation-comparison-prev]');
            const next = footer.querySelector('[data-operation-comparison-next]');
            if (!range || !numbers || !previous || !next) return;
            const rows = elements.map((element) => ({ element, area: element.dataset.operationComparisonArea, searchText: '', state: '' }));
            let currentPage = 1;
            let result;

            const render = () => {
                result = paginate(rows, { page: currentPage });
                currentPage = result.page;
                const visible = new Set(result.visible);
                const areas = new Set(result.visible.map((row) => row.area));
                rows.forEach((row) => { row.element.hidden = !visible.has(row); });
                groups.forEach((group) => { group.hidden = !areas.has(group.dataset.operationComparisonGroup); });
                range.textContent = `Mostrando ${result.first}–${result.last} de ${rows.length} productos`;
                previous.disabled = currentPage === 1;
                next.disabled = currentPage === result.lastPage;
                numbers.replaceChildren(...pageWindow(currentPage, result.lastPage).map((number) => {
                    if (number === null) {
                        const separator = document.createElement('span');
                        separator.textContent = '…';
                        separator.setAttribute('aria-hidden', 'true');
                        return separator;
                    }
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'pagination__button';
                    button.textContent = String(number);
                    button.setAttribute('aria-label', `Página ${number} de ${result.lastPage}`);
                    if (number === currentPage) button.setAttribute('aria-current', 'page');
                    button.addEventListener('click', () => changePage(number, button));
                    return button;
                }));
            };
            const changePage = (page, sourceButton = null) => {
                if (page === currentPage) return;
                const retainFocus = sourceButton && document.activeElement === sourceButton;
                currentPage = page;
                render();
                if (retainFocus) numbers.querySelector('[aria-current="page"]')?.focus({ preventScroll: true });
                list.parentElement.scrollIntoView({ block: 'start', inline: 'nearest', behavior: 'auto' });
            };
            previous.addEventListener('click', () => changePage(currentPage - 1));
            next.addEventListener('click', () => changePage(currentPage + 1));
            render();
            footer.hidden = false;
        });
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
