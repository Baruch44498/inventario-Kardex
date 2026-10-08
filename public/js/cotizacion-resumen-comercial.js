(() => {
    'use strict';

    const documents = document.querySelector('[data-commercial-documents]');
    if (documents) {
        document.addEventListener('pointerdown', (event) => {
            if (documents.open && !documents.contains(event.target)) documents.open = false;
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && documents.open) {
                documents.open = false;
                documents.querySelector('summary')?.focus();
            }
        });
    }

    const form = document.querySelector('[data-commercial-price-form]');
    if (!form) return;

    const price = form.querySelector('#precio_final_pactado');
    const preview = form.querySelector('[data-price-preview]');
    const cost = Number(form.dataset.priceCostNet);
    const currentTotal = Number(form.dataset.priceCurrentTotal);
    const currency = form.dataset.priceCurrency === 'USD' ? 'US$' : 'S/';
    const number = (value) => new Intl.NumberFormat('es-PE', {
        minimumFractionDigits: 2, maximumFractionDigits: 2,
    }).format(value);

    const estimate = () => {
        const entered = Number(price.value);
        const gross = price.value === '' ? currentTotal : entered;
        if (!Number.isFinite(gross) || gross <= 0) {
            preview.textContent = 'Ingresa un total válido para ver la utilidad.';
            return null;
        }
        const net = gross / 1.18;
        const profit = net - cost;
        const marginCost = cost > 0 ? `${number(profit / cost * 100)} %` : 'N/D';
        const marginSale = net > 0 ? `${number(profit / net * 100)} %` : 'N/D';
        preview.textContent = `Venta sin IGV: ${currency} ${number(net)}. Utilidad estimada: ${currency} ${number(profit)}. `
            + `Margen sobre costo: ${marginCost}. Margen sobre venta: ${marginSale}.`;
        return net;
    };

    price.addEventListener('input', estimate);
    // hidroil-ui.js registra la confirmación en burbuja. Esta fase de captura
    // deja pasar importes sin pérdida y conserva el bypass del modal al confirmar.
    form.addEventListener('submit', () => {
        if (form.dataset.confirmBypass === 'true') return;
        const net = estimate();
        if (price.value === '' || net === null || net >= cost) {
            form.dataset.confirmBypass = 'true';
        }
    }, true);
    estimate();
})();
