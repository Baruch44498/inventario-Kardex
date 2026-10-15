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
            const revealHashTarget = () => {
                const target = document.getElementById(window.location.hash.slice(1));
                if (target && workspace.contains(target)) {
                    requestAnimationFrame(() => target.scrollIntoView({ block: 'start', behavior: 'auto' }));
                }
            };
            const errorNode = workspace.querySelector('.field-error, .is-invalid, [aria-invalid="true"]');
            const errorTab = errorNode ? Array.from(panels.entries()).find(([, panel]) => panel.contains(errorNode))?.[0] : null;
            const configuredTab = workspace.dataset.defaultTab;
            const initialHashTab = tabFromHash();
            activate(errorTab || initialHashTab || (tabs.has(configuredTab) ? configuredTab : tabs.keys().next().value));
            if (!errorTab && initialHashTab) revealHashTarget();
            if (errorTab) {
                const invalid = panels.get(errorTab).querySelector('[aria-invalid="true"]');
                if (invalid) requestAnimationFrame(() => {
                    if (document.activeElement !== document.body) return;
                    invalid.focus({ preventScroll: true });
                    invalid.scrollIntoView({ block: 'center', behavior: 'auto' });
                });
            }
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
                if (requested) {
                    activate(requested);
                    revealHashTarget();
                }
            });
        });
    };

    const initPaymentForm = () => {
        document.querySelectorAll('[data-supplier-invoice-payment-form]').forEach((form) => {
            const amount = form.querySelector('[data-supplier-invoice-payment-amount]');
            const preview = form.querySelector('[data-supplier-invoice-payment-preview]');
            const fill = form.querySelector('[data-supplier-invoice-payment-fill]');
            if (!amount || !preview || !fill) return;

            const maximum = Number(amount.max);
            if (!Number.isFinite(maximum) || maximum <= 0) return;
            const balance = Number(amount.dataset.supplierInvoicePaymentBalance ?? amount.max);
            const fullAmount = Number(amount.dataset.supplierInvoicePaymentFull ?? amount.max);
            const symbol = form.dataset.currency === 'USD' ? 'US$' : 'S/';
            const format = new Intl.NumberFormat('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
            const update = () => {
                const entered = amount.value.trim();
                const value = Number(entered);
                const closesWithRounding = entered !== ''
                    && Math.abs(fullAmount - balance) > 0.00005
                    && Math.abs(value - fullAmount) < 0.00005;
                if (entered !== '' && (!Number.isFinite(value) || value < 0 || value > maximum || (value > balance && !closesWithRounding))) {
                    preview.textContent = 'Revisa el monto: debe estar dentro del saldo disponible.';
                    return;
                }
                const remaining = entered === '' ? balance
                    : closesWithRounding ? 0
                        : Math.max(0, Math.round((balance - value) * 10000) / 10000);
                preview.textContent = `Saldo después de este pago: ${symbol} ${format.format(remaining)}`;
            };
            fill.hidden = false;
            fill.addEventListener('click', () => {
                amount.value = String(fullAmount);
                update();
                amount.focus();
            });
            amount.addEventListener('input', update);
            update();
        });
    };

    const init = () => { initSupplierInvoiceTabs(); initPaymentForm(); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
