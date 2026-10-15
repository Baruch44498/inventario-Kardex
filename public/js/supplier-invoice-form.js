(() => {
    'use strict';

    const initSupplierInvoiceForm = () => {
        const form = document.querySelector('[data-supplier-invoice-form]');

        if (!form) {
            return;
        }

        const quantityInputs = Array.from(form.querySelectorAll('[data-invoice-quantity]'));
        const partialToggle = form.querySelector('[data-partial-billing-toggle]');
        const summary = form.querySelector('#supplier-invoice-line-summary');
        const physicalTotal = form.querySelector('#supplier-invoice-physical-total');
        const physicalResult = form.querySelector('#supplier-invoice-physical-result');
        const currency = form.querySelector('#supplier-invoice-currency')?.textContent.trim() || 'S/';
        const fileInput = form.querySelector('#supplier-invoice-file');
        const fileControls = form.querySelector('.supplier-invoice-upload__controls');
        const fileName = form.querySelector('#supplier-invoice-file-name');
        const fileRemove = form.querySelector('#supplier-invoice-file-remove');
        const fileError = form.querySelector('#supplier-invoice-file-error');
        const fileServerError = form.querySelector('#supplier-invoice-file-server-error');
        const fileReselect = form.querySelector('#supplier-invoice-file-reselect');
        const finder = form.querySelector('.supplier-invoice-line-finder');
        const lineSearch = form.querySelector('#supplier-invoice-line-search');
        const searchNext = form.querySelector('#supplier-invoice-line-search-next');
        const searchResult = form.querySelector('#supplier-invoice-line-search-result');
        const rows = Array.from(form.querySelectorAll('[data-invoice-row]'));
        const maxFileSize = 15 * 1024 * 1024;
        const displayMoney = (value) => new Intl.NumberFormat('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
        const money = (value) => Number(value || 0).toFixed(2);
        let currentTotal = 0;

        const physicalAmount = (value) => {
            const text = value.trim();
            const normalized = /^\d{1,3}(,\d{3})+(?:\.\d{1,2})?$/.test(text)
                ? text.replaceAll(',', '')
                : /^\d{1,3}(\.\d{3})+(?:,\d{1,2})?$/.test(text)
                    ? text.replaceAll('.', '').replace(',', '.')
                    : text.replace(',', '.');
            return /^\d+(?:\.\d{1,2})?$/.test(normalized) ? Number(normalized) : NaN;
        };

        const updatePhysicalResult = (total) => {
            if (!physicalTotal || !physicalResult) {
                return;
            }
            if (physicalTotal.value.trim() === '') {
                physicalResult.textContent = 'Escribe el total del documento para compararlo con el total conciliado.';
                physicalResult.classList.remove('supplier-invoice-physical-check__result--different', 'supplier-invoice-physical-check__result--match', 'supplier-invoice-physical-check__result--invalid');
                return;
            }

            const physical = physicalAmount(physicalTotal.value);
            if (!Number.isFinite(physical) || physical < 0) {
                physicalResult.textContent = 'Importe inválido. Introduce un total válido.';
                physicalResult.classList.remove('supplier-invoice-physical-check__result--different', 'supplier-invoice-physical-check__result--match');
                physicalResult.classList.add('supplier-invoice-physical-check__result--invalid');
                return;
            }
            const difference = Math.round(Math.abs(physical - Math.round(total * 100) / 100) * 100) / 100;
            const matches = difference === 0;
            physicalResult.textContent = matches
                ? 'Coincide con el total conciliado.'
                : `Diferencia de ${currency} ${displayMoney(difference)} respecto al total conciliado.`;
            physicalResult.classList.remove('supplier-invoice-physical-check__result--invalid');
            physicalResult.classList.toggle('supplier-invoice-physical-check__result--different', !matches);
            physicalResult.classList.toggle('supplier-invoice-physical-check__result--match', matches);
        };

        const calculate = () => {
            let base = 0;
            let igv = 0;
            let total = 0;

            let reducedLines = 0;
            rows.forEach((row) => {
                const quantity = Number(row.querySelector('[data-invoice-quantity]')?.value || 0);
                const fullQuantity = Number(row.querySelector('[data-invoice-quantity]')?.dataset.fullQuantity || 0);
                const reduced = quantity < fullQuantity - 0.0005;
                row.classList.toggle('supplier-invoice-line--reduced', reduced);
                if (reduced) {
                    reducedLines += 1;
                }
                const cost = Number(row.dataset.unitCost || 0);
                const lineTotal = Math.round(quantity * cost * 10000) / 10000;
                const lineBase = row.dataset.taxed === '1' ? Math.round(lineTotal / 1.18 * 10000) / 10000 : lineTotal;
                const lineIgv = Math.round((lineTotal - lineBase) * 10000) / 10000;

                base += lineBase;
                igv += lineIgv;
                total += lineTotal;
                row.querySelector('[data-invoice-base]').textContent = money(lineBase);
                row.querySelector('[data-invoice-igv]').textContent = money(lineIgv);
                row.querySelector('[data-invoice-total]').textContent = money(lineTotal);
            });

            total = Math.round(total * 10000) / 10000;
            currentTotal = total;
            form.querySelector('[data-document-base]').textContent = displayMoney(base);
            form.querySelector('[data-document-igv]').textContent = displayMoney(igv);
            form.querySelector('[data-document-total]').textContent = displayMoney(total);
            if (summary) {
                summary.textContent = partialToggle?.checked
                    ? `Facturación parcial: ${reducedLines} de ${quantityInputs.length} líneas con cantidad menor · Total ${currency} ${displayMoney(total)}`
                    : `${quantityInputs.length} productos · se factura todo el saldo recibido · Total ${currency} ${displayMoney(total)}`;
            }
            updatePhysicalResult(total);
        };

        const syncPartialMode = ({ reset = false } = {}) => {
            const partial = partialToggle?.checked === true;

            quantityInputs.forEach((input) => {
                if (!partial && reset) {
                    input.value = input.dataset.fullQuantity || input.max;
                }

                input.readOnly = !partial;
                input.closest('td')?.querySelector('[data-invoice-quantity-help]')
                    ?.replaceChildren(document.createTextNode(partial ? 'Máximo recibido' : 'Saldo completo'));
            });

            form.classList.toggle('supplier-invoice-form--partial', partial);
            calculate();
        };

        quantityInputs.forEach((input) => input.addEventListener('input', calculate));
        partialToggle?.addEventListener('change', () => syncPartialMode({ reset: !partialToggle.checked }));
        physicalTotal?.addEventListener('input', () => updatePhysicalResult(currentTotal));

        if (finder && lineSearch && searchNext && searchResult) {
            const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es-PE');
            let matches = [];
            let nextMatch = 0;
            const updateMatches = () => {
                const query = normalize(lineSearch.value.trim());
                matches = query ? rows.filter((row) => {
                    const product = row.querySelector('td[data-label="Producto"]');
                    return normalize(`${product?.querySelector('strong')?.textContent || ''} ${product?.querySelector('span')?.textContent || ''}`).includes(query);
                }) : [];
                rows.forEach((row) => row.classList.toggle('supplier-invoice-line--found', matches.includes(row)));
                nextMatch = 0;
                searchNext.disabled = matches.length === 0;
                searchResult.textContent = !query
                    ? 'Escribe para encontrar una línea; ninguna se oculta.'
                    : matches.length === 0
                        ? 'Sin coincidencias. Todas las líneas siguen visibles.'
                        : `${matches.length} ${matches.length === 1 ? 'coincidencia' : 'coincidencias'}. Todas las líneas siguen visibles.`;
            };
            const goToMatch = () => {
                if (matches.length === 0) return;
                const row = matches[nextMatch];
                row.tabIndex = -1;
                row.focus({ preventScroll: true });
                row.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'auto' });
                nextMatch = (nextMatch + 1) % matches.length;
                searchResult.textContent = `Coincidencia ${nextMatch || matches.length} de ${matches.length}.`;
            };
            lineSearch.addEventListener('input', updateMatches);
            lineSearch.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    goToMatch();
                }
            });
            searchNext.addEventListener('click', goToMatch);
            finder.hidden = false;
            updateMatches();
        }

        if (fileInput && fileControls && fileName && fileRemove && fileError) {
            const showFile = ({ changed = false } = {}) => {
                const file = fileInput.files?.[0];
                fileName.textContent = file
                    ? `${file.name} · ${new Intl.NumberFormat('es-PE', { maximumFractionDigits: 2 }).format(file.size / (1024 * 1024))} MB`
                    : 'Ningún archivo seleccionado';
                fileRemove.hidden = !file;
                fileError.hidden = !file || file.size <= maxFileSize;
                fileError.textContent = fileError.hidden ? '' : 'El archivo supera el máximo de 15 MB. Elige otro.';
                if (changed) {
                    if (fileServerError) fileServerError.hidden = true;
                    if (fileReselect) fileReselect.hidden = true;
                    fileInput.toggleAttribute('aria-invalid', !fileError.hidden);
                }
            };

            fileInput.addEventListener('change', () => showFile({ changed: true }));
            fileRemove.addEventListener('click', () => {
                fileInput.value = '';
                showFile({ changed: true });
                fileInput.focus();
            });
            form.addEventListener('submit', (event) => {
                if (fileInput.files?.[0]?.size > maxFileSize) {
                    event.preventDefault();
                    showFile({ changed: true });
                    fileInput.focus();
                }
            });
            form.classList.add('supplier-invoice-form--enhanced');
            fileControls.hidden = false;
            showFile();
        }
        syncPartialMode();
        const errorSummary = document.querySelector('#supplier-invoice-errors');
        if (errorSummary) {
            const firstError = form.querySelector('[aria-invalid="true"]') || errorSummary;
            firstError.focus({ preventScroll: true });
            const target = firstError === fileInput && fileControls && !fileControls.hidden ? fileControls : firstError;
            target.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'auto' });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSupplierInvoiceForm, { once: true });
    } else {
        initSupplierInvoiceForm();
    }
})();
