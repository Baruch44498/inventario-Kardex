document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-alert-bulk-form]');
    if (!form) return;

    const master = document.querySelector('[data-select-visible-alerts]');
    const checkboxes = [...document.querySelectorAll('[data-alert-checkbox]')];
    const count = form.querySelector('[data-alert-selection-count]');
    const submit = form.querySelector('[data-selected-alerts-submit]');
    const start = form.querySelector('[data-alert-selection-start]');
    const cancel = form.querySelector('[data-alert-selection-cancel]');
    const selectionControls = [...document.querySelectorAll('[data-alert-selection-control]')];

    const sync = () => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
        if (count) count.textContent = `${selected} seleccionada${selected === 1 ? '' : 's'}`;
        if (submit) submit.disabled = selected === 0;

        if (master) {
            master.checked = checkboxes.length > 0 && selected === checkboxes.length;
            master.indeterminate = selected > 0 && selected < checkboxes.length;
        }
    };

    master?.addEventListener('change', () => {
        checkboxes.forEach((checkbox) => { checkbox.checked = master.checked; });
        sync();
    });
    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', sync));

    const setSelectionMode = (active) => {
        selectionControls.forEach((control) => { control.hidden = !active; });
        if (count) count.hidden = !active;
        if (submit) submit.hidden = !active;
        if (cancel) cancel.hidden = !active;
        if (start) start.hidden = active;

        if (!active) {
            checkboxes.forEach((checkbox) => { checkbox.checked = false; });
            if (master) master.checked = false;
        }

        sync();
        if (active) checkboxes[0]?.focus();
    };

    start?.addEventListener('click', () => setSelectionMode(true));
    cancel?.addEventListener('click', () => setSelectionMode(false));
    sync();
});
