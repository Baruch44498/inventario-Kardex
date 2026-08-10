(() => {
    'use strict';

    const sidebar = document.querySelector('[data-sidebar]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const progress = document.querySelector('[data-page-progress]');
    const sidebarScrollKey = 'hidroil-sidebar-scroll';

    const showPageProgress = () => {
        document.body.classList.add('is-navigating');
        progress?.setAttribute('aria-hidden', 'false');
    };

    const hidePageProgress = () => {
        document.body.classList.remove('is-navigating');
        progress?.setAttribute('aria-hidden', 'true');
    };

    const saveSidebarScroll = () => {
        if (sidebar) {
            sessionStorage.setItem(sidebarScrollKey, String(sidebar.scrollTop));
        }
    };

    const closeSidebar = () => {
        sidebar?.classList.remove('sidebar--open');
        overlay?.classList.remove('sidebar-overlay--visible');
        sidebarToggle?.setAttribute('aria-expanded', 'false');
    };

    const openSidebar = () => {
        sidebar?.classList.add('sidebar--open');
        overlay?.classList.add('sidebar-overlay--visible');
        sidebarToggle?.setAttribute('aria-expanded', 'true');
    };

    if (sidebar) {
        const activeLinks = Array.from(sidebar.querySelectorAll('.sidebar-link--active'));
        activeLinks.forEach((link, index) => {
            if (index === 0) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });

        const storedScroll = Number(sessionStorage.getItem(sidebarScrollKey) || 0);
        requestAnimationFrame(() => {
            sidebar.scrollTop = storedScroll;
        });

        let scrollFrame = null;
        sidebar.addEventListener('scroll', () => {
            if (scrollFrame !== null) {
                cancelAnimationFrame(scrollFrame);
            }

            scrollFrame = requestAnimationFrame(saveSidebarScroll);
        }, { passive: true });

        sidebar.querySelectorAll('a[href]').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.matchMedia('(max-width: 880px)').matches) {
                    closeSidebar();
                }
            });
        });
    }

    sidebarToggle?.setAttribute('aria-expanded', 'false');
    sidebarToggle?.addEventListener('click', () => {
        if (sidebar?.classList.contains('sidebar--open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });
    overlay?.addEventListener('click', closeSidebar);

    document.querySelectorAll('[data-sidebar-group]').forEach((group) => {
        const key = `hidroil-sidebar-${group.dataset.sidebarGroup}`;
        const active = group.dataset.active === 'true';
        const stored = localStorage.getItem(key);
        const summary = group.querySelector('summary');

        if (active) {
            group.open = true;
        } else if (stored !== null) {
            group.open = stored === 'open';
        }

        const syncExpanded = () => {
            summary?.setAttribute('aria-expanded', String(group.open));
        };

        syncExpanded();
        group.addEventListener('toggle', () => {
            localStorage.setItem(key, group.open ? 'open' : 'closed');
            syncExpanded();
        });
    });

    const dismissToast = (toast) => {
        if (!toast || toast.dataset.closing === 'true') {
            return;
        }

        toast.dataset.closing = 'true';
        toast.classList.add('toast--leaving');
        window.setTimeout(() => toast.remove(), 220);
    };

    document.querySelectorAll('[data-toast]').forEach((toast) => {
        const timer = window.setTimeout(() => dismissToast(toast), 5000);
        toast.querySelector('[data-toast-close]')?.addEventListener('click', () => {
            window.clearTimeout(timer);
            dismissToast(toast);
        });
    });

    const modalStates = new Map();

    const createModalController = (name) => {
        const root = document.querySelector(`[data-ui-modal="${name}"]`);
        if (!root) return null;

        const dialog = root.querySelector('[role="dialog"]');
        const cancelButton = root.querySelector('[data-ui-modal-cancel]');
        const confirmButton = root.querySelector('[data-ui-modal-confirm]');
        const title = root.querySelector('[data-ui-modal-title]');
        const message = root.querySelector('[data-ui-modal-message]');
        const confirmLabel = root.querySelector('[data-ui-modal-confirm-label]');
        const state = { trigger: null, onConfirm: null };

        const close = ({ restoreFocus = true } = {}) => {
            root.hidden = true;
            document.body.classList.remove('modal-open');

            if (restoreFocus) {
                state.trigger?.focus();
            }

            state.trigger = null;
            state.onConfirm = null;
        };

        const open = (options = {}) => {
            state.trigger = options.trigger || document.activeElement;
            state.onConfirm = options.onConfirm || null;

            if (title && options.title) title.textContent = options.title;
            if (message && options.message) message.textContent = options.message;
            if (confirmLabel && options.confirmLabel) {
                confirmLabel.textContent = options.confirmLabel;
            }

            const tone = options.tone || root.dataset.defaultTone || 'warning';
            dialog?.classList.remove(
                'ui-confirmation-modal--danger',
                'ui-confirmation-modal--warning',
                'ui-confirmation-modal--info'
            );
            dialog?.classList.add(`ui-confirmation-modal--${tone}`);
            confirmButton?.classList.toggle('button--danger', tone === 'danger');
            confirmButton?.classList.toggle('button--primary', tone !== 'danger');

            root.hidden = false;
            document.body.classList.add('modal-open');
            requestAnimationFrame(() => cancelButton?.focus());
        };

        cancelButton?.addEventListener('click', () => close());
        confirmButton?.addEventListener('click', () => {
            const callback = state.onConfirm;
            close({ restoreFocus: false });
            callback?.();
        });

        root.addEventListener('click', (event) => {
            if (event.target === root) close();
        });

        dialog?.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') return;

            const focusable = Array.from(
                dialog.querySelectorAll('button:not([disabled]), [href], input, select, textarea')
            );
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

        const controller = { root, open, close };
        modalStates.set(name, controller);
        return controller;
    };

    const confirmationModal = createModalController('confirm');
    const discardModal = createModalController('discard');

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        Array.from(modalStates.values()).forEach((modal) => {
            if (!modal.root.hidden) modal.close();
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmBypass === 'true') {
                delete form.dataset.confirmBypass;
                return;
            }

            event.preventDefault();
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const submitter = event.submitter || form.querySelector('[type="submit"]');
            const tone = form.dataset.confirmTone
                || (
                    submitter?.classList.contains('button--danger')
                        || submitter?.classList.contains('icon-button--danger')
                        ? 'danger'
                        : 'warning'
                );

            confirmationModal?.open({
                trigger: submitter,
                title: form.dataset.confirmTitle || 'Confirmar acción',
                message: form.dataset.confirm || 'Revisa la información antes de continuar.',
                confirmLabel: form.dataset.confirmLabel || 'Confirmar',
                tone,
                onConfirm: () => {
                    form.dataset.confirmBypass = 'true';
                    if (submitter) {
                        form.requestSubmit(submitter);
                    } else {
                        form.requestSubmit();
                    }
                },
            });
        });
    });

    document.querySelectorAll('[data-character-count]').forEach((counter) => {
        const field = document.getElementById(counter.dataset.characterCount);
        if (!field) return;

        const updateCounter = () => {
            const max = field.maxLength > 0 ? field.maxLength : '—';
            counter.textContent = `${field.value.length} / ${max}`;
        };

        field.addEventListener('input', updateCounter);
        updateCounter();
    });

    document.querySelectorAll('[data-digits-only]').forEach((field) => {
        const configuredLimit = Number.parseInt(field.dataset.digitsOnly, 10);
        const limit = Number.isInteger(configuredLimit) && configuredLimit > 0
            ? configuredLimit
            : null;

        const keepDigitsOnly = () => {
            const digits = field.value.replace(/\D/g, '');
            field.value = limit === null ? digits : digits.slice(0, limit);
        };

        field.addEventListener('input', keepDigitsOnly);
        keepDigitsOnly();
    });

    document.querySelectorAll('[data-uppercase-code]').forEach((field) => {
        const normalizeCode = () => {
            field.value = field.value
                .toUpperCase()
                .replace(/\s+/g, '')
                .replace(/[^A-Z0-9-]/g, '');
        };

        field.addEventListener('input', normalizeCode);
        normalizeCode();
    });

    const dirtyFormStates = new WeakMap();
    const serializeForm = (form) => new URLSearchParams(new FormData(form)).toString();

    const navigateTo = (url) => {
        saveSidebarScroll();
        showPageProgress();
        window.location.assign(url);
    };

    document.querySelectorAll('[data-dirty-form]').forEach((form) => {
        dirtyFormStates.set(form, serializeForm(form));

        form.querySelector('[data-cancel-form]')?.addEventListener('click', (event) => {
            const button = event.currentTarget;
            const url = button.dataset.cancelUrl;
            if (!url) return;

            const hasChanges = serializeForm(form) !== dirtyFormStates.get(form);
            if (!hasChanges) {
                navigateTo(url);
                return;
            }

            discardModal?.open({
                trigger: button,
                title: '¿Descartar cambios?',
                message: 'Vas a perder la información que ingresaste en este formulario.',
                confirmLabel: 'Descartar cambios',
                tone: 'danger',
                onConfirm: () => navigateTo(url),
            });
        });
    });

    document.querySelectorAll('[data-history-back]').forEach((button) => {
        button.addEventListener('click', () => window.history.back());
    });

    document.querySelectorAll('[data-loading-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented || !form.checkValidity()) return;
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = 'true';
            form.classList.add('form-is-loading');
            form.setAttribute('aria-busy', 'true');

            const button = event.submitter || form.querySelector('[data-submit-button]');
            const icon = button?.querySelector('[data-submit-icon]');
            const spinner = button?.querySelector('[data-submit-spinner]');
            const label = button?.querySelector('[data-submit-label]');

            if (button) {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            }
            if (icon) icon.hidden = true;
            if (spinner) spinner.hidden = false;
            if (label && button?.dataset.loadingText) {
                label.textContent = button.dataset.loadingText;
            }

            saveSidebarScroll();
            showPageProgress();
        });
    });

    document.querySelectorAll('[data-responsive-table]').forEach((tableWrap) => {
        tableWrap.querySelectorAll('[data-table-details-toggle]').forEach((button) => {
            const detailsId = button.getAttribute('aria-controls');
            const detailsRow = detailsId ? document.getElementById(detailsId) : null;
            if (!detailsRow) return;

            button.addEventListener('click', () => {
                const expanded = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', String(!expanded));
                button.classList.toggle('table-details-toggle--expanded', !expanded);
                button.title = expanded ? 'Ver más detalles' : 'Ocultar detalles';
                detailsRow.hidden = expanded;
            });
        });
    });

    const collapsibleNotices = Array.from(document.querySelectorAll('[data-collapsible-notice]'));

    collapsibleNotices.forEach((notice) => {
        notice.addEventListener('toggle', () => {
            if (!notice.open) return;

            collapsibleNotices.forEach((otherNotice) => {
                if (otherNotice !== notice) otherNotice.open = false;
            });
        });
    });

    document.addEventListener('click', (event) => {
        collapsibleNotices.forEach((notice) => {
            if (notice.open && !notice.contains(event.target)) {
                notice.open = false;
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        collapsibleNotices.forEach((notice) => {
            notice.open = false;
        });
    });

    document.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (
                event.defaultPrevented
                || event.button !== 0
                || event.metaKey
                || event.ctrlKey
                || event.shiftKey
                || event.altKey
                || link.target === '_blank'
                || link.hasAttribute('download')
            ) return;

            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

            const target = new URL(link.href, window.location.href);
            if (target.origin !== window.location.origin || target.href === window.location.href) {
                return;
            }

            saveSidebarScroll();
            showPageProgress();
        });
    });

    window.addEventListener('beforeunload', saveSidebarScroll);
    window.addEventListener('pageshow', hidePageProgress);
})();
