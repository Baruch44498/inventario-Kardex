(() => {
    const number = (value) => Number.parseFloat(value || '0') || 0;
    const money = (value, currency) => `${currency === 'USD' ? 'US$' : 'S/'} ${value.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    document.querySelectorAll('[data-budget-form]').forEach((form) => {
        const type = form.querySelector('[data-budget-type]');
        const productField = form.querySelector('[data-budget-product-field]');
        const productBox = form.querySelector('[data-remote-combobox]');
        const productSearch = productBox?.querySelector('[data-remote-combobox-search]');
        const productValue = productBox?.querySelector('[data-remote-combobox-value]');
        const socialField = form.querySelector('[data-budget-social-field]');
        const unitField = form.querySelector('[data-budget-unit-field]');
        const unitSelect = form.querySelector('[data-budget-unit]');
        const unitHelp = form.querySelector('[data-budget-unit-help]');
        const quantityHint = form.querySelector('[data-budget-quantity-hint]');
        const staticUnitOptions = Array.from(form.querySelectorAll('[data-budget-unit-option]'));
        const description = form.querySelector('[data-budget-description]');
        const quantity = form.querySelector('[data-budget-quantity]');
        const currency = form.querySelector('[data-budget-currency]');
        const exchange = form.querySelector('[data-budget-exchange]');
        const unitCost = form.querySelector('[data-budget-unit-cost]');
        const margin = form.querySelector('[data-budget-margin]');
        const social = form.querySelector('[data-budget-social]');
        const taxMode = form.querySelector('[data-budget-tax-mode]');
        const taxRate = form.querySelector('[data-budget-tax-rate]');
        const saleTaxRate = form.querySelector('[data-budget-sale-tax-rate]');
        const preview = form.querySelector('[data-budget-preview-text]');

        const contextualOption = (text, value = '') => {
            let option = unitSelect?.querySelector('[data-budget-context-unit]');
            if (!unitSelect) return null;

            if (!option) {
                option = document.createElement('option');
                option.dataset.budgetContextUnit = 'true';
                unitSelect.prepend(option);
            }

            option.value = value;
            option.textContent = text;
            option.hidden = false;
            option.disabled = false;
            option.selected = true;

            return option;
        };

        const refreshUnit = (isMaterial) => {
            if (!unitSelect || !unitField) return;

            const currentType = type?.value || '';
            const productUnitCode = String(unitField.dataset.productUnitCode || '').toUpperCase();
            const productUnitLabel = unitField.dataset.productUnitLabel || productUnitCode;
            const contextOption = unitSelect.querySelector('[data-budget-context-unit]');

            staticUnitOptions.forEach((option) => {
                option.hidden = true;
                option.disabled = true;
            });

            if (isMaterial) {
                unitSelect.disabled = true;

                if (!productUnitCode) {
                    contextualOption('Selecciona primero un producto');
                    if (unitHelp) unitHelp.textContent = 'La unidad se copiará automáticamente desde el catálogo de almacén.';
                    return;
                }

                const catalogOption = staticUnitOptions.find((option) => option.value === productUnitCode);
                if (catalogOption) {
                    if (contextOption) contextOption.hidden = true;
                    catalogOption.hidden = false;
                    catalogOption.disabled = false;
                    catalogOption.selected = true;
                } else {
                    contextualOption(`${productUnitCode} · ${productUnitLabel}`, productUnitCode);
                }

                if (unitHelp) unitHelp.textContent = `Unidad fija del producto: ${productUnitLabel || productUnitCode}.`;
                return;
            }

            unitSelect.disabled = currentType === '';
            if (contextOption) contextOption.hidden = true;

            if (currentType === '') {
                contextualOption('Selecciona primero el tipo de costo');
                if (unitHelp) unitHelp.textContent = 'Las opciones cambian según el tipo de costo.';
                return;
            }

            const compatibles = staticUnitOptions.filter((option) => {
                const types = String(option.dataset.compatibleTypes || '').split(',');
                const compatible = types.includes(currentType);
                option.hidden = !compatible;
                option.disabled = !compatible;
                return compatible;
            });
            const selected = compatibles.find((option) => option.selected) || compatibles[0];
            if (selected) selected.selected = true;
            if (unitHelp) unitHelp.textContent = 'Solo se muestran unidades compatibles con este tipo de costo.';
        };

        const refreshProductRules = (isMaterial) => {
            if (productField) productField.hidden = !isMaterial;
            if (productBox) productBox.dataset.required = isMaterial ? 'true' : 'false';
            if (productSearch) productSearch.required = isMaterial;

            if (!quantity) return;

            const allowsFraction = unitField?.dataset.productAllowsFraction === 'true';
            quantity.step = isMaterial && !allowsFraction ? '1' : '0.001';
            if (quantityHint) {
                quantityHint.textContent = isMaterial && !allowsFraction
                    ? 'Este producto se controla en cantidades enteras.'
                    : 'Admite hasta tres decimales.';
            }
        };

        const refresh = () => {
            const isMaterial = type?.value === 'MATERIAL';
            const isLabor = type?.value === 'MANO_OBRA';
            if (socialField) socialField.hidden = !isLabor;
            refreshProductRules(isMaterial);
            refreshUnit(isMaterial);

            const qty = number(quantity?.value);
            const unit = number(unitCost?.value);
            const tc = number(exchange?.value);
            const socialRate = isLabor ? number(social?.value) : 0;
            const rate = number(taxRate?.value);
            const marginRate = number(margin?.value);
            const saleRate = number(saleTaxRate?.value);
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
            const saleNet = net * (1 + marginRate / 100);
            const saleTax = saleNet * saleRate / 100;
            const saleTotal = saleNet + saleTax;
            const utility = saleNet - net;
            const netPen = original === 'USD' ? net * tc : net;
            const totalPen = original === 'USD' ? total * tc : total;
            const netUsd = original === 'PEN' ? net / tc : net;
            const salePen = original === 'USD' ? saleTotal * tc : saleTotal;
            const utilityPen = original === 'USD' ? utility * tc : utility;
            preview.textContent = `Costo original: ${money(total, original)} · Costo neto PEN: ${money(netPen, 'PEN')} · Costo total PEN: ${money(totalPen, 'PEN')} · Venta total PEN: ${money(salePen, 'PEN')} · Utilidad neta PEN: ${money(utilityPen, 'PEN')} · Costo neto USD: ${money(netUsd, 'USD')}`;
        };

        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        productBox?.addEventListener('remote-combobox:selected', (event) => {
            if (unitField) {
                unitField.dataset.productUnitCode = event.detail?.unidad_codigo || '';
                unitField.dataset.productUnitLabel = event.detail?.unidad_nombre || event.detail?.unidad || '';
                unitField.dataset.productAllowsFraction = event.detail?.permite_fraccionamiento ? 'true' : 'false';
            }
            if (description && description.value.trim() === '') {
                description.value = event.detail?.descripcion || '';
            }
            if (unitCost && number(unitCost.value) === 0 && number(event.detail?.costo_referencia) > 0) {
                unitCost.value = String(event.detail.costo_referencia);
            }
            refresh();
        });
        productValue?.addEventListener('change', () => {
            if (productValue.value !== '' || !unitField) return;
            unitField.dataset.productUnitCode = '';
            unitField.dataset.productUnitLabel = '';
            unitField.dataset.productAllowsFraction = 'false';
            refresh();
        });
        refresh();
    });
})();
