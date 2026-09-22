document.addEventListener('DOMContentLoaded', () => {
    const wizard = document.querySelector('[data-proforma-wizard]');
    const form = wizard?.closest('form');
    if (!wizard || !form) return;

    const stepper = wizard.querySelector('[data-workflow-stepper]');
    const panels = Array.from(wizard.querySelectorAll('[data-proforma-step-panel]'));
    const finalActions = wizard.querySelector('[data-proforma-final-actions]');
    let current = Math.max(1, Math.min(3, Number(wizard.dataset.initialStep) || 1));
    let reachable = current;

    // Los controles de los pasos ocultos siguen siendo parte del POST. Al enviar,
    // se revisan aquí para poder mostrar el paso que contiene el primer error.
    form.noValidate = true;
    wizard.classList.add('proforma-wizard--active');

    const show = (step, focus = false) => {
        current = step;
        reachable = Math.max(reachable, current);

        panels.forEach((panel) => {
            panel.hidden = Number(panel.dataset.proformaStepPanel) !== current;
        });
        finalActions.hidden = current !== 3;

        stepper.dataset.currentStep = String(current);
        stepper.querySelectorAll('[data-workflow-step]').forEach((item) => {
            const number = Number(item.dataset.workflowStep);
            const button = item.querySelector('[data-workflow-step-button]');
            item.classList.toggle('workflow-step--completed', number < current);
            item.classList.toggle('workflow-step--current', number === current);
            item.classList.toggle('workflow-step--pending', number > current);
            button.disabled = number > reachable;
            if (number === current) button.setAttribute('aria-current', 'step');
            else button.removeAttribute('aria-current');
        });

        if (focus) {
            const heading = panels[current - 1].querySelector('h2');
            heading?.setAttribute('tabindex', '-1');
            heading?.focus({ preventScroll: true });
            stepper.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
    };

    const firstInvalid = (panel) => {
        for (const box of panel.querySelectorAll('[data-remote-combobox]')) {
            const search = box.querySelector('[data-remote-combobox-search]');
            const value = box.querySelector('[data-remote-combobox-value]');
            if (!search || !value) continue;
            search.setCustomValidity(value.value ? '' :
                search.value.trim() ? 'Selecciona una opción de los resultados.' : 'Selecciona una opción.');
        }

        return Array.from(panel.querySelectorAll('input:not([type="hidden"]), select, textarea'))
            .find((control) => !control.checkValidity());
    };

    const validate = (step) => {
        const invalid = firstInvalid(panels[step - 1]);
        if (!invalid) return true;
        show(step);
        invalid.reportValidity();
        invalid.focus();
        return false;
    };

    wizard.querySelectorAll('[data-proforma-next]').forEach((button) => {
        button.addEventListener('click', () => {
            if (validate(current)) show(current + 1, true);
        });
    });

    wizard.querySelectorAll('[data-proforma-previous]').forEach((button) => {
        button.addEventListener('click', () => show(current - 1, true));
    });

    stepper.querySelectorAll('[data-workflow-step-button]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = Number(button.dataset.stepNumber);
            if (target <= reachable && (target <= current || validate(current))) show(target, true);
        });
    });

    form.addEventListener('submit', (event) => {
        for (let step = 1; step <= 3; step += 1) {
            if (!validate(step)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
        }
        show(3);
    }, { capture: true });

    show(current);
});
