import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const script = fs.readFileSync(path.join(__dirname, '../../public/js/supplier-quote-products.js'), 'utf8');
const module = { exports: {} };
vm.runInNewContext(script, { module });
const { PAGE_SIZE, normalize, paginate, pageWindow } = module.exports;

const products = (count) => Array.from({ length: count }, (_, index) => ({
    id: index + 1,
    searchText: normalize(`PROD-${index + 1} Descripción ${index + 1}`),
}));

test('con 1, 10, 15, 16, 52 y 120 líneas ninguna se pierde ni se duplica', () => {
    assert.equal(PAGE_SIZE, 15);

    for (const count of [1, 10, 15, 16, 52, 120]) {
        const rows = products(count);
        const first = paginate(rows, '', 1);
        const all = Array.from({ length: first.lastPage }, (_, offset) =>
            paginate(rows, '', offset + 1).visible.map((row) => row.id)
        ).flat();

        assert.equal(first.matches.length, count);
        assert.equal(first.visible.length, Math.min(15, count));
        assert.deepEqual(all, rows.map((row) => row.id));
    }
});

test('52 líneas muestran los rangos y las cuatro páginas esperadas', () => {
    const rows = products(52);
    assert.deepEqual([1, 2, 3, 4].map((page) => {
        const result = paginate(rows, '', page);
        return [result.first, result.last, result.visible.length];
    }), [[1, 15, 15], [16, 30, 15], [31, 45, 15], [46, 52, 7]]);
});

test('42 líneas conservan el orden al navegar 1 → 2 → 3 → 1', () => {
    const rows = products(42);
    const results = [1, 2, 3, 1].map((page) => paginate(rows, '', page));
    assert.deepEqual(results.map(({ first, last }) => [first, last]), [
        [1, 15], [16, 30], [31, 42], [1, 15],
    ]);
    assert.deepEqual(results.slice(0, 3).flatMap(({ visible }) => visible.map(({ id }) => id)),
        rows.map(({ id }) => id));
});

test('la búsqueda usa código o descripción, normaliza tildes y cambia el total', () => {
    const rows = products(52);
    const byCode = paginate(rows, 'prod-52', 1);
    assert.deepEqual(byCode.visible.map((row) => row.id), [52]);

    const byDescription = paginate(rows, 'descripcion 16', 4);
    assert.deepEqual(byDescription.visible.map((row) => row.id), [16]);
    assert.equal(byDescription.page, 1);
    assert.equal(byDescription.matches.length, 1);

    const empty = paginate(rows, 'sin coincidencias', 1);
    assert.equal(empty.matches.length, 0);
    assert.equal(empty.visible.length, 0);
    assert.deepEqual([empty.first, empty.last, empty.lastPage], [0, 0, 1]);
});

test('los números de página dejan accesibles extremos y vecinos', () => {
    assert.deepEqual(Array.from(pageWindow(2, 4)), [1, 2, 3, 4]);
    assert.deepEqual(Array.from(pageWindow(15, 30)), [1, 2, null, 14, 15, 16, null, 29, 30]);
});

test('la navegación conserva las filas, busca, limpia y mantiene el foco', () => {
    let activeElement = null;
    const element = () => ({
        dataset: {}, hidden: true, disabled: false, value: '', attributes: {}, listeners: {},
        children: [], scrollCount: 0, focusCount: 0,
        setAttribute(name, value) { this.attributes[name] = value; },
        addEventListener(name, callback) { this.listeners[name] = callback; },
        replaceChildren(...children) { this.children = children; },
        scrollIntoView(options) { this.scrollCount += 1; this.scrollOptions = options; },
        focus(options) { this.focusCount += 1; this.focusOptions = options; activeElement = this; },
        click() { this.listeners.click?.(); },
        input() { this.listeners.input?.(); },
    });

    const section = element();
    section.classList = {
        classes: new Set(),
        toggle(name, enabled) {
            if (enabled) this.classes.add(name);
            else this.classes.delete(name);
        },
    };
    const tableWrap = { scrollTop: 240 };
    const selectors = [
        'tools', 'search', 'clear', 'footer', 'range', 'empty', 'pages', 'numbers', 'prev', 'next',
    ];
    const controls = Object.fromEntries(selectors.map((name) => [name, element()]));
    controls.numbers.querySelector = (selector) => selector === '[aria-current="page"]'
        ? controls.numbers.children.find((child) => child.attributes['aria-current'] === 'page')
        : null;
    const attr = (name) => `[data-supplier-quote-product-${name}]`;
    const lookup = Object.fromEntries(selectors.map((name) => [attr(name), controls[name]]));
    lookup['.supplier-quote-detail-table-wrap'] = tableWrap;

    const rows = Array.from({ length: 52 }, (_, index) => {
        const row = element();
        row.hidden = false; // Todas las filas son visibles antes de ejecutar el script.
        row.querySelector = (selector) => ({
            textContent: selector.endsWith('strong') ? `PROD-${index + 1}` : `Descripción ${index + 1}`,
        });
        return row;
    });
    section.querySelector = (selector) => lookup[selector];
    section.querySelectorAll = () => rows;

    const document = {
        readyState: 'complete',
        get activeElement() { return activeElement; },
        querySelectorAll: () => [section],
        createElement: () => element(),
    };
    vm.runInNewContext(script, { document });

    assert.equal(rows.filter((row) => !row.hidden).length, 15);
    assert.equal(section.classList.classes.has('supplier-quote-detail-lines--long'), true);
    assert.equal(controls.range.textContent, 'Mostrando 1–15 de 52 productos');
    assert.equal(controls.pages.hidden, false);
    assert.equal(section.scrollCount, 0);
    controls.next.click();
    assert.equal(controls.range.textContent, 'Mostrando 16–30 de 52 productos');
    assert.equal(tableWrap.scrollTop, 0);
    assert.equal(section.scrollCount, 1);
    assert.equal(section.scrollOptions.inline, 'nearest');
    assert.equal(controls.next.focusCount, 0);

    const pageThree = controls.numbers.children.find((child) => child.textContent === '3');
    pageThree.focus();
    pageThree.click();
    const activePage = controls.numbers.children.find((child) => child.attributes['aria-current'] === 'page');
    assert.equal(activePage.textContent, '3');
    assert.equal(activePage.disabled, false);
    assert.equal(activeElement, activePage);
    assert.equal(activePage.focusOptions.preventScroll, true);
    assert.equal(controls.range.textContent, 'Mostrando 31–45 de 52 productos');
    assert.equal(section.scrollCount, 2);

    controls.search.value = 'PROD-52';
    controls.search.input();
    assert.equal(rows.filter((row) => !row.hidden).length, 1);
    assert.equal(rows[51].hidden, false);
    assert.equal(controls.pages.hidden, true);
    assert.equal(controls.range.textContent, 'Mostrando 1–1 de 1 producto · 1 coincidencia de 52');

    controls.search.value = 'no existe';
    controls.search.input();
    assert.equal(rows.filter((row) => !row.hidden).length, 0);
    assert.equal(controls.empty.hidden, false);
    assert.equal(controls.range.textContent, 'Mostrando 0 de 0 productos · 0 coincidencias de 52');
    controls.clear.click();
    assert.equal(rows.filter((row) => !row.hidden).length, 15);
    assert.equal(controls.search.focusCount, 1);
});
