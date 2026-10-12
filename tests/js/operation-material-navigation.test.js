import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const directory = path.dirname(fileURLToPath(import.meta.url));
const script = fs.readFileSync(path.join(directory, '../../public/js/operation-material-navigation.js'), 'utf8');
const exported = { exports: {} };
vm.runInNewContext(script, { module: exported });
const { PAGE_SIZE, normalize, paginate, pageWindow } = exported.exports;

test('0, 5, 15, 16 y 179 filas conservan orden sin pérdida ni duplicación', () => {
    assert.equal(PAGE_SIZE, 15);
    for (const count of [0, 5, 15, 16, 179]) {
        const rows = Array.from({ length: count }, (_, index) => ({
            id: index + 1, searchText: `mat-${index + 1}`, state: 'PENDIENTE', shortage: false,
        }));
        const pages = [];
        for (let page = 1; page <= Math.max(1, Math.ceil(count / PAGE_SIZE)); page += 1) {
            pages.push(...paginate(rows, { page }).visible.map((row) => row.id));
        }
        assert.deepEqual(pages, rows.map((row) => row.id));
    }
});

test('búsqueda, estado y faltante combinan filtros y reinician rango', () => {
    const rows = [
        { id: 1, searchText: normalize('CÓD-1 Válvula'), state: 'ACTIVA', shortage: true },
        { id: 2, searchText: normalize('CÓD-2 Válvula'), state: 'ATENDIDA', shortage: false },
        { id: 3, searchText: normalize('CÓD-3 Bomba'), state: 'ACTIVA', shortage: false },
    ];
    const result = paginate(rows, { query: 'valvula', state: 'ACTIVA', shortageOnly: true, page: 3 });
    assert.equal(result.page, 1);
    assert.equal(result.first, 1);
    assert.equal(result.last, 1);
    assert.deepEqual(result.visible.map((row) => row.id), [1]);
    assert.equal(paginate(rows, { query: 'sin coincidencias' }).matches.length, 0);
    assert.deepEqual(Array.from(pageWindow(6, 12)), [1, 2, null, 5, 6, 7, null, 11, 12]);
});

const node = () => ({
    hidden: false,
    value: '',
    checked: false,
    dataset: {},
    listeners: {},
    attributes: {},
    addEventListener(type, handler) { this.listeners[type] = handler; },
    setAttribute(name, value) { this.attributes[name] = value; },
    focus() { this.focused = true; },
    scrollIntoView() { this.scrolled = true; },
    replaceChildren(...children) { this.children = children; },
    querySelector(selector) { return this.children?.find((child) => child.attributes['aria-current'] === 'page') || null; },
    classList: { toggle() {} },
});

const render = ({ count = 179, reservations = false } = {}) => {
    const rows = Array.from({ length: count }, (_, index) => ({
        hidden: false,
        form: { value: `edición-${index}` },
        dataset: {
            operationLineSearchText: `MAT-${index + 1} Válvula ${index + 1}`,
            operationLineState: index % 2 ? 'ATENDIDA' : 'ACTIVA',
            operationLineShortage: index % 2 ? '0' : '1',
        },
    }));
    const selectors = {
        '[data-operation-line-tools]': node(),
        '[data-operation-line-search]': node(),
        '[data-operation-line-state]': node(),
        '[data-operation-line-shortage]': reservations ? node() : null,
        '[data-operation-line-clear]': node(),
        '[data-operation-line-footer]': node(),
        '[data-operation-line-range]': node(),
        '[data-operation-line-empty]': node(),
        '[data-operation-line-pages]': node(),
        '[data-operation-line-numbers]': node(),
        '[data-operation-line-prev]': node(),
        '[data-operation-line-next]': node(),
        '.required-materials-table-wrap, .reservation-table-wrap': node(),
    };
    const section = {
        dataset: {},
        querySelector: (selector) => selectors[selector],
        querySelectorAll: () => rows,
        scrollIntoView() { this.scrolled = true; },
    };
    const document = {
        readyState: 'complete',
        activeElement: null,
        querySelectorAll: () => [section],
        createElement: () => node(),
    };
    vm.runInNewContext(script, { document });
    return { rows, section, controls: selectors };
};

test('179 filas navegan y filtran sin quitar formularios del documento', () => {
    const ui = render();
    const { rows, controls } = ui;
    const form = rows[80].form;
    assert.equal(rows.filter((row) => !row.hidden).length, 15);
    assert.equal(controls['[data-operation-line-range]'].textContent, 'Mostrando 1–15 de 179 productos');
    controls['[data-operation-line-next]'].listeners.click();
    assert.equal(controls['[data-operation-line-range]'].textContent, 'Mostrando 16–30 de 179 productos');
    controls['[data-operation-line-search]'].value = 'válvula 81';
    controls['[data-operation-line-search]'].listeners.input();
    assert.equal(rows[80].hidden, false);
    assert.equal(rows.filter((row) => !row.hidden).length, 1);
    assert.equal(rows[80].form, form);
    assert.match(controls['[data-operation-line-range]'].textContent, /1 coincidencias de 179/);
    controls['[data-operation-line-clear]'].listeners.click();
    assert.equal(controls['[data-operation-line-range]'].textContent, 'Mostrando 1–15 de 179 productos');
});

test('reservas filtran solo faltantes sin ocultar los controles cuando hay cinco filas', () => {
    const ui = render({ count: 5, reservations: true });
    const { rows, controls } = ui;
    assert.equal(controls['[data-operation-line-tools]'].hidden, false);
    const shortage = controls['[data-operation-line-shortage]'];
    shortage.checked = true;
    shortage.listeners.change();
    assert.deepEqual(rows.map((row) => row.hidden), [false, true, false, true, false]);
    assert.equal(controls['[data-operation-line-pages]'].hidden, true);
});
