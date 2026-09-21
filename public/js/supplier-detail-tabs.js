(() => {
    'use strict';

    const initSupplierDetailTabs = () => {
        const root = document.querySelector('[data-supplier-detail-tabs-root]');
        const tabList = root?.querySelector('[data-supplier-detail-tabs]');

        if (!root || !tabList || root.dataset.supplierDetailTabsReady === 'true') {
            return;
        }

        const tabs = new Map(
            [...tabList.querySelectorAll('[data-supplier-detail-tab]')]
                .map((button) => [button.dataset.supplierDetailTab, button])
        );
        const panels = new Map(
            [...root.querySelectorAll('[data-supplier-detail-panel]')]
                .map((panel) => [panel.dataset.supplierDetailPanel, panel])
        );

        if (tabs.size === 0 || panels.size === 0) return;

        root.dataset.supplierDetailTabsReady = 'true';
        root.classList.add('supplier-detail-sections--tabbed');

        const hashToTab = (hash) => {
            const normalized = String(hash || '').replace(/^#/, '');
            if (!normalized) return null;

            const target = document.getElementById(normalized);
            const panel = target?.closest('[data-supplier-detail-panel]');
            if (panel?.dataset.supplierDetailPanel) {
                return panel.dataset.supplierDetailPanel;
            }

            const aliases = {
                datos: 'datos',
                'datos-proveedor': 'datos',
                productos: 'productos',
                precios: 'productos',
                'productos-precios': 'productos',
                documentos: 'documentos',
                'documentos-proveedor': 'documentos',
                historial: 'historial',
                'historial-proveedor': 'historial',
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

            if (focus) tabs.get(tabName).focus({ preventScroll: true });

            if (updateHash) {
                const nextHash = `#${panels.get(tabName).id}`;
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
            if (target && !target.matches('[data-supplier-detail-panel]')) {
                window.requestAnimationFrame(() => target.scrollIntoView({ block: 'start' }));
            }
        };

        tabList.addEventListener('click', (event) => {
            const button = event.target.closest('[data-supplier-detail-tab]');
            if (!button || !tabList.contains(button)) return;

            activate(button.dataset.supplierDetailTab, { updateHash: true });
        });

        tabList.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

            const buttons = [...tabs.values()];
            const currentIndex = buttons.indexOf(document.activeElement);
            if (currentIndex < 0) return;

            event.preventDefault();
            let nextIndex = currentIndex;
            if (event.key === 'ArrowRight') nextIndex = (currentIndex + 1) % buttons.length;
            if (event.key === 'ArrowLeft') nextIndex = (currentIndex - 1 + buttons.length) % buttons.length;
            if (event.key === 'Home') nextIndex = 0;
            if (event.key === 'End') nextIndex = buttons.length - 1;

            const nextButton = buttons[nextIndex];
            activate(nextButton.dataset.supplierDetailTab, { focus: true, updateHash: true });
        });

        const query = new URLSearchParams(window.location.search);
        const paginatedTab = query.has('precios')
            ? 'productos'
            : (query.has('cotizaciones') ? 'documentos' : null);

        activate(paginatedTab || 'datos');
        revealHashTarget();
        window.addEventListener('hashchange', revealHashTarget);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSupplierDetailTabs, { once: true });
    } else {
        initSupplierDetailTabs();
    }
})();
