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
        const areaField = form.querySelector('[data-budget-area-field]');
        const areaInput = areaField?.querySelector('input[name="area_nombre"]');
        const areaSelect = areaField?.querySelector('[data-budget-area-select]');
        const newAreaOption = areaSelect?.querySelector('[data-budget-create-area]');
        const newAreaField = areaField?.querySelector('[data-budget-new-area]');
        const areaRequired = form.querySelector('[data-budget-area-required]');
        const areaHelp = form.querySelector('[data-budget-area-help]');
        const serviceField = form.querySelector('[data-budget-service-field]');
        const serviceExecution = form.querySelector('[data-budget-service-execution]');
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
        const taxRateLabel = form.querySelector('[data-budget-tax-rate-label]');
        const saleTaxRate = form.querySelector('[data-budget-sale-tax-rate]');
        const preview = form.querySelector('[data-budget-preview-text]');
        const resultCards = form.querySelector('[data-budget-result-cards]');
        const resultCostPen = form.querySelector('[data-budget-result-cost-pen]');
        const resultSalePen = form.querySelector('[data-budget-result-sale-pen]');
        const resultUtilityPen = form.querySelector('[data-budget-result-utility-pen]');
        const resultCostUsd = form.querySelector('[data-budget-result-cost-usd]');

        form.querySelectorAll('[data-budget-before-value]').forEach((item) => {
            item.textContent = `Antes: ${money(number(item.dataset.budgetBeforeValue), item.dataset.budgetBeforeCurrency)}`;
        });
        if (areaInput?.value.trim() && newAreaOption && !areaSelect?.value) newAreaOption.selected = true;

        if (unitCost && productValue?.value) unitCost.dataset.productId = productValue.value;
        const applyWarehouseCost = () => {
            const referencePen = number(unitCost?.dataset.referencePen);
            const tc = number(exchange?.value);
            if (!unitCost || referencePen <= 0 || (currency?.value === 'USD' && tc <= 0)) return;
            const suggested = currency?.value === 'USD' ? referencePen / tc : referencePen;
            unitCost.value = String(Number(suggested.toFixed(4)));
            unitCost.dataset.fromWarehouse = 'true';
        };

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

                return;
            }

            unitSelect.disabled = currentType === '';
            if (contextOption) contextOption.hidden = true;

            if (currentType === '') {
                contextualOption('Selecciona primero el tipo de costo');
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
        };

        const refreshProductRules = (isMaterial) => {
            if (productField) productField.hidden = !isMaterial;
            if (productBox) productBox.dataset.required = isMaterial ? 'true' : 'false';
            if (productSearch) productSearch.required = isMaterial;

            if (!quantity) return;

            const allowsFraction = unitField?.dataset.productAllowsFraction === 'true';
            const quantityIncrement = isMaterial && !allowsFraction ? '1' : '0.001';
            quantity.min = quantityIncrement;
            quantity.step = quantityIncrement;
        };

        const refreshUnitQuantityHelp = (isMaterial) => {
            if (!unitHelp && !quantityHint) return;
            const allowsFraction = unitField?.dataset.productAllowsFraction === 'true';
            const productUnit = unitField?.dataset.productUnitLabel || unitField?.dataset.productUnitCode;
            const message = isMaterial
                ? `${productUnit ? `Unidad fija: ${productUnit}. ` : 'Elige un producto para fijar su unidad. '}${allowsFraction ? 'Cantidad hasta tres decimales.' : 'Cantidad en números enteros.'}`
                : `${type?.value ? 'Unidades compatibles con el tipo de costo.' : 'Selecciona un tipo de costo.'} Cantidad hasta tres decimales.`;
            if (unitHelp) unitHelp.textContent = message;
            if (quantityHint && quantityHint !== unitHelp) quantityHint.textContent = message;
        };

        const refreshTaxRate = () => {
            if (!taxRate) return;

            const noAplica = taxMode?.value === 'NO_APLICA';
            taxRate.value = noAplica ? '0' : '18';
            if (taxRateLabel) taxRateLabel.textContent = noAplica ? 'No aplica' : '18%';
        };

        const refresh = () => {
            refreshTaxRate();
            const isMaterial = type?.value === 'MATERIAL';
            const isLabor = type?.value === 'MANO_OBRA';
            const isService = type?.value === 'SERVICIO_TERCERO';
            if (socialField) socialField.hidden = !isLabor;
            if (areaField) areaField.hidden = !(isMaterial || isService);
            const creatingArea = areaSelect?.selectedOptions?.[0] === newAreaOption;
            if (newAreaField) newAreaField.hidden = !creatingArea;
            if (areaInput) areaInput.required = isMaterial && creatingArea;
            if (areaSelect) areaSelect.required = isMaterial && !creatingArea;
            if (areaRequired) areaRequired.hidden = !isMaterial;
            if (areaHelp) {
                areaHelp.textContent = isService
                    ? 'Opcional. Selecciona el área cuando el servicio se realiza para una parte concreta de la orden.'
                    : 'Obligatorio. Determina dónde se comparará el material estimado con la salida real.';
            }
            if (serviceField) serviceField.hidden = !isService;
            if (serviceExecution) serviceExecution.required = isService;
            refreshProductRules(isMaterial);
            refreshUnit(isMaterial);
            refreshUnitQuantityHelp(isMaterial);

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

            if (!preview || qty <= 0 || unit <= 0 || tc <= 0) {
                if (preview) preview.textContent = 'Completa cantidad, costo y tipo de cambio.';
                if (resultCards) resultCards.hidden = true;
                return;
            }

            const original = currency?.value || 'PEN';
            const saleNet = net * (1 + marginRate / 100);
            const saleTax = saleNet * saleRate / 100;
            const saleTotal = saleNet + saleTax;
            const utility = saleNet - net;
            const totalPen = original === 'USD' ? total * tc : total;
            const totalUsd = original === 'PEN' ? total / tc : total;
            const salePen = original === 'USD' ? saleTotal * tc : saleTotal;
            const utilityPen = original === 'USD' ? utility * tc : utility;
            preview.textContent = '';
            if (resultCostPen) resultCostPen.textContent = money(totalPen, 'PEN');
            if (resultSalePen) resultSalePen.textContent = money(salePen, 'PEN');
            if (resultUtilityPen) resultUtilityPen.textContent = money(utilityPen, 'PEN');
            if (resultCostUsd) resultCostUsd.textContent = money(totalUsd, 'USD');
            if (resultCards) resultCards.hidden = false;
        };

        areaSelect?.addEventListener('change', () => {
            if (areaSelect.selectedOptions[0] !== newAreaOption && areaInput) areaInput.value = '';
            refresh();
            if (areaSelect.selectedOptions[0] === newAreaOption) areaInput?.focus();
        });
        areaInput?.addEventListener('input', () => {
            if (areaInput.value.trim() && newAreaOption) newAreaOption.selected = true;
        });
        form.addEventListener('input', refresh);
        form.addEventListener('change', refresh);
        unitCost?.addEventListener('input', () => { unitCost.dataset.fromWarehouse = 'false'; });
        currency?.addEventListener('change', () => {
            if (unitCost?.dataset.fromWarehouse === 'true') applyWarehouseCost();
        });
        taxMode?.addEventListener('change', refreshTaxRate);
        productBox?.addEventListener('remote-combobox:selected', (event) => {
            const referencePen = number(event.detail?.costo_referencia);
            const productId = String(event.detail?.id || '');
            const changedProduct = Boolean(unitCost?.dataset.productId && unitCost.dataset.productId !== productId);
            if (unitField) {
                unitField.dataset.productUnitCode = event.detail?.unidad_codigo || '';
                unitField.dataset.productUnitLabel = event.detail?.unidad_nombre || event.detail?.unidad || '';
                unitField.dataset.productAllowsFraction = event.detail?.permite_fraccionamiento ? 'true' : 'false';
            }
            if (description && description.value.trim() === '') {
                description.value = event.detail?.descripcion || '';
            }
            if (unitCost) {
                const suggest = number(unitCost.value) <= 0 || unitCost.dataset.fromWarehouse === 'true' || changedProduct;
                unitCost.dataset.productId = productId;
                unitCost.dataset.referencePen = referencePen > 0 ? String(referencePen) : '';
                if (suggest) {
                    if (referencePen > 0) applyWarehouseCost();
                    else {
                        unitCost.value = '';
                        unitCost.dataset.fromWarehouse = 'false';
                    }
                }
            }
            refresh();
        });
        productValue?.addEventListener('change', () => {
            if (productValue.value !== '' || !unitField) return;
            unitField.dataset.productUnitCode = '';
            unitField.dataset.productUnitLabel = '';
            unitField.dataset.productAllowsFraction = 'false';
            if (unitCost) {
                if (unitCost.dataset.fromWarehouse === 'true') unitCost.value = '';
                unitCost.dataset.fromWarehouse = 'false';
                unitCost.dataset.referencePen = '';
                unitCost.dataset.productId = '';
            }
            refresh();
        });
        refresh();
    });
})();
