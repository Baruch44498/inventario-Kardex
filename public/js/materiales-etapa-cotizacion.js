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
    let activeCurrency = currency?.value || 'PEN';

    const number = (value) => Number.parseFloat(value || '0') || 0;
    const exchange = number(form.querySelector('input[name="tipo_cambio"]')?.value);
    const money = (value, code) => `${code === 'USD' ? 'US$' : 'S/'} ${value.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
    const inSoles = (value, code) => code === 'USD' ? value * exchange : value;
    const fromSoles = (value, code) => code === 'USD' ? value / exchange : value;

    const refreshTaxRate = () => {
        if (!taxRate) return;

        const noAplica = taxMode?.value === 'NO_APLICA';
        taxRate.value = noAplica ? '0' : '18';
        if (taxRateLabel) taxRateLabel.textContent = noAplica ? 'No aplica' : '18%';
    };

    const refreshRow = (row) => {
        const quantity = number(row.querySelector('[data-material-quantity]')?.value);
        const unitCost = number(row.querySelector('[data-material-cost]')?.value);
        const code = currency?.value || 'PEN';
        const otherCode = code === 'USD' ? 'PEN' : 'USD';
        const converted = code === 'USD' ? unitCost * exchange : unitCost / exchange;
        const label = row.querySelector('[data-material-cost-label]');
        const unitEquivalent = row.querySelector('[data-material-unit-equivalent]');
        const subtotal = row.querySelector('[data-material-subtotal]');
        const subtotalEquivalent = row.querySelector('[data-material-subtotal-equivalent]');
        if (label) label.textContent = `Costo unitario (${code === 'USD' ? 'US$' : 'S/'})`;
        if (unitEquivalent) unitEquivalent.textContent = unitCost > 0 && exchange > 0
            ? `≈ ${money(converted, otherCode)} por unidad` : '—';
        if (subtotal) subtotal.textContent = quantity > 0 && unitCost > 0 ? money(quantity * unitCost, code) : '—';
        if (subtotalEquivalent) subtotalEquivalent.textContent = quantity > 0 && unitCost > 0 && exchange > 0
            ? `≈ ${money(quantity * converted, otherCode)}` : '—';
    };

    const refreshList = () => {
        const rows = Array.from(list.querySelectorAll('[data-material-row]'));
        let totalSoles = 0;
        let validRows = 0;
        rows.forEach((row, index) => {
            const rowNumber = row.querySelector('[data-material-row-number]');
            const remove = row.querySelector('[data-remove-material-row]');
            if (rowNumber) rowNumber.textContent = String(index + 1);
            if (remove) remove.disabled = rows.length === 1;
            refreshRow(row);
            const quantity = number(row.querySelector('[data-material-quantity]')?.value);
            const unitCost = number(row.querySelector('[data-material-cost]')?.value);
            if (quantity > 0 && unitCost > 0) {
                totalSoles += quantity * inSoles(unitCost, currency?.value || 'PEN');
                validRows += 1;
            }
        });
        if (count) count.textContent = `${rows.length} ${rows.length === 1 ? 'material' : 'materiales'} en este bloque`;
        const totalPen = form.querySelector('[data-bulk-total-pen]');
        const totalUsd = form.querySelector('[data-bulk-total-usd]');
        if (totalPen) totalPen.textContent = validRows ? money(totalSoles, 'PEN') : '—';
        if (totalUsd) totalUsd.textContent = validRows && exchange > 0 ? money(totalSoles / exchange, 'USD') : '—';
    };

    const initializeRow = (row) => {
        const box = row.querySelector('[data-remote-combobox]');
        const cost = row.querySelector('[data-material-cost]');
        const selectedProduct = box?.querySelector('[data-remote-combobox-value]');
        if (cost && selectedProduct?.value) cost.dataset.productId = selectedProduct.value;
        if (cost && number(cost.value) > 0) cost.dataset.penValue = String(inSoles(number(cost.value), activeCurrency));
        window.HidroilRemoteCombobox?.initialize(box);

        const applyWarehouseCost = () => {
            const referencePen = number(cost?.dataset.referencePen);
            if (!cost || referencePen <= 0 || (currency?.value === 'USD' && exchange <= 0)) return;
            const suggested = currency?.value === 'USD' ? referencePen / exchange : referencePen;
            cost.value = String(Number(suggested.toFixed(4)));
            cost.dataset.penValue = String(referencePen);
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
                        cost.dataset.penValue = '';
                        cost.dataset.fromWarehouse = 'false';
                    }
                }
            }
            refreshList();
        });

        cost?.addEventListener('input', () => {
            cost.dataset.fromWarehouse = 'false';
            cost.dataset.penValue = cost.value === '' ? '' : String(inSoles(number(cost.value), activeCurrency));
        });
        selectedProduct?.addEventListener('change', () => {
            if (selectedProduct.value !== '' || !cost) return;
            if (cost.dataset.fromWarehouse === 'true') {
                cost.value = '';
                cost.dataset.penValue = '';
            }
            cost.dataset.fromWarehouse = 'false';
            cost.dataset.referencePen = '';
            cost.dataset.productId = '';
            refreshList();
        });
        row.applyWarehouseCost = applyWarehouseCost;
        row.addEventListener('input', refreshList);
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
        if (exchange <= 0) {
            currency.value = activeCurrency;
            return;
        }
        list.querySelectorAll('[data-material-row]').forEach((row) => {
            const cost = row.querySelector('[data-material-cost]');
            if (!cost || number(cost.value) <= 0) return;
            const penValue = number(cost.dataset.penValue) || inSoles(number(cost.value), activeCurrency);
            cost.dataset.penValue = String(penValue);
            cost.value = String(Number(fromSoles(penValue, currency.value).toFixed(4)));
        });
        activeCurrency = currency.value;
        refreshList();
    });
    taxMode?.addEventListener('change', refreshTaxRate);
    list.querySelectorAll('[data-material-row]').forEach(initializeRow);
    refreshTaxRate();
    refreshList();
});
