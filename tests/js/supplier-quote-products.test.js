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

test('con 1, 10, 15, 16, 52 y 101 líneas ninguna se pierde ni se duplica', () => {
    assert.equal(PAGE_SIZE, 15);

    for (const count of [1, 10, 15, 16, 52, 101]) {
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
    const element = () => ({
        dataset: {}, hidden: true, disabled: false, value: '', attributes: {}, listeners: {},
        children: [], scrollCount: 0, focusCount: 0,
        setAttribute(name, value) { this.attributes[name] = value; },
        addEventListener(name, callback) { this.listeners[name] = callback; },
        replaceChildren(...children) { this.children = children; },
        scrollIntoView() { this.scrollCount += 1; },
        focus() { this.focusCount += 1; },
        click() { this.listeners.click?.(); },
        input() { this.listeners.input?.(); },
    });

    const section = element();
    const selectors = [
        'tools', 'search', 'clear', 'footer', 'range', 'empty', 'pages', 'numbers', 'prev', 'next',
    ];
    const controls = Object.fromEntries(selectors.map((name) => [name, element()]));
    const attr = (name) => `[data-supplier-quote-product-${name}]`;
    const lookup = Object.fromEntries(selectors.map((name) => [attr(name), controls[name]]));

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
        querySelectorAll: () => [section],
        createElement: () => element(),
    };
    vm.runInNewContext(script, { document });

    assert.equal(rows.filter((row) => !row.hidden).length, 15);
    assert.equal(controls.range.textContent, 'Mostrando 1–15 de 52 productos');
    assert.equal(controls.pages.hidden, false);
    controls.next.click();
    assert.equal(controls.range.textContent, 'Mostrando 16–30 de 52 productos');
    assert.equal(section.scrollCount, 1);
    assert.equal(controls.next.focusCount, 0);

    controls.search.value = 'PROD-52';
    controls.search.input();
    assert.equal(rows.filter((row) => !row.hidden).length, 1);
    assert.equal(rows[51].hidden, false);
    assert.equal(controls.pages.hidden, true);
    assert.equal(controls.range.textContent, 'Mostrando 1–1 de 1 producto');

    controls.search.value = 'no existe';
    controls.search.input();
    assert.equal(rows.filter((row) => !row.hidden).length, 0);
    assert.equal(controls.empty.hidden, false);
    controls.clear.click();
    assert.equal(rows.filter((row) => !row.hidden).length, 15);
    assert.equal(controls.search.focusCount, 1);
});
