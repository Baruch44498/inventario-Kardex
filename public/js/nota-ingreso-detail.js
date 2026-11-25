document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('[data-entry-detail-tabs]');

    if (workspace) {
        const tabs = [...workspace.querySelectorAll('[data-entry-detail-tab]')];
        const panels = [...workspace.querySelectorAll('[data-entry-detail-panel]')];
        const validTabs = new Set(tabs.map((tab) => tab.dataset.entryDetailTab));
        const defaultTab = workspace.dataset.defaultTab || tabs[0]?.dataset.entryDetailTab;

        const fromHash = () => {
            const hash = window.location.hash.replace(/^#/, '');
            return validTabs.has(hash) ? hash : defaultTab;
        };

        const activate = (name, { focus = false, updateHash = true } = {}) => {
            const selectedName = validTabs.has(name) ? name : defaultTab;

            tabs.forEach((tab) => {
                const selected = tab.dataset.entryDetailTab === selectedName;
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                tab.tabIndex = selected ? 0 : -1;
                if (selected && focus) tab.focus();
            });

            panels.forEach((panel) => {
                panel.hidden = panel.dataset.entryDetailPanel !== selectedName;
            });

            if (updateHash && window.location.hash !== `#${selectedName}`) {
                window.history.replaceState(null, '', `#${selectedName}`);
            }
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(tab.dataset.entryDetailTab));
            tab.addEventListener('keydown', (event) => {
                let target = null;
                if (event.key === 'ArrowRight') target = (index + 1) % tabs.length;
                if (event.key === 'ArrowLeft') target = (index - 1 + tabs.length) % tabs.length;
                if (event.key === 'Home') target = 0;
                if (event.key === 'End') target = tabs.length - 1;
                if (target !== null) {
                    event.preventDefault();
                    activate(tabs[target].dataset.entryDetailTab, { focus: true });
                }
            });
        });

        window.addEventListener('hashchange', () => activate(fromHash(), { updateHash: false }));
        activate(fromHash(), { updateHash: false });
    }

    const modal = document.querySelector('[data-entry-cancel-modal]');
    const openButton = document.querySelector('[data-open-entry-cancel]');
    const closeButton = modal?.querySelector('[data-close-entry-cancel]');

    const openModal = () => {
        if (!modal) return;
        modal.hidden = false;
        document.body.classList.add('modal-open');
        requestAnimationFrame(() => modal.querySelector('textarea')?.focus());
    };

    const closeModal = () => {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('modal-open');
        openButton?.focus();
    };

    openButton?.addEventListener('click', openModal);
    closeButton?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hidden) closeModal();
    });

    if (modal && !modal.hidden) {
        document.body.classList.add('modal-open');
        modal.querySelector('textarea')?.focus();
    }
});
