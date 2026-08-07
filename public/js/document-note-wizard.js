document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-document-note-wizard]').forEach((wizard) => {
        const stepper = wizard.querySelector('[data-workflow-stepper]');
        const form = wizard.querySelector('[data-note-wizard-form]');
        const sections = Array.from(wizard.querySelectorAll('[data-flow-step-section]'));
        const originContext = wizard.querySelector('[data-note-origin-context]');
        const actions = wizard.querySelector('[data-note-wizard-actions]');
        const previousButton = wizard.querySelector('[data-note-wizard-prev]');
        const nextButton = wizard.querySelector('[data-note-wizard-next]');
        const submitButton = wizard.querySelector('[data-note-wizard-submit]');

        if (!stepper || sections.length === 0) return;

        let currentStep = Number(wizard.dataset.initialStep || stepper.dataset.currentStep || 1);
        let reachableStep = currentStep;

        const sectionFor = (step) => sections.find(
            (section) => Number(section.dataset.flowStepSection) === step
        );

        const paintStepper = () => {
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

        const paintActions = () => {
            if (!actions) return;

            actions.hidden = currentStep < 2;
            if (currentStep < 2) return;

            if (previousButton) {
                previousButton.hidden = false;
                previousButton.textContent = currentStep === 2
                    ? 'Cambiar origen'
                    : (currentStep === 3 ? 'Volver a datos' : 'Volver a productos');
            }

            if (nextButton) {
                nextButton.hidden = currentStep >= 4;
                nextButton.textContent = currentStep === 2
                    ? (nextButton.dataset.step2Label || 'Continuar a productos')
                    : (nextButton.dataset.step3Label || 'Revisar confirmación');
            }

            if (submitButton) {
                submitButton.hidden = currentStep !== 4;
            }
        };

        const showStep = (step, shouldScroll = true) => {
            if (step < 1 || step > 4 || step > reachableStep) return;

            currentStep = step;
            sections.forEach((section) => {
                section.hidden = Number(section.dataset.flowStepSection) !== currentStep;
            });

            if (originContext) {
                originContext.hidden = currentStep === 1;
            }

            paintStepper();
            paintActions();

            if (shouldScroll) {
                const target = sectionFor(currentStep) || stepper;
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        };

        const validateNativeFields = (section) => {
            if (!section) return true;

            const fields = Array.from(
                section.querySelectorAll('input, select, textarea')
            ).filter((field) => !field.disabled && field.type !== 'hidden');

            for (const field of fields) {
                if (!field.checkValidity()) {
                    field.reportValidity();
                    field.focus({ preventScroll: true });
                    return false;
                }
            }

            return true;
        };

        const validateProducts = (section) => {
            if (!section) return false;

            const quantities = Array.from(section.querySelectorAll(
                '[data-output-quantity], [data-entry-quantity]'
            ));
            const selected = quantities.filter((input) => Number(input.value || 0) > 0);

            if (selected.length === 0) {
                const first = quantities[0];
                if (first) {
                    first.setCustomValidity('Ingresa al menos una cantidad mayor que 0.');
                    first.reportValidity();
                    first.focus({ preventScroll: true });
                    window.setTimeout(() => first.setCustomValidity(''), 100);
                }
                return false;
            }

            return validateNativeFields(section);
        };

        const validateStep = (step) => {
            const section = sectionFor(step);
            if (step === 3) return validateProducts(section);
            return validateNativeFields(section);
        };

        const advance = () => {
            if (currentStep >= 4 || !validateStep(currentStep)) return;
            reachableStep = Math.max(reachableStep, currentStep + 1);
            showStep(currentStep + 1);
        };

        nextButton?.addEventListener('click', advance);
        previousButton?.addEventListener('click', () => {
            if (currentStep > 1) showStep(currentStep - 1);
        });

        stepper.querySelectorAll('[data-workflow-step-button]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = Number(button.dataset.stepNumber);
                if (target <= reachableStep && target !== currentStep) {
                    showStep(target);
                }
            });
        });

        form?.addEventListener('submit', (event) => {
            if (currentStep < 4) {
                event.preventDefault();
                advance();
            }
        }, true);

        showStep(currentStep, false);
    });
});
