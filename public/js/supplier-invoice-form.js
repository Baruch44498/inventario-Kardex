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
        const maxFileSize = 15 * 1024 * 1024;
        const displayMoney = (value) => new Intl.NumberFormat('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
        const money = (value) => Number(value || 0).toFixed(2);

        const updatePhysicalResult = (total) => {
            if (!physicalTotal || !physicalResult) {
                return;
            }
            if (physicalTotal.value.trim() === '') {
                physicalResult.textContent = 'Escribe el total del documento para compararlo con el total conciliado. No se guarda.';
                physicalResult.classList.remove('supplier-invoice-physical-check__result--different');
                return;
            }

            const physical = Number(physicalTotal.value.trim().replace(',', '.'));
            if (!Number.isFinite(physical) || physical < 0) {
                physicalResult.textContent = 'Introduce un importe válido.';
                physicalResult.classList.add('supplier-invoice-physical-check__result--different');
                return;
            }
            const difference = Math.round(Math.abs(physical - Number(total.toFixed(2))) * 100) / 100;
            const matches = difference === 0;
            physicalResult.textContent = matches
                ? 'Coincide con el total conciliado.'
                : `Diferencia de ${currency} ${displayMoney(difference)} respecto al total conciliado.`;
            physicalResult.classList.toggle('supplier-invoice-physical-check__result--different', !matches);
        };

        const calculate = () => {
            let base = 0;
            let igv = 0;
            let total = 0;

            let reducedLines = 0;
            form.querySelectorAll('[data-invoice-row]').forEach((row) => {
                const quantity = Number(row.querySelector('[data-invoice-quantity]')?.value || 0);
                const fullQuantity = Number(row.querySelector('[data-invoice-quantity]')?.dataset.fullQuantity || 0);
                const reduced = quantity < fullQuantity - 0.0005;
                row.classList.toggle('supplier-invoice-line--reduced', reduced);
                if (reduced) {
                    reducedLines += 1;
                }
                const cost = Number(row.dataset.unitCost || 0);
                const lineTotal = quantity * cost;
                const lineBase = row.dataset.taxed === '1' ? lineTotal / 1.18 : lineTotal;
                const lineIgv = lineTotal - lineBase;

                base += lineBase;
                igv += lineIgv;
                total += lineTotal;
                row.querySelector('[data-invoice-base]').textContent = money(lineBase);
                row.querySelector('[data-invoice-igv]').textContent = money(lineIgv);
                row.querySelector('[data-invoice-total]').textContent = money(lineTotal);
            });

            form.querySelector('[data-document-base]').textContent = money(base);
            form.querySelector('[data-document-igv]').textContent = money(igv);
            form.querySelector('[data-document-total]').textContent = money(total);
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
        physicalTotal?.addEventListener('input', () => {
            updatePhysicalResult(Number(form.querySelector('[data-document-total]')?.textContent || 0));
        });

        if (fileInput && fileControls && fileName && fileRemove && fileError) {
            const showFile = () => {
                const file = fileInput.files?.[0];
                fileName.textContent = file
                    ? `${file.name} · ${new Intl.NumberFormat('es-PE', { maximumFractionDigits: 2 }).format(file.size / (1024 * 1024))} MB`
                    : 'Ningún archivo seleccionado';
                fileRemove.hidden = !file;
                fileError.hidden = !file || file.size <= maxFileSize;
                fileError.textContent = fileError.hidden ? '' : 'El archivo supera el máximo de 15 MB. Elige otro.';
            };

            fileInput.addEventListener('change', showFile);
            fileRemove.addEventListener('click', () => {
                fileInput.value = '';
                showFile();
                fileInput.focus();
            });
            form.addEventListener('submit', (event) => {
                if (fileInput.files?.[0]?.size > maxFileSize) {
                    event.preventDefault();
                    showFile();
                    fileInput.focus();
                }
            });
            form.classList.add('supplier-invoice-form--enhanced');
            fileControls.hidden = false;
            showFile();
        }
        syncPartialMode();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSupplierInvoiceForm, { once: true });
    } else {
        initSupplierInvoiceForm();
    }
})();
