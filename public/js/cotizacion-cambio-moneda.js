(() => {
    'use strict';

    const form = document.querySelector('[data-commercial-currency-form]');
    if (!form) return;

    const rate = form.querySelector('[data-currency-rate]');
    const target = form.querySelector('[data-currency-target]');
    const price = form.querySelector('[data-currency-price]');
    const preview = form.querySelector('[data-currency-preview]');
    const sourceCurrency = form.dataset.sourceCurrency;
    const sourceTotal = Number(form.dataset.sourceTotal);
    const targetCostNet = Number(form.dataset.targetCostNet);
    const symbol = (currency) => currency === 'USD' ? 'US$' : 'S/';
    const format = (value) => new Intl.NumberFormat('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
    let autoPrice = price.dataset.currencyAuto === 'true';

    const update = () => {
        const exchange = Number(rate.value);
        const currency = target.value;
        if (!Number.isFinite(exchange) || exchange <= 0 || !Number.isFinite(sourceTotal)) {
            preview.textContent = 'Ingresa un tipo de cambio válido para ver la equivalencia.';
            return;
        }

        const equivalent = sourceCurrency === 'PEN'
            ? sourceTotal / exchange : sourceTotal * exchange;
        if (autoPrice) price.value = equivalent.toFixed(2);

        const agreed = Number(price.value);
        const profit = price.value !== '' && Number.isFinite(agreed) && agreed > 0
            ? ` Utilidad estimada antes de otros gastos: ${symbol(currency)} ${format(agreed / 1.18 - targetCostNet)}.`
            : '';
        preview.textContent = `Actual: ${symbol(sourceCurrency)} ${format(sourceTotal)}. `
            + `Referencia al TC: ${symbol(currency)} ${format(equivalent)}. `
            + (price.value !== '' && Number.isFinite(agreed) && agreed > 0
                ? `Precio a pactar: ${symbol(currency)} ${format(agreed)}.`
                : 'Ingresa el precio final que aceptó el cliente.')
            + profit;
    };

    price.addEventListener('input', () => {
        autoPrice = false;
        update();
    });
    rate.addEventListener('input', update);
    update();
})();
