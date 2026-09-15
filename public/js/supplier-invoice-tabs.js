(() => {
    'use strict';

    const initSupplierInvoiceTabs = () => {
        document.querySelectorAll('[data-supplier-invoice-tabs]').forEach((workspace) => {
            if (workspace.dataset.supplierInvoiceTabsReady === 'true') return;

            const tabs = new Map(Array.from(workspace.querySelectorAll('[data-supplier-invoice-tab]')).map((tab) => [tab.dataset.supplierInvoiceTab, tab]));
            const panels = new Map(Array.from(workspace.querySelectorAll('[data-supplier-invoice-panel]')).map((panel) => [panel.dataset.supplierInvoicePanel, panel]));
            if (tabs.size < 2 || tabs.size !== panels.size) return;

            const activate = (name, { focus = false, updateHash = false } = {}) => {
                if (!tabs.has(name)) return;
                tabs.forEach((tab, tabName) => {
                    const active = tabName === name;
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.tabIndex = active ? 0 : -1;
                    panels.get(tabName).hidden = !active;
                });
                if (focus) tabs.get(name).focus({ preventScroll: true });
                if (updateHash && window.history?.replaceState) window.history.replaceState(null, '', `#${name}`);
            };

            const tabFromHash = () => {
                const hash = window.location.hash.replace(/^#/, '');
                if (tabs.has(hash)) return hash;
                const target = hash ? document.getElementById(hash) : null;
                return target ? Array.from(panels.entries()).find(([, panel]) => panel.contains(target))?.[0] ?? null : null;
            };
            const errorNode = workspace.querySelector('.field-error, .is-invalid, [aria-invalid="true"]');
            const errorTab = errorNode ? Array.from(panels.entries()).find(([, panel]) => panel.contains(errorNode))?.[0] : null;
            const configuredTab = workspace.dataset.defaultTab;
            activate(errorTab || tabFromHash() || (tabs.has(configuredTab) ? configuredTab : tabs.keys().next().value));
            workspace.dataset.supplierInvoiceTabsReady = 'true';

            tabs.forEach((tab, name) => {
                tab.addEventListener('click', () => activate(name, { updateHash: true }));
                tab.addEventListener('keydown', (event) => {
                    const ordered = Array.from(tabs.values());
                    const current = ordered.indexOf(tab);
                    const next = event.key === 'ArrowRight' ? (current + 1) % ordered.length
                        : event.key === 'ArrowLeft' ? (current - 1 + ordered.length) % ordered.length
                            : event.key === 'Home' ? 0 : event.key === 'End' ? ordered.length - 1 : null;
                    if (next === null) return;
                    event.preventDefault();
                    activate(ordered[next].dataset.supplierInvoiceTab, { focus: true, updateHash: true });
                });
            });
            window.addEventListener('hashchange', () => {
                const requested = tabFromHash();
                if (requested) activate(requested);
            });
        });
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initSupplierInvoiceTabs, { once: true });
    else initSupplierInvoiceTabs();
})();
