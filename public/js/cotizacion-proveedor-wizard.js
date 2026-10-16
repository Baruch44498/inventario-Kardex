const supplierQuoteDecimalFromPaste = (value) => {
    const compact = value.trim().replace(/\s/g, '');
    if (!/^\d[\d.,]*$/.test(compact)) return null;
    const comma = compact.lastIndexOf(',');
    const dot = compact.lastIndexOf('.');
    const decimal = Math.max(comma, dot);
    const integer = compact.slice(0, decimal).replace(/[.,]/g, '');
    const fraction = decimal < 0 ? '' : compact.slice(decimal + 1);
    const normalized = decimal < 0 ? compact : `${integer}.${fraction}`;
    return /^\d+(?:\.\d+)?$/.test(normalized) ? normalized : null;
};

const supplierQuotePastePlan = (text, available) => {
    const entries = text.split(/\r?\n/).filter((entry) => entry.trim());
    return {
        values: entries.slice(0, available).map((entry, position) => ({
            position,
            value: supplierQuoteDecimalFromPaste(entry.split('\t')[0]),
        })),
        overflow: Math.max(0, entries.length - available),
    };
};

const supplierQuoteRequestedAmount = (requested, factor) => {
    const presentation = Math.round((requested / factor + Number.EPSILON) * 100) / 100;
    const useBase = Math.abs(presentation * factor - requested) > 0.0001;
    return { quantity: useBase ? requested : presentation, useBase };
};

const supplierQuoteSetOptionsOpen = (line, open, activeElement = null) => {
    const options = line.querySelector('[data-line-options]');
    const toggle = line.querySelector('[data-line-options-toggle]');
    if (!options || !toggle) return;
    if (!open && options.contains(activeElement)) toggle.focus();
    options.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.textContent = open ? 'Ocultar detalles' : 'Más detalles';
    const number = line.querySelector('.supplier-quote-line__index span')?.textContent?.trim();
    toggle.setAttribute('aria-label', `${toggle.textContent} del producto ${number || 'actual'}`);
};

const supplierQuoteBindOptionsToggle = (line, activeElement = () => document.activeElement) => {
    const options = line.querySelector('[data-line-options]');
    const toggle = line.querySelector('[data-line-options-toggle]');
    if (!options || !toggle) return;
    toggle.addEventListener('click', () => {
        supplierQuoteSetOptionsOpen(line, options.hidden, activeElement());
    });
    options.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        event.preventDefault();
        supplierQuoteSetOptionsOpen(line, false, activeElement());
    });
    supplierQuoteSetOptionsOpen(line, line.dataset.initialOptions === '1', activeElement());
};

const supplierQuoteSyncLineBadges = (line, sharedObservation = false) => {
    const type = line.querySelector('[data-line-link-type]')?.value;
    const requested = line.querySelector('[data-line-requisition-detail]')?.value;
    const alternative = line.querySelector('[data-line-alternative]');
    if (alternative) alternative.hidden = type !== 'ALTERNATIVA' || !requested;

    const discount = line.querySelector('[data-line-discount-badge]');
    const discountType = line.querySelector('[data-line-discount-type]')?.value;
    const discountValue = Number(line.querySelector('[data-line-discount-value]')?.value || 0);
    if (discount) discount.hidden = line.querySelector('[data-line-discount-mode]')?.value !== 'APLICAR'
        || !['MONTO', 'PORCENTAJE'].includes(discountType) || !(discountValue > 0);

    const presentation = line.querySelector('[data-line-presentation]')?.selectedOptions?.[0];
    const presentationBadge = line.querySelector('[data-line-presentation-badge]');
    if (presentationBadge) {
        presentationBadge.hidden = !presentation?.value;
        presentationBadge.textContent = presentation?.value
            ? 'Presentación: ' + (presentation.dataset.name || presentation.textContent?.trim() || '')
            : '';
    }

    const brand = line.querySelector('[data-line-brand]')?.value.trim();
    const brandSummary = line.querySelector('[data-line-brand-summary]');
    if (brandSummary) {
        brandSummary.hidden = !brand;
        brandSummary.textContent = brand ? `Marca: ${brand}` : '';
    }
    const note = line.querySelector('[data-line-observation]')?.value.trim();
    const noteSummary = line.querySelector('[data-line-note-summary]');
    if (noteSummary) noteSummary.hidden = !note || sharedObservation;
};

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        supplierQuoteDecimalFromPaste, supplierQuotePastePlan, supplierQuoteRequestedAmount,
        supplierQuoteSetOptionsOpen, supplierQuoteBindOptionsToggle, supplierQuoteSyncLineBadges,
    };
}

