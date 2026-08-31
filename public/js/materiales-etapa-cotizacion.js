document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-bulk-material-form]');
    if (!form) return;

    const list = form.querySelector('[data-bulk-material-list]');
    const template = form.querySelector('[data-bulk-material-template]');
    const addButton = form.querySelector('[data-add-material-row]');
    const count = form.querySelector('[data-bulk-material-count]');
    const currency = form.querySelector('[data-bulk-currency]');
    let nextIndex = list?.querySelectorAll('[data-material-row]').length || 0;

    const number = (value) => Number.parseFloat(value || '0') || 0;
    const money = (value) => `${currency?.value === 'USD' ? 'US$' : 'S/'} ${value.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

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
        window.HidroilRemoteCombobox?.initialize(box);

        box?.addEventListener('remote-combobox:selected', (event) => {
            const unit = row.querySelector('[data-material-unit]');
            const quantity = row.querySelector('[data-material-quantity]');
            const cost = row.querySelector('[data-material-cost]');
            const allowsFraction = Boolean(event.detail?.permite_fraccionamiento);
            if (unit) unit.value = event.detail?.unidad_codigo || 'Automática';
            if (quantity) {
                quantity.min = allowsFraction ? '0.001' : '1';
                quantity.step = allowsFraction ? '0.001' : '1';
            }
            if (cost && number(cost.value) === 0 && number(event.detail?.costo_referencia) > 0) {
                cost.value = String(event.detail.costo_referencia);
            }
            refreshRow(row);
        });

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

    currency?.addEventListener('change', refreshList);
    list.querySelectorAll('[data-material-row]').forEach(initializeRow);
    refreshList();
});
