(() => {
    'use strict';

    const initSupplierInvoiceForm = () => {
        const form = document.querySelector('[data-supplier-invoice-form]');

        if (!form) {
            return;
        }

        const quantityInputs = Array.from(form.querySelectorAll('[data-invoice-quantity]'));
        const partialToggle = form.querySelector('[data-partial-billing-toggle]');
        const money = (value) => Number(value || 0).toFixed(2);

        const calculate = () => {
            let base = 0;
            let igv = 0;
            let total = 0;

            form.querySelectorAll('[data-invoice-row]').forEach((row) => {
                const quantity = Number(row.querySelector('[data-invoice-quantity]')?.value || 0);
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
        syncPartialMode();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSupplierInvoiceForm, { once: true });
    } else {
        initSupplierInvoiceForm();
    }
})();