document.addEventListener('DOMContentLoaded', () => {
    const wizard = document.querySelector('[data-supplier-quote-wizard]');
    const form = wizard?.closest('form');
    const stepper = wizard?.querySelector('[data-workflow-stepper]');
    const panels = Array.from(wizard?.querySelectorAll('[data-quote-step-panel]') || []);
    const lines = wizard?.querySelector('[data-supplier-quote-lines]');
    const template = wizard?.querySelector('[data-supplier-quote-line-template]');
    const addButton = wizard?.querySelector('[data-add-supplier-quote-line]');
    const currency = wizard?.querySelector('[data-supplier-quote-currency]');
    const exchangeField = wizard?.querySelector('[data-supplier-exchange-field]');
    const exchangeInput = wizard?.querySelector('[data-supplier-exchange-input]');
    const provider = wizard?.querySelector('[data-quote-provider]');
    const providerSearch = wizard?.querySelector('#proveedor_busqueda');
    const documentNumber = wizard?.querySelector('[data-quote-document-number]');
    const quoteDate = wizard?.querySelector('[data-quote-date]');
    const globalSwitch = wizard?.querySelector('[data-global-discount-switch]');
    const globalMode = wizard?.querySelector('[data-global-discount-mode]');
    const globalFields = wizard?.querySelector('[data-global-discount-fields]');
    const globalType = wizard?.querySelector('[data-global-discount-type]');
    const globalValue = wizard?.querySelector('[data-global-discount-value]');
    const globalAnswer = wizard?.querySelector('[data-global-discount-answer]');
    const globalValueLabel = wizard?.querySelector('[data-global-discount-value-label]');
    const reconciliation = wizard?.querySelector('[data-quote-reconciliation]');
    const roundingConfirmation = wizard?.querySelector('[data-rounding-adjustment-confirmation]');
    const roundingCheckbox = wizard?.querySelector('[data-rounding-adjustment-checkbox]');
    const entryMethods = document.querySelector('.supplier-quote-entry-methods');
    const entrySummary = wizard?.querySelector('[data-quote-entry-summary]');
    const unpricedFilter = wizard?.querySelector('[data-quote-unpriced-filter]');
    const unpricedEmpty = wizard?.querySelector('[data-quote-unpriced-empty]');
    const pasteStatus = wizard?.querySelector('[data-quote-paste-status]');
    const documentTotal = wizard.dataset.documentTotal !== undefined
        ? Number(wizard.dataset.documentTotal)
        : null;
    const documentSubtotal = wizard.dataset.documentSubtotal !== undefined
        && wizard.dataset.documentSubtotal !== ''
        ? Number(wizard.dataset.documentSubtotal)
        : null;
    const documentTax = wizard.dataset.documentTax !== undefined
        && wizard.dataset.documentTax !== ''
        ? Number(wizard.dataset.documentTax)
        : null;
    const documentCurrency = wizard.dataset.documentCurrency || null;

    if (!wizard || !form || !lines || !template) return;

    let currentStep = Number(wizard.dataset.initialStep || 1);
    let reachableStep = currentStep;
    let nextIndex = lines.querySelectorAll('[data-supplier-quote-line]').length;
    let lastTotalText = '—';
    let reconciliationMatches = documentTotal === null;

    const symbol = () => currency?.value === 'USD' ? 'US$' : 'S/';
    const number = (value, decimals = 2) => Number(value || 0).toLocaleString(
        'es-PE',
        { minimumFractionDigits: decimals, maximumFractionDigits: decimals }
    );
    const money = (value, decimals = 2) => symbol() + ' ' + number(value, decimals);
    const documentMoney = (value, decimals = 2) =>
        (documentCurrency === 'USD' ? 'US$' : 'S/') + ' ' + number(value, decimals);
    const roundTo = (value, decimals) => Math.round(
        (Number(value) + Number.EPSILON) * (10 ** decimals)
    ) / (10 ** decimals);
    const setText = (selector, value) => {
        wizard.querySelectorAll(selector).forEach((element) => {
            element.textContent = value;
        });
    };

    const paintWorkflow = () => {
        if (!stepper) return;

        stepper.dataset.currentStep = String(currentStep);
        stepper.querySelectorAll('[data-workflow-step]').forEach((item) => {
            const step = Number(item.dataset.workflowStep);
            const button = item.querySelector('[data-workflow-step-button]');
            const completed = step < currentStep;
            const active = step === currentStep;

            item.classList.toggle('workflow-step--completed', completed);
            item.classList.toggle('workflow-step--current', active);
            item.classList.toggle('workflow-step--pending', step > currentStep);
            button?.toggleAttribute('disabled', step > reachableStep);

            if (active) {
                button?.setAttribute('aria-current', 'step');
            } else {
                button?.removeAttribute('aria-current');
            }
        });
    };

    const updateSummary = () => {
        const providerText = provider?.value
            ? providerSearch?.value.trim()
            : 'Por seleccionar';
        const documentText = documentNumber?.value.trim() || 'Sin número externo';
        const currencyText = currency?.value === 'USD' ? 'Dólares (USD)' : 'Soles (PEN)';
        let dateText = '—';

        if (quoteDate?.value) {
            dateText = new Intl.DateTimeFormat('es-PE').format(
                new Date(quoteDate.value + 'T00:00:00')
            );
        }

        const productCount = Array.from(
            lines.querySelectorAll('[data-line-product]')
        ).filter((input) => input.value).length;

        setText('[data-context-provider], [data-review-provider]', providerText);
        setText('[data-context-document], [data-review-document]', documentText);
        setText('[data-context-currency], [data-review-currency]', currencyText);
        setText('[data-review-date]', dateText);
        setText(
            '[data-review-products]',
            productCount + (productCount === 1 ? ' producto' : ' productos')
        );
        setText('[data-review-total]', lastTotalText);
    };

    const showStep = (step, scroll = true) => {
        currentStep = Math.max(1, Math.min(3, Number(step)));
        reachableStep = Math.max(reachableStep, currentStep);

        panels.forEach((panel) => {
            panel.hidden = Number(panel.dataset.quoteStepPanel) !== currentStep;
        });

        paintWorkflow();
        updateSummary();
        if (entryMethods) {
            entryMethods.hidden = currentStep !== 1;
            if (entrySummary) {
                entrySummary.hidden = currentStep === 1;
                const label = entrySummary.querySelector('[data-quote-entry-label]');
                if (label) label.textContent = entryMethods.querySelector('.supplier-quote-entry-option.is-active strong')?.textContent || 'Ingreso manual';
            }
        }

        if (scroll) {
            const currentPanel = panels.find(
                (panel) => Number(panel.dataset.quoteStepPanel) === currentStep
            );
            currentPanel?.scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
                block: 'start',
            });
        }
    };

    const validateStep = (step) => {
        const panel = panels.find(
            (candidate) => Number(candidate.dataset.quoteStepPanel) === step
        );
        if (!panel) return true;

        const controls = Array.from(panel.querySelectorAll(
            'input:not([type="hidden"]), select, textarea'
        ));

        for (const control of controls) {
            if (!control.checkValidity()) {
                const line = control.closest('[data-supplier-quote-line]');
                if (line) {
                    const options = line.querySelector('[data-line-options]');
                    if (options?.contains(control)) supplierQuoteSetOptionsOpen(line, true);
                    line.hidden = false;
                }
                control.reportValidity();
                control.focus({ preventScroll: true });
                control.scrollIntoView({ block: 'center' });
                return false;
            }
        }

        return true;
    };

    const refreshIndexes = () => {
        lines.querySelectorAll('[data-supplier-quote-line]').forEach((line, index) => {
            const badge = line.querySelector('.supplier-quote-line__index span');
            if (badge) badge.textContent = index + 1;
            line.querySelector('[data-remove-supplier-quote-line]')?.setAttribute('aria-label', 'Quitar producto ' + (index + 1));
            supplierQuoteSetOptionsOpen(line, !line.querySelector('[data-line-options]')?.hidden);
        });
    };

    const allLines = () => Array.from(lines.querySelectorAll('[data-supplier-quote-line]'));
    const hasPrice = (line) => (line.querySelector('[data-line-price]')?.value || '').trim() !== '';

    const updateLineCount = () => {
        const items = allLines();
        const priced = items.filter(hasPrice).length;
        setText('[data-quote-priced-count], [data-quote-priced-summary]',
            `${priced} de ${items.length} productos con precio`);
        items.forEach((line) => {
            const hide = Boolean(unpricedFilter?.checked && hasPrice(line)
                && !line.contains(document.activeElement));
            line.hidden = hide;
        });
        if (unpricedEmpty) unpricedEmpty.hidden = !unpricedFilter?.checked
            || items.some((line) => !line.hidden);
    };

    const updateLineSummaries = () => {
        const items = allLines();
        const shared = wizard.querySelector('[data-quote-shared-observation]');
        const original = shared?.dataset.sharedObservation;
        const same = Boolean(original && items.length > 1 && items.every((line) =>
            line.querySelector('[data-line-observation]')?.value.trim() === original));
        if (shared) shared.hidden = !same;
        items.forEach((line) => supplierQuoteSyncLineBadges(line, same));
    };

    const syncLineVisuals = (line) => {
        const product = line.querySelector('[data-line-product]');
        const requested = line.querySelector('[data-line-requisition-detail]');
        const type = line.querySelector('[data-line-link-type]')?.value;
        const fixed = line.querySelector('[data-line-fixed-product]');
        const editor = line.querySelector('[data-line-product-editor]');
        const search = line.querySelector('[data-product-search]');
        const linked = type === 'SOLICITADO' && Boolean(requested?.value)
            && requested.selectedOptions?.[0]?.dataset.productId === product?.value;

        // Do not conceal a product that still needs confirmation after a PDF/Excel import.
        const showFixed = linked && Boolean(product?.value) && !line.dataset.editingProduct
            && line.querySelector('[data-line-link-confirmed]')?.value !== '0';
        if (fixed && editor) {
            fixed.hidden = !showFixed;
            editor.hidden = showFixed;
            if (search) search.required = !showFixed;
            if (showFixed) {
                const label = line.querySelector('[data-line-fixed-label]');
                const amount = line.querySelector('[data-line-fixed-requested]');
                if (label) label.textContent = search?.value || label.textContent;
                if (amount) amount.textContent = 'Solicitado: '
                    + Number(requested.selectedOptions?.[0]?.dataset.requestedQuantity || 0)
                        .toLocaleString('es-PE', { maximumFractionDigits: 3 })
                    + ' ' + (line.querySelector('[data-line-presentation]')?.dataset.baseUnit || 'UND');
            }
        }
        updateLineSummaries();
    };

    const updateDiscountValueLabel = (type, label, perUnit = false) => {
        if (!label) return;

        if (type === 'PORCENTAJE') {
            label.textContent = 'Porcentaje de descuento (%)';
        } else if (type === 'MONTO') {
            label.textContent = perUnit
                ? 'Monto por unidad (' + symbol() + ')'
                : 'Monto del descuento (' + symbol() + ')';
        } else {
            label.textContent = 'Valor del descuento';
        }
    };

    const syncLineDiscount = (line) => {
        const switchControl = line.querySelector('[data-line-discount-switch]');
        const mode = line.querySelector('[data-line-discount-mode]');
        const fields = line.querySelector('[data-line-discount-fields]');
        const type = line.querySelector('[data-line-discount-type]');
        const value = line.querySelector('[data-line-discount-value]');
        const answer = line.querySelector('[data-line-discount-answer]');
        const label = line.querySelector('[data-line-discount-value-label]');
        const active = Boolean(switchControl?.checked);

        if (mode) mode.value = active ? 'APLICAR' : 'SIN_DESCUENTO';
        if (fields) fields.hidden = !active;
        if (type) type.required = active;
        if (value) value.required = active;
        if (answer) answer.textContent = active ? 'Sí, lo detalla' : 'No';

        if (!active) {
            if (type) type.value = '';
            if (value) value.value = '';
        }

        updateDiscountValueLabel(type?.value, label, true);
    };

    const lineAmounts = (line) => {
        const quantity = roundTo(
            Number(line.querySelector('[data-line-quantity]')?.value || 0),
            3
        );
        const price = roundTo(
            Number(line.querySelector('[data-line-price]')?.value || 0),
            4
        );
        const discountMode = line.querySelector('[data-line-discount-mode]')?.value;
        const discountType = line.querySelector('[data-line-discount-type]')?.value;
        const discountValue = roundTo(
            Number(line.querySelector('[data-line-discount-value]')?.value || 0),
            4
        );
        const taxMode = line.querySelector('[data-line-tax-mode]')?.value;
        let offeredUnit = price;

        if (discountMode === 'APLICAR') {
            offeredUnit -= discountType === 'PORCENTAJE'
                ? price * (discountValue / 100)
                : discountValue;
        }

        offeredUnit = roundTo(Math.max(0, offeredUnit), 4);

        let baseUnit = offeredUnit;
        let taxUnit = 0;
        let totalUnit = offeredUnit;

        if (taxMode === 'INCLUIDO') {
            baseUnit = roundTo(offeredUnit / 1.18, 4);
            taxUnit = roundTo(offeredUnit - baseUnit, 4);
        } else if (taxMode === 'AGREGAR') {
            taxUnit = roundTo(baseUnit * 0.18, 4);
            totalUnit = roundTo(baseUnit * 1.18, 4);
        }

        return {
            base: roundTo(quantity * baseUnit, 4),
            tax: roundTo(quantity * taxUnit, 4),
            total: roundTo(quantity * totalUnit, 4),
        };
    };

    const updateReconciliation = ({ base, tax, total }) => {
        if (!reconciliation || documentTotal === null) return total;

        const roundCurrency = (value) => roundTo(value, 2);
        const systemTotal = roundCurrency(total);
        const declaredTotal = roundCurrency(documentTotal);
        const totalDifference = Math.abs(systemTotal - declaredTotal);
        const baseDifference = documentSubtotal === null
            ? 0
            : Math.abs(roundCurrency(base) - roundCurrency(documentSubtotal));
        const taxDifference = documentTax === null
            ? 0
            : Math.abs(roundCurrency(tax) - roundCurrency(documentTax));
        const sameCurrency = !documentCurrency || documentCurrency === currency?.value;
        const exactMatch = sameCurrency
            && totalDifference < 0.005
            && baseDifference <= 0.0100001
            && taxDifference <= 0.0100001;
        const roundingEligible = sameCurrency
            && totalDifference >= 0.005
            && totalDifference <= 0.0500001
            && baseDifference <= 0.0500001
            && taxDifference <= 0.0500001;
        const adjustment = roundingEligible
            ? roundCurrency(declaredTotal - systemTotal)
            : 0;
        const adjustmentConfirmed = roundingEligible && Boolean(roundingCheckbox?.checked);

        reconciliationMatches = exactMatch || adjustmentConfirmed;

        reconciliation.classList.toggle('is-match', reconciliationMatches);
        reconciliation.classList.toggle('has-difference', !reconciliationMatches);
        reconciliation.classList.toggle('has-adjustment', roundingEligible);
        if (roundingConfirmation) roundingConfirmation.hidden = !roundingEligible;
        if (roundingCheckbox) roundingCheckbox.required = roundingEligible;

        const status = reconciliation.querySelector('[data-reconciliation-status]');
        const message = reconciliation.querySelector('[data-reconciliation-message]');
        const difference = reconciliation.querySelector('[data-reconciliation-difference]');
        const documentOutput = reconciliation.querySelector('[data-document-total-output]');
        const systemOutput = reconciliation.querySelector('[data-system-total-output]');
        const adjustmentOutput = reconciliation.querySelector('[data-rounding-adjustment-output]');
        const reconciledOutput = reconciliation.querySelector('[data-reconciled-total-output]');
        const reviewStatus = wizard.querySelector('[data-review-status]');
        const reviewStatusText = wizard.querySelector('[data-review-status-text]');

        if (status) {
            status.textContent = exactMatch
                ? 'Importes conciliados'
                : (roundingEligible
                    ? (adjustmentConfirmed ? 'Conciliado con ajuste' : 'Ajuste pendiente')
                    : 'Requiere revisión');
        }
        if (documentOutput) documentOutput.textContent = documentMoney(documentTotal);
        if (systemOutput) systemOutput.textContent = money(total);
        if (difference) difference.textContent = money(totalDifference);
        if (adjustmentOutput) {
            adjustmentOutput.textContent = roundingEligible
                ? (adjustment >= 0 ? '+ ' : '- ') + documentMoney(Math.abs(adjustment))
                : documentMoney(0);
        }
        if (reconciledOutput) {
            reconciledOutput.textContent = exactMatch || roundingEligible
                ? documentMoney(documentTotal)
                : money(total);
        }
        reviewStatus?.classList.toggle('has-difference', !reconciliationMatches);
        reviewStatus?.classList.toggle('has-adjustment', roundingEligible);
        if (reviewStatusText) {
            reviewStatusText.textContent = exactMatch
                ? 'Listo para registrar'
                : (roundingEligible
                    ? (adjustmentConfirmed ? 'Ajuste confirmado' : 'Confirma el ajuste')
                    : 'Importes pendientes');
        }

        if (message) {
            if (!sameCurrency) {
                message.textContent = 'La moneda del formulario no coincide con la moneda detectada en el documento.';
            } else if (exactMatch) {
                message.textContent = 'El total, la base neta y el IGV coinciden con el documento del proveedor.';
            } else if (roundingEligible) {
                message.textContent = adjustmentConfirmed
                    ? 'Ajuste confirmado. Las líneas conservan su cálculo y el total final coincidirá con el documento.'
                    : 'La diferencia está dentro del máximo permitido. Revisa y confirma el ajuste de cabecera para continuar.';
            } else {
                message.textContent = 'La diferencia no corresponde a un redondeo permitido. Revisa el precio unitario, el IGV o los descuentos. El sistema no registrará la cotización mientras exista una diferencia.';
            }
        }

        wizard.querySelector('[data-submit-supplier-quote]')
            ?.setAttribute('aria-disabled', reconciliationMatches ? 'false' : 'true');

        return exactMatch || roundingEligible ? documentTotal : total;
    };

    const calculate = () => {
        let subtotal = 0;
        let taxBeforeGlobalDiscount = 0;

        lines.querySelectorAll('[data-supplier-quote-line]').forEach((line) => {
            const amounts = lineAmounts(line);
            subtotal += amounts.base;
            taxBeforeGlobalDiscount += amounts.tax;

            const baseOutput = line.querySelector('[data-line-base]');
            const taxOutput = line.querySelector('[data-line-tax]');
            const totalOutput = line.querySelector('[data-line-total]');
            if (baseOutput) baseOutput.textContent = money(amounts.base);
            if (taxOutput) taxOutput.textContent = money(amounts.tax);
            if (totalOutput) totalOutput.textContent = money(amounts.total);
        });

        subtotal = roundTo(subtotal, 4);
        taxBeforeGlobalDiscount = roundTo(taxBeforeGlobalDiscount, 4);

        let discount = 0;
        let discountFactor = 0;

        if (globalMode?.value === 'APLICAR') {
            const value = roundTo(Number(globalValue?.value || 0), 4);
            discount = globalType?.value === 'PORCENTAJE'
                ? subtotal * (value / 100)
                : value;
            discount = roundTo(Math.min(Math.max(0, discount), subtotal), 4);
            discountFactor = subtotal > 0 ? discount / subtotal : 0;
        }

        const netBase = roundTo(subtotal - discount, 4);
        const tax = roundTo(taxBeforeGlobalDiscount * (1 - discountFactor), 4);
        const total = roundTo(netBase + tax, 4);
        const finalTotal = updateReconciliation({ base: netBase, tax, total });
        lastTotalText = documentTotal !== null
            && (Math.abs(finalTotal - documentTotal) < 0.005)
            ? documentMoney(finalTotal)
            : money(finalTotal);

        setText('[data-quote-subtotal]', money(subtotal));
        setText('[data-quote-discount]', money(discount));
        setText('[data-quote-net-base]', money(netBase));
        setText('[data-quote-tax-total]', money(tax));
        setText('[data-quote-total]', money(total));
        setText('[data-review-total]', lastTotalText);
        updateSummary();
        updateLineCount();
        updateLineSummaries();
    };

    const fillPastedColumn = (start, text) => {
        const role = start.matches('[data-line-price]') ? 'price' : 'quantity';
        const selector = role === 'price' ? '[data-line-price]' : '[data-line-quantity]';
        const targets = allLines().slice(allLines().indexOf(start.closest('[data-supplier-quote-line]')));
        let filled = 0;
        const plan = supplierQuotePastePlan(text, targets.length);
        let skipped = plan.overflow;
        plan.values.forEach(({ position, value }) => {
            const target = targets[position]?.querySelector(selector);
            if (!target || value === null) { skipped += 1; return; }
            const previous = target.value;
            target.value = value;
            if (!target.checkValidity()) { target.value = previous; skipped += 1; return; }
            target.dispatchEvent(new Event('input', { bubbles: true }));
            filled += 1;
        });
        if (pasteStatus) {
            pasteStatus.textContent = `${filled} ${role === 'price' ? 'precios' : 'cantidades'} pegados.`
                + (skipped ? ` ${skipped} valores omitidos o sin fila disponible.` : '');
        }
    };

    const bindLine = (line) => {
        line.addEventListener('focusout', () => queueMicrotask(updateLineCount));
        line.querySelectorAll('input, select').forEach((input) => {
            input.addEventListener('input', calculate);
            input.addEventListener('change', calculate);
        });

        line.querySelector('[data-line-discount-switch]')?.addEventListener(
            'change',
            () => {
                syncLineDiscount(line);
                syncLineVisuals(line);
                calculate();
            }
        );

        supplierQuoteBindOptionsToggle(line);
        line.querySelector('[data-line-change-product]')?.addEventListener('click', () => {
            line.dataset.editingProduct = '1';
            syncLineVisuals(line);
            line.querySelector('[data-product-search]')?.focus();
        });
        line.addEventListener('change', (event) => {
            if (event.target.matches('[data-line-link-type], [data-line-requisition-detail], [data-line-presentation], [data-line-discount-type]')) {
                queueMicrotask(() => syncLineVisuals(line));
            }
        });
        line.addEventListener('supplier-product-selected', () => {
            const editor = line.querySelector('[data-line-product-editor]');
            const focusInEditor = editor?.contains(document.activeElement);
            delete line.dataset.editingProduct;
            queueMicrotask(() => {
                syncLineVisuals(line);
                if (focusInEditor && editor.hidden) {
                    line.querySelector('[data-line-quantity]')?.focus({ preventScroll: true });
                }
            });
        });
        line.addEventListener('supplier-product-cleared', () => {
            delete line.dataset.editingProduct;
            queueMicrotask(() => syncLineVisuals(line));
        });
        line.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' || !event.target.matches('[data-line-quantity], [data-line-price]')) return;
            event.preventDefault();
            const type = event.target.matches('[data-line-price]') ? '[data-line-price]' : '[data-line-quantity]';
            allLines().slice(allLines().indexOf(line) + 1)
                .find((candidate) => !candidate.hidden)?.querySelector(type)?.focus();
        });
        line.addEventListener('paste', (event) => {
            if (!event.target.matches('[data-line-quantity], [data-line-price]')) return;
            const pasted = event.clipboardData?.getData('text/plain') || '';
            if (!/[\r\n]/.test(pasted)) return;
            event.preventDefault();
            fillPastedColumn(event.target, pasted);
        });

        line.querySelector('[data-line-discount-type]')?.addEventListener(
            'change',
            (event) => {
                updateDiscountValueLabel(
                    event.target.value,
                    line.querySelector('[data-line-discount-value-label]'),
                    true
                );
            }
        );

        line.querySelector('[data-remove-supplier-quote-line]')?.addEventListener(
            'click',
            () => {
                const all = lines.querySelectorAll('[data-supplier-quote-line]');

                if (all.length === 1) {
                    line.querySelectorAll('input').forEach((input) => {
                        if (input.type === 'checkbox') {
                            input.checked = false;
                        } else {
                            input.value = input.matches('[data-line-quantity]') ? 1 : '';
                        }
                    });
                    const product = line.querySelector('[data-line-product]');
                    const taxMode = line.querySelector('[data-line-tax-mode]');
                    const discountType = line.querySelector('[data-line-discount-type]');
                    if (product) product.value = '';
                    if (taxMode) taxMode.value = 'AGREGAR';
                    if (discountType) discountType.value = '';
                    const productSearch = line.querySelector('[data-product-search]');
                    const productClear = line.querySelector('[data-product-clear]');
                    if (productSearch) productSearch.dataset.selectedLabel = '';
                    if (productClear) productClear.hidden = true;
                    syncLineDiscount(line);
                    delete line.dataset.editingProduct;
                    syncLineVisuals(line);
                } else {
                    line.remove();
                    refreshIndexes();
                }

                calculate();
            }
        );

        syncLineDiscount(line);
        syncLineVisuals(line);
    };

    lines.querySelectorAll('[data-supplier-quote-line]').forEach(bindLine);

    form.addEventListener('submit', (event) => {
        if (currentStep < 3 || documentTotal === null || reconciliationMatches) return;

        event.preventDefault();
        showStep(3, false);
        reconciliation?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        reconciliation?.querySelector('[data-reconciliation-message]')?.focus?.();
    });

    addButton?.addEventListener('click', () => {
        const html = template.innerHTML
            .replaceAll('__INDEX__', nextIndex)
            .replaceAll('__NUMBER__', nextIndex + 1);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const line = wrapper.firstElementChild;
        lines.appendChild(line);
        bindLine(line);
        nextIndex += 1;
        refreshIndexes();
        calculate();
    });

    const syncCurrency = () => {
        const usd = currency?.value === 'USD';
        if (exchangeField) exchangeField.hidden = !usd;
        if (exchangeInput) {
            exchangeInput.disabled = !usd;
            exchangeInput.required = usd;
            if (!usd) exchangeInput.value = '';
        }

        lines.querySelectorAll('[data-supplier-quote-line]').forEach((line) => {
            updateDiscountValueLabel(
                line.querySelector('[data-line-discount-type]')?.value,
                line.querySelector('[data-line-discount-value-label]'),
                true
            );
        });
        updateDiscountValueLabel(globalType?.value, globalValueLabel);
        calculate();
    };

    const syncGlobalDiscount = () => {
        const active = Boolean(globalSwitch?.checked);
        if (globalMode) globalMode.value = active ? 'APLICAR' : 'SIN_DESCUENTO';
        if (globalFields) globalFields.hidden = !active;
        if (globalType) globalType.required = active;
        if (globalValue) globalValue.required = active;
        if (globalAnswer) globalAnswer.textContent = active ? 'Sí, lo detalla' : 'No';

        if (!active) {
            if (globalType) globalType.value = '';
            if (globalValue) globalValue.value = '';
        }

        updateDiscountValueLabel(globalType?.value, globalValueLabel);
        calculate();
    };

    currency?.addEventListener('change', syncCurrency);
    provider?.addEventListener('change', updateSummary);
    documentNumber?.addEventListener('input', updateSummary);
    quoteDate?.addEventListener('change', updateSummary);
    globalSwitch?.addEventListener('change', syncGlobalDiscount);
    globalType?.addEventListener('change', () => {
        updateDiscountValueLabel(globalType.value, globalValueLabel);
        calculate();
    });
    globalValue?.addEventListener('input', calculate);
    roundingCheckbox?.addEventListener('change', calculate);
    unpricedFilter?.addEventListener('change', updateLineCount);
    wizard.querySelector('.supplier-quote-economic-breakdown')?.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const disclosure = event.currentTarget;
        if (!disclosure.open) return;
        event.preventDefault();
        disclosure.open = false;
        disclosure.querySelector('summary')?.focus();
    });
    entrySummary?.querySelector('[data-quote-change-entry]')?.addEventListener('click', () => {
        if (!entryMethods) return;
        entryMethods.hidden = false;
        entryMethods.scrollIntoView({ block: 'start' });
        entryMethods.querySelector('.supplier-quote-entry-option.is-active')?.focus();
    });
    wizard.querySelector('[data-quote-apply-tax]')?.addEventListener('click', () => {
        const value = wizard.querySelector('[data-quote-bulk-tax]')?.value;
        let affected = 0;
        allLines().forEach((line) => {
            const select = line.querySelector('[data-line-tax-mode]');
            if (!select) return;
            if (select.value !== value) affected += 1;
            select.value = value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
        if (pasteStatus) pasteStatus.textContent = `Tratamiento de IGV actualizado en ${affected} de ${allLines().length} líneas.`
            + (unpricedFilter?.checked ? ' También se incluyeron las líneas ocultas por el filtro.' : '');
    });
    wizard.querySelector('[data-quote-use-requested]')?.addEventListener('click', () => {
        let applied = 0;
        let skipped = 0;
        allLines().forEach((line) => {
            const select = line.querySelector('[data-line-requisition-detail]');
            const quantity = line.querySelector('[data-line-quantity]');
            const presentation = line.querySelector('[data-line-presentation]');
            const factor = Number(presentation?.selectedOptions?.[0]?.dataset.factor || 1);
            const requested = Number(select?.selectedOptions?.[0]?.dataset.requestedQuantity);
            if (!select?.value || !quantity || !Number.isFinite(requested) || factor <= 0) return;
            const amount = supplierQuoteRequestedAmount(requested, factor);
            const previous = quantity.value;
            quantity.value = String(amount.quantity);
            if (!quantity.checkValidity()) {
                quantity.value = previous;
                skipped += 1;
                return;
            }
            if (amount.useBase && presentation) {
                // Two decimal presentation quantities cannot always represent the exact requested base amount.
                presentation.value = '';
                presentation.dispatchEvent(new Event('change', { bubbles: true }));
            }
            quantity.value = String(amount.quantity);
            quantity.dispatchEvent(new Event('input', { bubbles: true }));
            quantity.dispatchEvent(new Event('change', { bubbles: true }));
            applied += 1;
        });
        if (pasteStatus) pasteStatus.textContent = `Cantidad solicitada aplicada a ${applied} líneas vinculadas.`
            + (skipped ? ` ${skipped} cantidades requieren revisión manual.` : '')
            + (unpricedFilter?.checked ? ' Se incluyeron las líneas ocultas por el filtro.' : '');
    });

    lines.addEventListener('supplier-quote-requested-lines-ready', (event) => {
        const line = event.target.closest('[data-supplier-quote-line]');
        if (line) syncLineVisuals(line);
    });

    wizard.querySelectorAll('[data-next-quote-step]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!validateStep(currentStep)) return;
            showStep(Number(button.dataset.nextQuoteStep));
        });
    });

    wizard.querySelectorAll('[data-previous-quote-step]').forEach((button) => {
        button.addEventListener('click', () => {
            showStep(Number(button.dataset.previousQuoteStep));
        });
    });

    stepper?.querySelectorAll('[data-workflow-step-button]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = Number(button.dataset.stepNumber);
            if (target <= reachableStep && target !== currentStep) {
                showStep(target);
            }
        });
    });

    form.addEventListener('submit', (event) => {
        if (currentStep < 3) {
            event.preventDefault();
            if (validateStep(currentStep)) {
                showStep(currentStep + 1);
            }
        }
    });

    syncGlobalDiscount();
    syncCurrency();
    calculate();
    showStep(currentStep, false);
    if (unpricedFilter) unpricedFilter.disabled = false;
    ['[data-quote-apply-tax]', '[data-quote-use-requested]'].forEach((selector) => {
        const button = wizard.querySelector(selector);
        if (button) button.disabled = false;
    });
});
