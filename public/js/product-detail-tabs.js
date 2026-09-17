(() => {
    const workspaces = document.querySelectorAll('[data-product-detail-tabs]');

    workspaces.forEach((workspace) => {
        const tabs = [...workspace.querySelectorAll('[data-product-detail-tab]')];
        const panels = [...workspace.querySelectorAll('[data-product-detail-panel]')];
        const validTabs = new Set(tabs.map((tab) => tab.dataset.productDetailTab));
        const defaultTab = workspace.dataset.defaultTab || tabs[0]?.dataset.productDetailTab;

        if (! tabs.length || ! defaultTab) {
            return;
        }

        const tabFromHash = () => {
            const hash = window.location.hash.replace(/^#/, '');

            return validTabs.has(hash) ? hash : defaultTab;
        };

        const activate = (name, { focus = false, updateHash = true } = {}) => {
            const selectedName = validTabs.has(name) ? name : defaultTab;

            tabs.forEach((tab) => {
                const selected = tab.dataset.productDetailTab === selectedName;
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                tab.tabIndex = selected ? 0 : -1;

                if (selected && focus) {
                    tab.focus();
                }
            });

            panels.forEach((panel) => {
                panel.hidden = panel.dataset.productDetailPanel !== selectedName;
            });

            if (updateHash && window.location.hash !== `#${selectedName}`) {
                window.history.replaceState(null, '', `#${selectedName}`);
            }
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(tab.dataset.productDetailTab));
            tab.addEventListener('keydown', (event) => {
                let targetIndex = null;

                if (event.key === 'ArrowRight') {
                    targetIndex = (index + 1) % tabs.length;
                } else if (event.key === 'ArrowLeft') {
                    targetIndex = (index - 1 + tabs.length) % tabs.length;
                } else if (event.key === 'Home') {
                    targetIndex = 0;
                } else if (event.key === 'End') {
                    targetIndex = tabs.length - 1;
                }

                if (targetIndex !== null) {
                    event.preventDefault();
                    activate(tabs[targetIndex].dataset.productDetailTab, { focus: true });
                }
            });
        });

        window.addEventListener('hashchange', () => activate(tabFromHash(), { updateHash: false }));
        activate(tabFromHash(), { updateHash: false });
    });
})();
