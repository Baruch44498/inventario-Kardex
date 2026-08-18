(() => {
    const number = (value) => Number.parseFloat(value || '0') || 0;
    const money = (value, currency) => `${currency === 'USD' ? 'US$' : 'S/'} ${value.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    document.querySelectorAll('[data-budget-form]').forEach((form) => {
        const type = form.querySelector('[data-budget-type]');
        const productField = form.querySelector('[data-budget-product-field]');
        const socialField = form.querySelector('[data-budget-social-field]');
        const description = form.querySelector('[data-budget-description]');
        const quantity = form.querySelector('[data-budget-quantity]');
        const currency = form.querySelector('[data-budget-currency]');
        const exchange = form.querySelector('[data-budget-exchange]');
        const unitCost = form.querySelector('[data-budget-unit-cost]');
        const social = form.querySelector('[data-budget-social]');
        const taxMode = form.querySelector('[data-budget-tax-mode]');
        const taxRate = form.querySelector('[data-budget-tax-rate]');
        const preview = form.querySelector('[data-budget-preview-text]');

        const refresh = () => {
            const isMaterial = type?.value === 'MATERIAL';
            const isLabor = type?.value === 'MANO_OBRA';
            if (productField) productField.hidden = !isMaterial;
            if (socialField) socialField.hidden = !isLabor;

            const qty = number(quantity?.value);
            const unit = number(unitCost?.value);
            const tc = number(exchange?.value);
            const socialRate = isLabor ? number(social?.value) : 0;
            const rate = number(taxRate?.value);
            const base = qty * unit;
            const withSocial = base + base * socialRate / 100;
            let net = withSocial;
            let tax = 0;
            let total = withSocial;

            if (taxMode?.value === 'INCLUIDO' && rate > 0) {
                net = total / (1 + rate / 100);
                tax = total - net;
            } else if (taxMode?.value === 'AGREGAR') {
                tax = net * rate / 100;
                total = net + tax;
            }

            if (!preview || qty <= 0 || tc <= 0) {
                if (preview) preview.textContent = 'Completa cantidad, costo y tipo de cambio.';
                return;
            }

            const original = currency?.value || 'PEN';
            const netPen = original === 'USD' ? net * tc : net;
            const totalPen = original === 'USD' ? total * tc : total;
            const netUsd = original === 'PEN' ? net / tc : net;
            preview.textContent = `Original: ${money(total, original)} · Neto PEN: ${money(netPen, 'PEN')} · Total PEN: ${money(totalPen, 'PEN')} · Neto USD: ${money(netUsd, 'USD')}`;
        };

        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        form.querySelector('[data-remote-combobox]')?.addEventListener('remote-combobox:selected', (event) => {
            if (description && description.value.trim() === '') {
                description.value = event.detail?.descripcion || '';
            }
            if (unitCost && number(unitCost.value) === 0 && number(event.detail?.costo_referencia) > 0) {
                unitCost.value = String(event.detail.costo_referencia);
            }
            refresh();
        });
        refresh();
    });
})();
