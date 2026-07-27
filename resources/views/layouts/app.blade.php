<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Panel administrativo') | Hidroil</title>

    <link rel="stylesheet" href="{{ asset('css/hidroil-admin.css') }}">
</head>
<body class="app-page">
    <div class="page-progress" data-page-progress aria-hidden="true">
        <span></span>
    </div>

    <div class="app-shell">
        @include('layouts.partials.sidebar')

        <div class="app-main">
            @include('layouts.partials.navbar')

            <main class="content">
                @include('layouts.partials.flash')
                @yield('content')
            </main>
        </div>
    </div>

    <div class="modal-backdrop" data-discard-modal hidden>
        <section
            class="confirmation-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="discard-modal-title"
            aria-describedby="discard-modal-description"
            tabindex="-1"
        >
            <span class="confirmation-modal__icon" aria-hidden="true">
                <x-ui.icon name="warning" :size="25" />
            </span>

            <div class="confirmation-modal__content">
                <h2 id="discard-modal-title">¿Descartar cambios?</h2>
                <p id="discard-modal-description">
                    Vas a perder la información que ingresaste en este formulario.
                </p>
            </div>

            <div class="confirmation-modal__actions">
                <button
                    type="button"
                    class="button button--ghost"
                    data-discard-stay
                >
                    Seguir editando
                </button>

                <button
                    type="button"
                    class="button button--danger"
                    data-discard-confirm
                >
                    Descartar cambios
                </button>
            </div>
        </section>
    </div>

    <script>
        const sidebar = document.querySelector('[data-sidebar]');
        const overlay = document.querySelector('[data-sidebar-overlay]');
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

        if (sidebar) {
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
        }

        const closeSidebar = () => {
            sidebar?.classList.remove('sidebar--open');
            overlay?.classList.remove('sidebar-overlay--visible');
        };

        document.querySelector('[data-sidebar-toggle]')
            ?.addEventListener('click', () => {
                sidebar?.classList.toggle('sidebar--open');
                overlay?.classList.toggle('sidebar-overlay--visible');
            });

        overlay?.addEventListener('click', closeSidebar);

        document.querySelectorAll('[data-sidebar-group]').forEach((group) => {
            const key = `hidroil-sidebar-${group.dataset.sidebarGroup}`;
            const active = group.dataset.active === 'true';
            const stored = localStorage.getItem(key);

            if (active) {
                group.open = true;
            } else if (stored !== null) {
                group.open = stored === 'open';
            }

            group.addEventListener('toggle', () => {
                localStorage.setItem(key, group.open ? 'open' : 'closed');
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

        document.querySelectorAll('[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const message = form.dataset.confirm || '¿Confirmas esta acción?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });

        document.querySelectorAll('[data-character-count]').forEach((counter) => {
            const field = document.getElementById(counter.dataset.characterCount);
            if (!field) {
                return;
            }

            const updateCounter = () => {
                const max = field.maxLength > 0 ? field.maxLength : '—';
                counter.textContent = `${field.value.length} / ${max}`;
            };

            field.addEventListener('input', updateCounter);
            updateCounter();
        });

        const discardModal = document.querySelector('[data-discard-modal]');
        const discardDialog = discardModal?.querySelector('.confirmation-modal');
        const discardStayButton = discardModal?.querySelector('[data-discard-stay]');
        const discardConfirmButton = discardModal?.querySelector('[data-discard-confirm]');
        const dirtyFormStates = new WeakMap();
        let pendingDiscardUrl = null;
        let pendingDiscardTrigger = null;

        const serializeForm = (form) => new URLSearchParams(new FormData(form)).toString();

        const navigateTo = (url) => {
            saveSidebarScroll();
            showPageProgress();
            window.location.assign(url);
        };

        const closeDiscardModal = () => {
            if (!discardModal) {
                return;
            }

            discardModal.hidden = true;
            document.body.classList.remove('modal-open');
            pendingDiscardUrl = null;

            if (pendingDiscardTrigger) {
                pendingDiscardTrigger.focus();
                pendingDiscardTrigger = null;
            }
        };

        const openDiscardModal = (url, trigger) => {
            if (!discardModal) {
                navigateTo(url);
                return;
            }

            pendingDiscardUrl = url;
            pendingDiscardTrigger = trigger;
            discardModal.hidden = false;
            document.body.classList.add('modal-open');
            requestAnimationFrame(() => discardStayButton?.focus());
        };

        document.querySelectorAll('[data-dirty-form]').forEach((form) => {
            dirtyFormStates.set(form, serializeForm(form));

            form.querySelector('[data-cancel-form]')?.addEventListener('click', (event) => {
                const button = event.currentTarget;
                const url = button.dataset.cancelUrl;

                if (!url) {
                    return;
                }

                const hasChanges = serializeForm(form) !== dirtyFormStates.get(form);

                if (!hasChanges) {
                    navigateTo(url);
                    return;
                }

                openDiscardModal(url, button);
            });
        });

        discardStayButton?.addEventListener('click', closeDiscardModal);

        discardConfirmButton?.addEventListener('click', () => {
            const url = pendingDiscardUrl;
            closeDiscardModal();

            if (url) {
                navigateTo(url);
            }
        });

        discardModal?.addEventListener('click', (event) => {
            if (event.target === discardModal) {
                closeDiscardModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && discardModal && !discardModal.hidden) {
                closeDiscardModal();
            }
        });

        discardDialog?.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') {
                return;
            }

            const focusable = Array.from(
                discardDialog.querySelectorAll('button:not([disabled]), [href], input, select, textarea')
            );

            if (focusable.length === 0) {
                return;
            }

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

        document.querySelectorAll('[data-loading-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (event.defaultPrevented || !form.checkValidity()) {
                    return;
                }

                if (form.dataset.submitting === 'true') {
                    event.preventDefault();
                    return;
                }

                form.dataset.submitting = 'true';
                form.classList.add('form-is-loading');
                form.setAttribute('aria-busy', 'true');

                const button = form.querySelector('[data-submit-button]');
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
                ) {
                    return;
                }

                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:')) {
                    return;
                }

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
    </script>

    @stack('scripts')
</body>
</html>
