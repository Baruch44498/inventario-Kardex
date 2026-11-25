document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.budget-workflow-page');
    if (!page) return;

    const menu = page.querySelector('[data-budget-source-menu]');
    document.addEventListener('pointerdown', (event) => {
        if (menu?.open && !menu.contains(event.target)) menu.open = false;
    });
    menu?.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        event.stopPropagation();
        menu.open = false;
        menu.querySelector('summary')?.focus();
    });

    const newArea = page.querySelector('[data-budget-new-area]');
    const openNewArea = (scroll = false) => {
        if (!newArea) return;
        newArea.open = true;
        if (scroll) {
            newArea.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        }
    };
    const openForHash = () => {
        if (location.hash === '#nueva-area') {
            openNewArea(true);
            return;
        }
        const area = cards.find((card) => `#${card.id}` === location.hash);
        if (area) area.open = true;
    };
    window.addEventListener('hashchange', openForHash);
    page.querySelector('[data-budget-open-area]')?.addEventListener('click', (event) => {
        const destination = new URL(event.currentTarget.href);
        if (destination.pathname !== location.pathname || destination.search !== location.search || !newArea) return;
        event.preventDefault();
        openNewArea(true);
        if (location.hash !== '#nueva-area') history.replaceState(null, '', destination.href);
        newArea.querySelector('summary')?.focus({ preventScroll: true });
    });

    const cards = Array.from(page.querySelectorAll('.quote-area-card'));
    openForHash();
    const search = page.querySelector('[data-budget-area-search]');
    const count = page.querySelector('[data-budget-area-count]');
    const toggle = page.querySelector('[data-budget-toggle-areas]');
    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es');
    const visible = () => cards.filter((card) => !card.hidden);
    const updateToggle = () => {
        if (!toggle) return;
        const shown = visible();
        const allOpen = shown.length > 0 && shown.every((card) => card.open);
        toggle.textContent = allOpen ? 'Contraer todo' : 'Expandir todo';
        toggle.setAttribute('aria-expanded', String(allOpen));
    };
    search?.addEventListener('input', () => {
        const query = normalize(search.value.trim());
        cards.forEach((card) => {
            const name = card.querySelector('.quote-area-card__identity strong')?.textContent || '';
            card.hidden = !normalize(name).includes(query);
        });
        if (count) {
            const found = visible().length;
            count.textContent = `${found} ${found === 1 ? 'área encontrada' : 'áreas encontradas'}`;
        }
        updateToggle();
    });
    toggle?.addEventListener('click', () => {
        const shown = visible();
        const expand = shown.some((card) => !card.open);
        shown.forEach((card) => { card.open = expand; });
        updateToggle();
    });
    cards.forEach((card) => card.addEventListener('toggle', updateToggle));
    updateToggle();
});
