document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('[data-order-cancel-modal]');
    const openButton = document.querySelector('[data-open-order-cancel]');
    const closeButton = document.querySelector('[data-close-order-cancel]');

    if (!modal) return;

    const open = () => {
        modal.hidden = false;
        document.body.classList.add('modal-open');

        window.requestAnimationFrame(() => {
            modal.querySelector('textarea')?.focus();
        });
    };

    const close = () => {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
        openButton?.focus();
    };

    openButton?.addEventListener('click', open);
    closeButton?.addEventListener('click', close);

    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) close();
    });

    if (!modal.hidden) document.body.classList.add('modal-open');
});

