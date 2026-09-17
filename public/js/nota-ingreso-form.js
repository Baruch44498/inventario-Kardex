document.addEventListener('DOMContentLoaded', () => {
    const originForm = document.querySelector('[data-entry-origin-form]');
    const originType = originForm?.querySelector('[data-entry-origin-type]');
    originType?.addEventListener('change', () => originForm.submit());

    document.querySelector('[data-fill-pending]')?.addEventListener('click', () => {
        document.querySelectorAll('[data-entry-quantity]').forEach((input) => {
            const measure = input.closest('[data-reception-measure]');
            const mode = measure?.querySelector('[data-reception-mode]')?.value;
            input.value = measure
                ? (mode === 'PRESENTACION'
                    ? measure.dataset.pendingPresentation
                    : measure.dataset.pendingBase)
                : (input.dataset.pendingValue || '0');
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    document.querySelectorAll('[data-reception-measure]').forEach((measure) => {
        const mode = measure.querySelector('[data-reception-mode]');
        const input = measure.querySelector('[data-entry-quantity]');
        const help = measure.querySelector('[data-reception-conversion]');
        if (!mode || !input || !help) return;

        const refresh = () => {
            const byPresentation = mode.value === 'PRESENTACION';
            const factor = Number(measure.dataset.factor || 1);
            const entered = Number(input.value || 0);
            const baseQuantity = byPresentation ? entered * factor : entered;
            input.max = byPresentation
                ? measure.dataset.pendingPresentation
                : measure.dataset.pendingBase;
            input.step = byPresentation ? '0.001' : measure.dataset.baseStep;
            help.textContent = byPresentation
                ? `1 ${measure.dataset.presentationName} = ${factor} ${measure.dataset.baseUnit}. Ingresarán ${baseQuantity.toFixed(2)} ${measure.dataset.baseUnit} al Kardex.`
                : `Ingreso directo en ${measure.dataset.baseUnit}.`;
        };

        mode.addEventListener('change', () => {
            input.value = '0';
            refresh();
        });
        input.addEventListener('input', refresh);
        refresh();
    });

    document.querySelector('[data-invoice-selector]')?.addEventListener('change', (event) => {
        const selector = event.currentTarget;
        const url = new URL(selector.dataset.reloadUrl, window.location.origin);
        if (selector.value) url.searchParams.set('factura_proveedor_id', selector.value);
        window.location.assign(url.toString());
    });
});
