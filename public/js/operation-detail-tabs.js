(() => {
    'use strict';

    const initOperationDetailTabs = () => {
        const root = document.querySelector('[data-operation-tabs-root]');
        const tabList = root?.querySelector('[data-operation-tabs]');

        if (!root || !tabList || root.dataset.operationTabsReady === 'true') {
            return;
        }

        const tabs = new Map(
            [...tabList.querySelectorAll('[data-operation-tab]')]
                .map((button) => [button.dataset.operationTab, button])
        );
        const panels = new Map(
            [...root.querySelectorAll('[data-operation-panel]')]
                .map((panel) => [panel.dataset.operationPanel, panel])
        );

        if (tabs.size === 0 || panels.size === 0) return;

        root.dataset.operationTabsReady = 'true';
        root.classList.add('operation-page--tabbed');

        const hashToTab = (hash) => {
            const normalized = String(hash || '').replace(/^#/, '');
            if (!normalized) return null;

            const target = document.getElementById(normalized);
            const explicitPanel = target?.closest('[data-operation-panel]');
            if (explicitPanel?.dataset.operationPanel) {
                return explicitPanel.dataset.operationPanel;
            }

            const aliases = {
                contexto: 'resumen',
                resumen: 'resumen',
                'avance-operativo': 'ejecucion',
                'costos-directos': 'ejecucion',
                ejecucion: 'ejecucion',
                'documento-origen': 'materiales',
                'materiales-requeridos': 'materiales',
                'comparacion-materiales': 'materiales',
                materiales: 'materiales',
                'reservas-materiales': 'reservas',
                reservas: 'reservas',
                'herramientas-en-uso': 'herramientas',
                herramientas: 'herramientas',
                'requerimientos-compra': 'abastecimiento',
                abastecimiento: 'abastecimiento',
            };

            return aliases[normalized] || null;
        };

        const activate = (tabName, { focus = false, updateHash = false } = {}) => {
            if (!tabs.has(tabName) || !panels.has(tabName)) return false;

            tabs.forEach((button, name) => {
                const active = name === tabName;
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                button.tabIndex = active ? 0 : -1;
            });

            panels.forEach((panel, name) => {
                panel.hidden = name !== tabName;
            });

            if (focus) {
                tabs.get(tabName).focus({ preventScroll: true });
            }

            if (updateHash) {
                const nextHash = `#${tabName}`;
                if (window.location.hash !== nextHash) {
                    window.history.replaceState(null, '', nextHash);
                }
            }

            return true;
        };

        const revealHashTarget = () => {
            const tabName = hashToTab(window.location.hash);
            if (!tabName || !activate(tabName)) return;

            const targetId = window.location.hash.replace(/^#/, '');
            const target = targetId ? document.getElementById(targetId) : null;

            if (target && !target.matches('[data-operation-panel]')) {
                window.requestAnimationFrame(() => {
                    target.scrollIntoView({ block: 'start' });
                });
            }
        };

        tabList.addEventListener('click', (event) => {
            const button = event.target.closest('[data-operation-tab]');
            if (!button || !tabList.contains(button)) return;

            activate(button.dataset.operationTab, { updateHash: true });

            const navTop = tabList.getBoundingClientRect().top;
            if (navTop < 68 || navTop > window.innerHeight * 0.55) {
                tabList.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        tabList.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

            const buttons = [...tabs.values()];
            const currentIndex = buttons.indexOf(document.activeElement);
            if (currentIndex < 0) return;

            event.preventDefault();

            let nextIndex = currentIndex;
            if (event.key === 'ArrowRight') {
                nextIndex = (currentIndex + 1) % buttons.length;
            } else if (event.key === 'ArrowLeft') {
                nextIndex = (currentIndex - 1 + buttons.length) % buttons.length;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = buttons.length - 1;
            }

            const nextButton = buttons[nextIndex];
            activate(nextButton.dataset.operationTab, { focus: true, updateHash: true });
        });

        activate('resumen');

        const validationPanel = [...panels.values()].find((panel) => (
            panel.querySelector('.field-error, [aria-invalid="true"], .notice--danger')
        ));

        if (validationPanel) {
            activate(validationPanel.dataset.operationPanel);
        } else {
            revealHashTarget();
        }

        window.addEventListener('hashchange', revealHashTarget);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOperationDetailTabs, { once: true });
    } else {
        initOperationDetailTabs();
    }
})();
