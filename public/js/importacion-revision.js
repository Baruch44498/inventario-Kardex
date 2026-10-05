(() => {
    const items = Array.from(document.querySelectorAll('.import-review-item__details'));

    items.forEach((details) => {
        details.addEventListener('toggle', () => {
            if (details.open) {
                items.forEach((other) => {
                    if (other !== details) other.open = false;
                });
            }
        });
    });

    document.querySelectorAll('.import-review-form').forEach((form) => {
        const type = form.querySelector('[data-import-review-type]');
        if (!type) return;

        const updateFields = () => {
            form.querySelectorAll('[data-import-review-for]').forEach((field) => {
                const applies = field.dataset.importReviewFor === 'material'
                    ? type.value === 'MATERIAL'
                    : field.dataset.importReviewFor === 'servicio'
                        ? type.value === 'SERVICIO_TERCERO'
                        : type.value !== 'MATERIAL';
                field.hidden = !applies;
                field.querySelectorAll('input, select, textarea, button').forEach((control) => {
                    control.disabled = !applies;
                });
            });
        };

        type.addEventListener('change', updateFields);
        updateFields();
    });
})();
