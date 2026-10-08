(() => {
    'use strict';

    const form = document.querySelector('[data-commercial-currency-form]');
    if (!form) return;

    const rate = form.querySelector('[data-currency-rate]');
    const target = form.querySelector('[data-currency-target]');
    const price = form.querySelector('[data-currency-price]');
    const preview = form.querySelector('[data-currency-preview]');
    const afterTotal = form.querySelector('[data-currency-after-total]');
    const afterProfit = form.querySelector('[data-currency-after-profit]');
    const afterRate = form.querySelector('[data-currency-after-rate]');
    const equivalentNode = form.querySelector('[data-currency-equivalent]');
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
            afterTotal.textContent = 'Ingresa TC';
            afterProfit.textContent = '—';
            afterRate.textContent = '—';
            equivalentNode.textContent = 'Ingresa TC';
            preview.textContent = 'Ingresa un tipo de cambio válido para ver la equivalencia.';
            return;
        }

        const equivalent = sourceCurrency === 'PEN'
            ? sourceTotal / exchange : sourceTotal * exchange;
        if (autoPrice) price.value = equivalent.toFixed(2);

        const agreed = Number(price.value);
        const validPrice = price.value !== '' && Number.isFinite(agreed) && agreed > 0;
        const profit = validPrice ? agreed / 1.18 - targetCostNet : null;
        afterTotal.textContent = validPrice ? `${symbol(currency)} ${format(agreed)}` : 'Ingresa el total';
        afterProfit.textContent = profit !== null ? `${symbol(currency)} ${format(profit)}` : '—';
        afterRate.textContent = format(exchange);
        equivalentNode.textContent = `${symbol(currency)} ${format(equivalent)}`;
        preview.textContent = `Actual: ${symbol(sourceCurrency)} ${format(sourceTotal)}. `
            + `Referencia al TC: ${symbol(currency)} ${format(equivalent)}. `
            + (validPrice
                ? `Precio a pactar: ${symbol(currency)} ${format(agreed)}.`
                : 'Ingresa el precio final que aceptó el cliente.')
            + (profit !== null ? ` Utilidad estimada antes de otros gastos: ${symbol(currency)} ${format(profit)}.` : '');
    };

    price.addEventListener('input', () => {
        autoPrice = false;
        update();
    });
    rate.addEventListener('input', update);
    update();
})();
