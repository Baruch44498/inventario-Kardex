document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-bulk-material-form]');
    if (!form) return;

    const list = form.querySelector('[data-bulk-material-list]');
    const template = form.querySelector('[data-bulk-material-template]');
    const addButton = form.querySelector('[data-add-material-row]');
    const count = form.querySelector('[data-bulk-material-count]');
    const currency = form.querySelector('[data-bulk-currency]');
    const taxMode = form.querySelector('[data-bulk-tax-mode]');
    const taxRate = form.querySelector('[data-bulk-tax-rate]');
    const taxRateLabel = form.querySelector('[data-bulk-tax-rate-label]');
    let nextIndex = list?.querySelectorAll('[data-material-row]').length || 0;

    const number = (value) => Number.parseFloat(value || '0') || 0;
    const exchange = number(form.querySelector('input[name="tipo_cambio"]')?.value);
    const money = (value) => `${currency?.value === 'USD' ? 'US$' : 'S/'} ${value.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

    const refreshTaxRate = () => {
        if (!taxRate) return;

        const noAplica = taxMode?.value === 'NO_APLICA';
        taxRate.value = noAplica ? '0' : '18';
        if (taxRateLabel) taxRateLabel.textContent = noAplica ? 'No aplica' : '18%';
    };

    const refreshRow = (row) => {
        const quantity = number(row.querySelector('[data-material-quantity]')?.value);
        const unitCost = number(row.querySelector('[data-material-cost]')?.value);
        const subtotal = row.querySelector('[data-material-subtotal]');
        if (subtotal) subtotal.textContent = quantity > 0 && unitCost > 0 ? money(quantity * unitCost) : '—';
    };

    const refreshList = () => {
        const rows = Array.from(list.querySelectorAll('[data-material-row]'));
        rows.forEach((row, index) => {
            const rowNumber = row.querySelector('[data-material-row-number]');
            const remove = row.querySelector('[data-remove-material-row]');
            if (rowNumber) rowNumber.textContent = String(index + 1);
            if (remove) remove.disabled = rows.length === 1;
            refreshRow(row);
        });
        if (count) count.textContent = `${rows.length} ${rows.length === 1 ? 'material' : 'materiales'} en este bloque`;
    };

    const initializeRow = (row) => {
        const box = row.querySelector('[data-remote-combobox]');
        const cost = row.querySelector('[data-material-cost]');
        const selectedProduct = box?.querySelector('[data-remote-combobox-value]');
        if (cost && selectedProduct?.value) cost.dataset.productId = selectedProduct.value;
        window.HidroilRemoteCombobox?.initialize(box);

        const applyWarehouseCost = () => {
            const referencePen = number(cost?.dataset.referencePen);
            if (!cost || referencePen <= 0 || (currency?.value === 'USD' && exchange <= 0)) return;
            const suggested = currency?.value === 'USD' ? referencePen / exchange : referencePen;
            cost.value = String(Number(suggested.toFixed(4)));
            cost.dataset.fromWarehouse = 'true';
        };

        box?.addEventListener('remote-combobox:selected', (event) => {
            const unit = row.querySelector('[data-material-unit]');
            const quantity = row.querySelector('[data-material-quantity]');
            const allowsFraction = Boolean(event.detail?.permite_fraccionamiento);
            const referencePen = number(event.detail?.costo_referencia);
            const productId = String(event.detail?.id || '');
            const changedProduct = Boolean(cost?.dataset.productId && cost.dataset.productId !== productId);
            if (unit) unit.value = event.detail?.unidad_codigo || 'Automática';
            if (quantity) {
                quantity.min = allowsFraction ? '0.001' : '1';
                quantity.step = allowsFraction ? '0.001' : '1';
            }
            if (cost) {
                const suggest = number(cost.value) <= 0 || cost.dataset.fromWarehouse === 'true' || changedProduct;
                cost.dataset.productId = productId;
                cost.dataset.referencePen = referencePen > 0 ? String(referencePen) : '';
                if (suggest) {
                    if (referencePen > 0) applyWarehouseCost();
                    else {
                        cost.value = '';
                        cost.dataset.fromWarehouse = 'false';
                    }
                }
            }
            refreshRow(row);
        });

        cost?.addEventListener('input', () => { cost.dataset.fromWarehouse = 'false'; });
        selectedProduct?.addEventListener('change', () => {
            if (selectedProduct.value !== '' || !cost) return;
            if (cost.dataset.fromWarehouse === 'true') cost.value = '';
            cost.dataset.fromWarehouse = 'false';
            cost.dataset.referencePen = '';
            cost.dataset.productId = '';
            refreshRow(row);
        });
        row.applyWarehouseCost = applyWarehouseCost;
        row.addEventListener('input', () => refreshRow(row));
        row.querySelector('[data-remove-material-row]')?.addEventListener('click', () => {
            if (list.querySelectorAll('[data-material-row]').length <= 1) return;
            row.remove();
            refreshList();
        });
    };

    addButton?.addEventListener('click', () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex));
        const row = wrapper.firstElementChild;
        nextIndex += 1;
        if (!row) return;
        list.appendChild(row);
        initializeRow(row);
        refreshList();
        row.querySelector('[data-remote-combobox-search]')?.focus();
    });

    currency?.addEventListener('change', () => {
        list.querySelectorAll('[data-material-row]').forEach((row) => {
            if (row.querySelector('[data-material-cost]')?.dataset.fromWarehouse === 'true') {
                row.applyWarehouseCost?.();
            }
        });
        refreshList();
    });
    taxMode?.addEventListener('change', refreshTaxRate);
    list.querySelectorAll('[data-material-row]').forEach(initializeRow);
    refreshTaxRate();
    refreshList();
});
