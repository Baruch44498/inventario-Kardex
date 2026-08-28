document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-toggle-new-component]');
    const form = document.querySelector('[data-new-component-form]');

    if (!toggle || !form) return;

    const update = () => {
        form.hidden = !toggle.checked;

        if (toggle.checked) {
            form.querySelector('select, input:not([type="hidden"]), textarea')?.focus();
        }
    };

    toggle.addEventListener('change', update);
    update();
});
