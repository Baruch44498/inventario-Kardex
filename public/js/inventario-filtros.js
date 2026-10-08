document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-inventory-filters]');
    const estado = form?.querySelector('[data-inventory-state-select]');

    if (!form || !estado) return;

    estado.addEventListener('change', () => {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });
});
