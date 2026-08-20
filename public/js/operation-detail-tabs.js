(() => {
    'use strict';

    const initOperationDetailTabs = () => {
        const page = document.querySelector('.operation-page--show');

        if (!page || page.dataset.operationTabsReady === 'true') {
            return;
        }

        const directChildren = Array.from(page.children);
        const summary = directChildren.find((node) => node.classList?.contains('operation-show-grid'));

        if (!summary) {
            return;
        }

        const isOriginDocument = (node) => {
            if (!node.matches?.('section.panel.supplier-quote-detail-lines:not(#avance-operativo)')) {
                return false;
            }

            const eyebrow = node.querySelector('.eyebrow');
            return eyebrow?.textContent?.trim().toLowerCase().includes('documento de origen') ?? false;
        };

        const groups = [
            {
                id: 'resumen',
                label: 'Resumen',
                nodes: [summary],
            },
            {
                id: 'ejecucion',
                label: 'Ejecución',
                nodes: directChildren.filter((node) => node.id === 'avance-operativo'),
            },
            {
                id: 'materiales',
                label: 'Materiales',
                nodes: directChildren.filter((node) => (
                    node.id === 'materiales-requeridos'
                    || node.id === 'reservas-materiales'
                    || node.classList?.contains('operation-tools-in-use')
                    || isOriginDocument(node)
                )),
            },
            {
                id: 'abastecimiento',
                label: 'Abastecimiento',
                nodes: directChildren.filter((node) => node.classList?.contains('operation-related-grid')),
            },
        ].filter((group) => group.nodes.length > 0);

        if (groups.length < 2) {
            return;
        }

        const tabList = document.createElement('nav');
        tabList.className = 'operation-detail-tabs';
        tabList.setAttribute('role', 'tablist');
        tabList.setAttribute('aria-label', 'Secciones de la orden');

        const panelsContainer = document.createElement('div');
        panelsContainer.className = 'operation-tab-panels';

        const tabs = new Map();
        const panels = new Map();
        const placeholders = new Map();

        // 19.1C.1: el contenedor debe entrar al DOM ANTES de mover las secciones.
        // Así nunca usamos como ancla un nodo que ya fue reubicado.
        summary.before(tabList, panelsContainer);

        try {
            groups.forEach((group, index) => {
                const tabId = `operation-tab-${group.id}`;
                const panelId = `operation-panel-${group.id}`;

                const button = document.createElement('button');
                button.type = 'button';
                button.id = tabId;
                button.className = 'operation-detail-tab';
                button.setAttribute('role', 'tab');
                button.setAttribute('aria-controls', panelId);
                button.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
                button.tabIndex = index === 0 ? 0 : -1;
                button.dataset.operationTab = group.id;
                button.append(document.createTextNode(group.label));

                if (group.id !== 'resumen' && group.nodes.length > 1) {
                    const count = document.createElement('span');
                    count.className = 'operation-detail-tab__count';
                    count.textContent = String(group.nodes.length);
                    count.setAttribute('aria-hidden', 'true');
                    button.append(count);
                }

                const panel = document.createElement('section');
                panel.id = panelId;
                panel.className = `operation-tab-panel operation-tab-panel--${group.id}`;
                panel.setAttribute('role', 'tabpanel');
                panel.setAttribute('aria-labelledby', tabId);
                panel.dataset.operationPanel = group.id;
                panel.hidden = index !== 0;

                tabList.append(button);
                panelsContainer.append(panel);
                tabs.set(group.id, button);
                panels.set(group.id, panel);

                group.nodes.forEach((node) => {
                    const placeholder = document.createComment(`operation-tab-placeholder:${group.id}`);
                    node.before(placeholder);
                    placeholders.set(node, placeholder);
                    panel.append(node);
                });
            });
        } catch (error) {
            // Fallo seguro: devolver cada sección a su ubicación original y retirar la navegación.
            placeholders.forEach((placeholder, node) => {
                if (placeholder.parentNode) {
                    placeholder.replaceWith(node);
                }
            });

            tabList.remove();
            panelsContainer.remove();
            console.error('No se pudo inicializar la navegación de la orden.', error);
            return;
        }

        // Los marcadores ya no son necesarios una vez completado el montaje.
        placeholders.forEach((placeholder) => placeholder.remove());

        page.classList.add('operation-page--tabbed');
        page.dataset.operationTabsReady = 'true';

        const hashToTab = (hash) => {
            const normalized = String(hash || '').replace(/^#/, '');

            if (['avance-operativo', 'costos-directos', 'ejecucion'].includes(normalized)) {
                return 'ejecucion';
            }

            if (['materiales-requeridos', 'reservas-materiales', 'materiales'].includes(normalized)) {
                return 'materiales';
            }

            if (['abastecimiento', 'requerimientos-compra'].includes(normalized)) {
                return 'abastecimiento';
            }

            if (['resumen', 'contexto'].includes(normalized)) {
                return 'resumen';
            }

            return null;
        };

        const activate = (tabName, { focus = false, updateHash = false } = {}) => {
            if (!tabs.has(tabName) || !panels.has(tabName)) {
                return;
            }

            tabs.forEach((button, name) => {
                const active = name === tabName;
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                button.tabIndex = active ? 0 : -1;
                panels.get(name).hidden = !active;
            });

            const activeButton = tabs.get(tabName);

            if (focus) {
                activeButton.focus({ preventScroll: true });
            }

            if (updateHash) {
                const nextHash = `#${tabName}`;
                if (window.location.hash !== nextHash) {
                    window.history.replaceState(null, '', nextHash);
                }
            }
        };

        tabList.addEventListener('click', (event) => {
            const button = event.target.closest('[data-operation-tab]');
            if (!button) {
                return;
            }

            activate(button.dataset.operationTab, { updateHash: true });

            const navTop = tabList.getBoundingClientRect().top;
            if (navTop < 68 || navTop > window.innerHeight * 0.55) {
                tabList.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });

        tabList.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                return;
            }

            const buttons = Array.from(tabs.values());
            const currentIndex = buttons.indexOf(document.activeElement);

            if (currentIndex < 0) {
                return;
            }

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

        const panelWithValidationIssue = groups.find((group) => {
            const panel = panels.get(group.id);
            return panel?.querySelector('.field-error, [aria-invalid="true"], .notice--danger');
        });

        const requestedTab = panelWithValidationIssue?.id || hashToTab(window.location.hash);

        if (requestedTab && tabs.has(requestedTab)) {
            activate(requestedTab);

            const originalHash = window.location.hash.replace(/^#/, '');
            const target = originalHash ? document.getElementById(originalHash) : null;

            if (target) {
                window.requestAnimationFrame(() => {
                    target.scrollIntoView({ block: 'start' });
                });
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOperationDetailTabs, { once: true });
    } else {
        initOperationDetailTabs();
    }
})();
