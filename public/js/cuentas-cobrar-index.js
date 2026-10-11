(() => {
    'use strict';

    const page = document.querySelector('.receivables-page');
    if (!page) return;

    const rows = page.querySelectorAll('.receivables-row[data-receivables-href]');
    if (!rows.length) return;

    page.classList.add('receivables-page--row-links');

    rows.forEach((row) => {
        row.addEventListener('click', (event) => {
            if (
                event.defaultPrevented || event.button !== 0
                || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
                || event.target.closest('a, button, input, select, textarea, summary, [role="button"]')
                || window.getSelection()?.toString()
            ) return;

            window.location.assign(row.dataset.receivablesHref);
        });
    });
})();
