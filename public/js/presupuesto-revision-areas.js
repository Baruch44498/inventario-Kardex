(() => {
    const section = document.getElementById('partidas-presupuestales');
    if (!section) return;

    const search = section.querySelector('[data-budget-area-search]');
    const counter = section.querySelector('[data-budget-area-count]');
    const empty = section.querySelector('[data-budget-area-empty]');
    const areas = Array.from(section.querySelectorAll('.budget-area-card'));
    const picker = section.querySelector('.budget-area-picker');
    const pickerSummary = picker?.querySelector('.budget-area-picker__summary');

    const normalize = (value) => value.normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('es')
        .trim();

    search?.addEventListener('input', () => {
        const query = normalize(search.value);
        let visible = 0;

        areas.forEach((area) => {
            area.hidden = !normalize(area.dataset.budgetAreaName || '').includes(query);
            if (!area.hidden) visible += 1;
        });

        counter.textContent = `${visible} ${visible === 1 ? 'área disponible' : 'áreas disponibles'}`;
        empty.hidden = visible !== 0;
    });

    if (picker && pickerSummary) {
        const mobile = window.matchMedia('(max-width: 767px)');
        const syncPicker = () => {
            picker.open = !mobile.matches;
            pickerSummary.setAttribute('aria-expanded', String(picker.open));
        };

        syncPicker();
        mobile.addEventListener('change', syncPicker);
        picker.addEventListener('toggle', () => {
            pickerSummary.setAttribute('aria-expanded', String(picker.open));
        });
    }

    section.addEventListener('toggle', (event) => {
        const opened = event.target;
        if (!opened.matches?.('.budget-entry-card__details') || !opened.open) return;

        section.querySelectorAll('.budget-entry-card__details[open]').forEach((details) => {
            if (details !== opened) details.open = false;
        });
    }, true);
})();
