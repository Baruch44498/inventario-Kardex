(() => {
    'use strict';

    const initPurchaseOrderTabs = () => {
        document.querySelectorAll('[data-purchase-order-tabs]').forEach((workspace) => {
            if (workspace.dataset.purchaseOrderTabsReady === 'true') {
                return;
            }

            const tabs = new Map(
                Array.from(workspace.querySelectorAll('[data-purchase-order-tab]'))
                    .map((tab) => [tab.dataset.purchaseOrderTab, tab])
            );
            const panels = new Map(
                Array.from(workspace.querySelectorAll('[data-purchase-order-panel]'))
                    .map((panel) => [panel.dataset.purchaseOrderPanel, panel])
            );

            if (tabs.size < 2 || tabs.size !== panels.size) {
                return;
            }

            const activate = (name, { focus = false, updateHash = false } = {}) => {
                if (!tabs.has(name)) {
                    return;
                }

                tabs.forEach((tab, tabName) => {
                    const active = tabName === name;
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.tabIndex = active ? 0 : -1;
                    panels.get(tabName).hidden = !active;
                });

                if (focus) {
                    tabs.get(name).focus({ preventScroll: true });
                }

                if (updateHash && window.history?.replaceState) {
                    window.history.replaceState(null, '', `#${name}`);
                }
            };

            const tabFromHash = () => {
                const hash = window.location.hash.replace(/^#/, '');

                if (tabs.has(hash)) {
                    return hash;
                }

                const target = hash ? document.getElementById(hash) : null;

                if (!target) {
                    return null;
                }

                return Array.from(panels.entries())
                    .find(([, panel]) => panel.contains(target))?.[0] ?? null;
            };

            const errorNode = workspace.querySelector('.field-error, .is-invalid, [aria-invalid="true"]');
            const errorTab = errorNode
                ? Array.from(panels.entries()).find(([, panel]) => panel.contains(errorNode))?.[0]
                : null;
            const configuredTab = workspace.dataset.defaultTab;
            const initialTab = errorTab
                || tabFromHash()
                || (tabs.has(configuredTab) ? configuredTab : tabs.keys().next().value);

            workspace.classList.add('purchase-order-workspace--tabbed');
            workspace.dataset.purchaseOrderTabsReady = 'true';
            activate(initialTab);

            tabs.forEach((tab, name) => {
                tab.addEventListener('click', () => activate(name, { updateHash: true }));
                tab.addEventListener('keydown', (event) => {
                    const orderedTabs = Array.from(tabs.values());
                    const currentIndex = orderedTabs.indexOf(tab);
                    let nextIndex = null;

                    if (event.key === 'ArrowRight') {
                        nextIndex = (currentIndex + 1) % orderedTabs.length;
                    } else if (event.key === 'ArrowLeft') {
                        nextIndex = (currentIndex - 1 + orderedTabs.length) % orderedTabs.length;
                    } else if (event.key === 'Home') {
                        nextIndex = 0;
                    } else if (event.key === 'End') {
                        nextIndex = orderedTabs.length - 1;
                    }

                    if (nextIndex === null) {
                        return;
                    }

                    event.preventDefault();
                    activate(orderedTabs[nextIndex].dataset.purchaseOrderTab, {
                        focus: true,
                        updateHash: true,
                    });
                });
            });

            window.addEventListener('hashchange', () => {
                const requestedTab = tabFromHash();

                if (requestedTab) {
                    activate(requestedTab);
                }
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPurchaseOrderTabs, { once: true });
    } else {
        initPurchaseOrderTabs();
    }
})();
