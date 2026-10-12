(() => {
    'use strict';

    document.querySelectorAll('[data-receivables-cobro-form]').forEach((form) => {
        const amount = form.querySelector('[data-receivables-amount]');
        const preview = form.querySelector('[data-receivables-preview]');
        const fill = form.querySelector('[data-receivables-fill]');
        if (!amount || !preview || !fill) return;

        const maximum = Number(amount.max);
        if (!Number.isFinite(maximum) || maximum <= 0) return;

        const currency = form.dataset.currency === 'USD' ? 'US$' : 'S/';
        const format = new Intl.NumberFormat('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 4,
        });

        const update = () => {
            const entered = amount.value.trim();
            const value = Number(entered);
            if (entered !== '' && (!Number.isFinite(value) || value < 0 || value > maximum)) {
                preview.textContent = 'Revisa el monto: debe estar dentro del saldo disponible.';
                return;
            }

            const remaining = entered === ''
                ? maximum
                : Math.max(0, Math.round((maximum - value) * 10000) / 10000);
            preview.textContent = `Saldo después de este cobro: ${currency} ${format.format(remaining)}`;
        };

        fill.hidden = false;
        fill.addEventListener('click', () => {
            amount.value = amount.max;
            update();
            amount.focus();
        });
        amount.addEventListener('input', update);
        update();

        const invalid = form.querySelector('[aria-invalid="true"]');
        if (invalid && document.activeElement === document.body) {
            invalid.focus({ preventScroll: true });
            invalid.scrollIntoView({ block: 'center', behavior: 'auto' });
        }
    });
})();
