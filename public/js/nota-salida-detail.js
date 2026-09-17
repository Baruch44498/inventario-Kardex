document.addEventListener('DOMContentLoaded', () => {
    const workspace = document.querySelector('[data-output-detail-tabs]');

    if (workspace) {
        const tabs = [...workspace.querySelectorAll('[data-output-detail-tab]')];
        const panels = [...workspace.querySelectorAll('[data-output-detail-panel]')];
        const validTabs = new Set(tabs.map((tab) => tab.dataset.outputDetailTab));
        const defaultTab = workspace.dataset.defaultTab || tabs[0]?.dataset.outputDetailTab;
        const fromHash = () => validTabs.has(window.location.hash.replace(/^#/, ''))
            ? window.location.hash.replace(/^#/, '')
            : defaultTab;

        const activate = (name, { focus = false, updateHash = true } = {}) => {
            const selectedName = validTabs.has(name) ? name : defaultTab;
            tabs.forEach((tab) => {
                const selected = tab.dataset.outputDetailTab === selectedName;
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                tab.tabIndex = selected ? 0 : -1;
                if (selected && focus) tab.focus();
            });
            panels.forEach((panel) => {
                panel.hidden = panel.dataset.outputDetailPanel !== selectedName;
            });
            if (updateHash && window.location.hash !== `#${selectedName}`) {
                window.history.replaceState(null, '', `#${selectedName}`);
            }
        };

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activate(tab.dataset.outputDetailTab));
            tab.addEventListener('keydown', (event) => {
                let target = null;
                if (event.key === 'ArrowRight') target = (index + 1) % tabs.length;
                if (event.key === 'ArrowLeft') target = (index - 1 + tabs.length) % tabs.length;
                if (event.key === 'Home') target = 0;
                if (event.key === 'End') target = tabs.length - 1;
                if (target !== null) {
                    event.preventDefault();
                    activate(tabs[target].dataset.outputDetailTab, { focus: true });
                }
            });
        });
        window.addEventListener('hashchange', () => activate(fromHash(), { updateHash: false }));
        activate(fromHash(), { updateHash: false });
    }

    const modal = document.querySelector('[data-output-cancel-modal]');
    const dialog = modal?.querySelector('.output-cancel-modal');
    const openButton = document.querySelector('[data-open-output-cancel]');
    const closeButton = modal?.querySelector('[data-close-output-cancel]');
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
    dialog?.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') return;
        const focusable = [...dialog.querySelectorAll('button:not([disabled]), textarea, input, select, [href]')];
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    if (modal && !modal.hidden) {
        document.body.classList.add('modal-open');
        requestAnimationFrame(() => modal.querySelector('textarea')?.focus());
    }
});
