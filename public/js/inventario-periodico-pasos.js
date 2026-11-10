(() => {
    'use strict';

    const root = document.querySelector('.periodic-inventory-show-page');

    if (!root) {
        return;
    }

    const steps = Array.from(root.querySelectorAll('[data-periodic-step]'));
    const panels = Array.from(root.querySelectorAll('[data-periodic-panel]'));

    if (steps.length === 0 || panels.length === 0) {
        return;
    }

    const scrollToPanel = (panel) => {
        const topbarHeight = document.querySelector('.topbar')?.getBoundingClientRect().height ?? 0;
        const gap = parseFloat(getComputedStyle(root).getPropertyValue('--space-3')) || 12;
        const top = window.scrollY + panel.getBoundingClientRect().top - topbarHeight - gap;

        window.scrollTo({
            top: Math.max(0, top),
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        });
    };

    const activate = (target, moveViewport = false) => {
        const activePanel = panels.find((panel) => panel.dataset.periodicPanel === target);

        if (!activePanel) {
            return;
        }

        panels.forEach((panel) => {
            panel.hidden = panel !== activePanel;
        });

        steps.forEach((step) => {
            const selected = step.dataset.periodicStep === target;

            step.classList.toggle('periodic-inventory-step--active', selected);
            step.setAttribute('aria-selected', selected ? 'true' : 'false');
            step.setAttribute('tabindex', selected ? '0' : '-1');
        });

        if (moveViewport) {
            scrollToPanel(activePanel);
        }
    };

    steps.forEach((step, index) => {
        step.addEventListener('click', () => {
            activate(step.dataset.periodicStep, true);
        });

        step.addEventListener('keydown', (event) => {
            let nextIndex = null;

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                nextIndex = (index + 1) % steps.length;
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                nextIndex = (index - 1 + steps.length) % steps.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = steps.length - 1;
            }

            if (nextIndex === null) {
                return;
            }

            event.preventDefault();
            steps[nextIndex].focus();
            activate(steps[nextIndex].dataset.periodicStep);
        });
    });

    const initialStep = steps.find((step) => step.getAttribute('aria-selected') === 'true') || steps[0];

    activate(initialStep.dataset.periodicStep);
})();
