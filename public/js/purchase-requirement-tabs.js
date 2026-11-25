(() => {
    'use strict';

    const initPurchaseRequirementTabs = () => {
        const page = document.querySelector('[data-purchase-requirement-tabs]');

        if (!page || page.dataset.purchaseRequirementTabsReady === 'true') {
            return;
        }

        const directChildren = Array.from(page.children);
        const groups = [
            {
                id: 'productos',
                label: 'Productos',
                nodes: directChildren.filter((node) => node.matches?.('.purchase-requirement-detail-panel')),
            },
            {
                id: 'gestion',
                label: 'Gestión y proveedores',
                nodes: directChildren.filter((node) => node.matches?.(
                    '.purchase-requirement-workflow-panel, .purchase-requirement-contacts-panel, .purchase-requirement-quotes-panel'
                )),
            },
            {
                id: 'abastecimiento',
                label: 'Abastecimiento',
                nodes: directChildren.filter((node) => node.matches?.('.purchase-requirement-supply-panel')),
            },
            {
                id: 'historial',
                label: 'Historial y control',
                nodes: directChildren.filter((node) => node.matches?.(
                    '.purchase-requirement-history-panel, .purchase-requirement-document-control'
                )),
            },
        ].filter((group) => group.nodes.length > 0);

        if (groups.length < 2) {
            return;
        }

        const firstGroupedNode = directChildren.find((node) => (
            groups.some((group) => group.nodes.includes(node))
        ));

        if (!firstGroupedNode) {
            return;
        }

        const tabList = document.createElement('nav');
        tabList.className = 'purchase-requirement-tabs';
        tabList.setAttribute('role', 'tablist');
        tabList.setAttribute('aria-label', 'Secciones del requerimiento');

        const panelsContainer = document.createElement('div');
        panelsContainer.className = 'purchase-requirement-tab-panels';

        const tabs = new Map();
        const panels = new Map();
        const placeholders = new Map();

        firstGroupedNode.before(tabList, panelsContainer);

        try {
            groups.forEach((group) => {
                const tabId = `purchase-requirement-tab-${group.id}`;
                const panelId = `purchase-requirement-panel-${group.id}`;
                const button = document.createElement('button');
                const panel = document.createElement('section');

                button.type = 'button';
                button.id = tabId;
                button.className = 'purchase-requirement-tab';
                button.dataset.purchaseRequirementTab = group.id;
                button.setAttribute('role', 'tab');
                button.setAttribute('aria-controls', panelId);
                button.setAttribute('aria-selected', 'false');
                button.tabIndex = -1;
                button.textContent = group.label;

                panel.id = panelId;
                panel.className = `purchase-requirement-tab-panel purchase-requirement-tab-panel--${group.id}`;
                panel.dataset.purchaseRequirementPanel = group.id;
                panel.setAttribute('role', 'tabpanel');
                panel.setAttribute('aria-labelledby', tabId);
                panel.hidden = true;

                group.nodes.forEach((node) => {
                    const placeholder = document.createComment(`purchase-requirement-tab-placeholder:${group.id}`);
                    node.before(placeholder);
                    placeholders.set(node, placeholder);
                    panel.append(node);
                });

                tabList.append(button);
                panelsContainer.append(panel);
                tabs.set(group.id, button);
                panels.set(group.id, panel);
            });
        } catch (error) {
            placeholders.forEach((placeholder, node) => {
                if (placeholder.parentNode) {
                    placeholder.replaceWith(node);
                }
            });
            tabList.remove();
            panelsContainer.remove();
            console.error('No se pudo inicializar la navegación del requerimiento.', error);
            return;
        }

        placeholders.forEach((placeholder) => placeholder.remove());
        page.classList.add('purchase-requirement-page--tabbed');
        page.dataset.purchaseRequirementTabsReady = 'true';

        const activate = (tabName, { focus = false, updateHash = false } = {}) => {
            if (!tabs.has(tabName)) {
                return;
            }

            tabs.forEach((button, name) => {
                const active = name === tabName;
                button.setAttribute('aria-selected', active ? 'true' : 'false');
                button.tabIndex = active ? 0 : -1;
                panels.get(name).hidden = !active;
            });

            if (focus) {
                tabs.get(tabName).focus({ preventScroll: true });
            }

            if (updateHash && window.history?.replaceState) {
                window.history.replaceState(null, '', `#${tabName}`);
            }
        };

        const tabFromHash = () => {
            const normalized = window.location.hash.replace(/^#/, '');

            if (tabs.has(normalized)) {
                return normalized;
            }

            const target = normalized ? document.getElementById(normalized) : null;

            if (!target) {
                return null;
            }

            for (const [name, panel] of panels) {
                if (panel.contains(target)) {
                    return name;
                }
            }

            return null;
        };

        const errorNode = page.querySelector('.field-error, .is-invalid, [aria-invalid="true"]');
        const errorTab = errorNode
            ? Array.from(panels.entries()).find(([, panel]) => panel.contains(errorNode))?.[0]
            : null;
        const requestedTab = tabFromHash();
        const configuredTab = page.dataset.defaultTab;
        const initialTab = errorTab
            || requestedTab
            || (tabs.has(configuredTab) ? configuredTab : groups[0].id);

        activate(initialTab);

        tabs.forEach((button, name) => {
            button.addEventListener('click', () => activate(name, { updateHash: true }));
            button.addEventListener('keydown', (event) => {
                const buttons = Array.from(tabs.values());
                const currentIndex = buttons.indexOf(button);
                let nextIndex = null;

                if (event.key === 'ArrowRight') {
                    nextIndex = (currentIndex + 1) % buttons.length;
                } else if (event.key === 'ArrowLeft') {
                    nextIndex = (currentIndex - 1 + buttons.length) % buttons.length;
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = buttons.length - 1;
                }

                if (nextIndex === null) {
                    return;
                }

                event.preventDefault();
                activate(buttons[nextIndex].dataset.purchaseRequirementTab, {
                    focus: true,
                    updateHash: true,
                });
            });
        });

        window.addEventListener('hashchange', () => {
            const hashTab = tabFromHash();

            if (hashTab) {
                activate(hashTab);
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPurchaseRequirementTabs, { once: true });
    } else {
        initPurchaseRequirementTabs();
    }
})();
