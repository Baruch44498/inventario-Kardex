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

test('0, 1, 5, 15, 16 y 179 filas conservan orden sin pérdida ni duplicación', () => {
    assert.equal(PAGE_SIZE, 15);
    for (const count of [0, 1, 5, 15, 16, 179]) {
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

test('el ajuste mantiene un respaldo nativo cuando JavaScript no inicia', () => {
    const blade = fs.readFileSync(path.join(directory, '../../resources/views/ordenes_operacion/partials/_show_materiales.blade.php'), 'utf8');
    const css = fs.readFileSync(path.join(directory, '../../public/css/hidroil/ordenes.css'), 'utf8');
    assert.match(blade, /data-operation-adjust-row data-operation-adjust-error=/);
    assert.match(blade, /<details class="required-material-adjustment-fallback" @if \(\$ajusteConError\) open @endif>/);
    assert.match(blade, /<summary>Modificar \{\{ \$material->producto\?->codigo \}\}<\/summary>/);
    assert.match(blade, /type="reset"[^>]*data-operation-discard-button/);
    assert.doesNotMatch(blade, /data-operation-adjust-row[^>]*\shidden\b/);
    assert.match(css, /:not\(\[data-operation-line-ready='true'\]\) \[data-operation-adjust-toggle\] \{ display: none; \}/);
    assert.match(css, /\[data-operation-line-ready='true'\] \.required-material-adjustment-fallback > summary \{ display: none; \}/);
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

const interactiveMaterials = ({ count = 16, errorIndex = -1 } = {}) => {
    let document;
    const element = () => ({
        hidden: false, value: '', checked: false, dataset: {}, attributes: {}, handlers: {},
        addEventListener(type, handler) { (this.handlers[type] ??= []).push(handler); },
        dispatch(type, properties = {}) {
            let stopped = false;
            const event = {
                target: this, currentTarget: this, preventDefault() {},
                stopPropagation() {}, stopImmediatePropagation() { stopped = true; }, ...properties,
            };
            for (const handler of this.handlers[type] || []) {
                handler(event);
                if (stopped) break;
            }
        },
        click() { this.focus(); this.dispatch('click'); },
        setAttribute(name, value) { this.attributes[name] = value; },
        getAttribute(name) { return this.attributes[name] ?? null; },
        focus() { document.activeElement = this; },
        scrollIntoView() {},
        replaceChildren(...children) { this.children = children; },
        querySelector(selector) {
            return selector === '[aria-current="page"]'
                ? this.children?.find((child) => child.attributes['aria-current'] === 'page') || null
                : null;
        },
    });
    const controls = Object.fromEntries([
        'tools', 'search', 'state', 'clear', 'footer', 'range', 'range-inline', 'empty',
        'pages', 'numbers', 'prev', 'next',
    ].map((name) => [`[data-operation-line-${name}]`, element()]));
    controls['[data-operation-variations-toggle]'] = element();
    controls['[data-operation-variations-toggle]'].attributes['aria-pressed'] = 'false';
    const headings = Array.from({ length: 8 }, element);
    const columns = [headings[4], headings[5], element(), element()];
    const rows = Array.from({ length: count }, (_, index) => {
        const main = element();
        const panel = element();
        const toggle = element();
        const input = element();
        const reset = element();
        const fallback = { open: index === errorIndex };
        const form = element();
        form.values = { cantidad_nueva: String(index + 1), motivo: '' };
        form.original = { ...form.values };
        form.reset = () => { form.values = { ...form.original }; };
        form.closest = (selector) => selector === '[data-operation-adjust-row]' ? panel : fallback;
        form.querySelector = (selector) => selector === '[data-operation-discard-button]' ? reset : null;
        panel.id = `operation-adjust-row-${index + 1}`;
        panel.dataset.operationAdjustError = index === errorIndex ? '1' : '0';
        panel.firstElementChild = { colSpan: 8 };
        panel.matches = () => true;
        panel.querySelector = (selector) => ({
            'details.required-material-adjustment-fallback': fallback,
            'form[data-operation-discard-form]': form,
            '[name="cantidad_nueva"]': input,
        })[selector] ?? null;
        panel.contains = (node) => node === input || node === reset;
        toggle.attributes['aria-expanded'] = index === errorIndex ? 'true' : 'false';
        main.dataset = { operationLineSearchText: `MAT-${index + 1}`, operationLineState: 'PENDIENTE' };
        main.nextElementSibling = panel;
        main.querySelector = () => toggle;
        return { main, panel, toggle, input, reset, fallback, form };
    });
    const modal = element();
    modal.hidden = true;
    const dialog = element();
    const cancel = element();
    const confirm = element();
    modal.querySelector = (selector) => ({
        '[role="dialog"]': dialog, '[data-ui-modal-cancel]': cancel,
        '[data-ui-modal-confirm]': confirm,
    })[selector] ?? null;
    const section = element();
    section.querySelector = (selector) => controls[selector]
        || (selector === '.required-materials-table-wrap, .reservation-table-wrap' ? element() : null);
    section.querySelectorAll = (selector) => ({
        '[data-operation-line-row]': rows.map((row) => row.main),
        '[data-operation-variation-column]': columns,
        '.required-materials-table thead th': headings,
    })[selector] ?? [];
    document = {
        readyState: 'complete', activeElement: null,
        body: { classList: { add() {}, remove() {} } },
        querySelector: (selector) => selector === '[data-ui-modal="operation-form-discard"]' ? modal : null,
        querySelectorAll: (selector) => ({
            '[data-operation-discard-form]': rows.map((row) => row.form),
            '[data-operation-line-list]': [section],
            '[data-operation-comparison-list]': [],
        })[selector] ?? [],
        getElementById: (id) => rows.find((row) => row.panel.id.replace('operation-adjust-row-', 'operation-adjust-trigger-') === id)?.toggle || null,
        createElement: element,
        handlers: {},
        addEventListener(type, handler) { this.handlers[type] = handler; },
    };
    class FormDataFixture {
        constructor(form) { this.values = form.values; }
        *[Symbol.iterator]() { yield* Object.entries(this.values); }
    }
    vm.runInNewContext(script, {
        document, FormData: FormDataFixture, URLSearchParams,
        window: { requestAnimationFrame: (callback) => callback() },
    });
    return { document, rows, controls, columns, headings, section, modal, cancel, confirm };
};

test('un error en la línea 16 abre solo su ajuste en la segunda página', () => {
    const ui = interactiveMaterials({ count: 179, errorIndex: 15 });
    assert.equal(ui.controls['[data-operation-line-range]'].textContent, 'Mostrando 16–30 de 179 productos');
    assert.equal(ui.rows.filter((row) => !row.main.hidden).length, 15);
    assert.equal(ui.rows.filter((row) => !row.panel.hidden).length, 1);
    assert.equal(ui.rows[15].panel.hidden, false);
    assert.equal(ui.rows[15].fallback.open, true);
});

test('abrir, cancelar y Escape devuelven el foco a Modificar', () => {
    const ui = interactiveMaterials();
    const row = ui.rows[0];
    row.toggle.click();
    assert.equal(ui.document.activeElement, row.input);
    assert.equal(row.reset.handlers.click?.length, 1);
    row.reset.click();
    assert.equal(row.panel.hidden, true);
    assert.equal(ui.document.activeElement, row.toggle);
    row.toggle.click();
    row.panel.dispatch('keydown', { key: 'Escape', target: row.input });
    assert.equal(row.panel.hidden, true);
    assert.equal(ui.document.activeElement, row.toggle);
});

test('un ajuste modificado no se oculta al filtrar o paginar sin confirmar', () => {
    const ui = interactiveMaterials();
    const row = ui.rows[0];
    row.toggle.click();
    row.form.values.motivo = 'Cambio sin guardar';
    const search = ui.controls['[data-operation-line-search]'];
    search.value = 'MAT-16';
    search.dispatch('input');
    assert.equal(ui.modal.hidden, false);
    assert.equal(search.value, '');
    assert.equal(row.panel.hidden, false);
    ui.cancel.click();
    assert.equal(row.form.values.motivo, 'Cambio sin guardar');
    assert.equal(row.panel.hidden, false);
    ui.controls['[data-operation-line-next]'].click();
    assert.equal(ui.controls['[data-operation-line-range]'].textContent, 'Mostrando 1–15 de 16 productos');
    ui.confirm.click();
    assert.equal(row.form.values.motivo, '');
    assert.equal(row.panel.hidden, true);
    assert.equal(ui.controls['[data-operation-line-range]'].textContent, 'Mostrando 16–16 de 16 productos');
    assert.equal(ui.rows.filter((item) => !item.main.hidden).length, 1);
    assert.equal(ui.document.activeElement?.hidden, false);
});

test('las variaciones alternan columnas, aria-pressed y colspan', () => {
    const ui = interactiveMaterials();
    const toggle = ui.controls['[data-operation-variations-toggle]'];
    assert.equal(toggle.getAttribute('aria-pressed'), 'false');
    assert.ok(ui.columns.every((column) => column.hidden));
    assert.equal(ui.rows[0].panel.firstElementChild.colSpan, 6);
    toggle.click();
    assert.equal(toggle.getAttribute('aria-pressed'), 'true');
    assert.ok(ui.columns.every((column) => !column.hidden));
    assert.equal(ui.rows[0].panel.firstElementChild.colSpan, 8);
    toggle.click();
    assert.equal(toggle.getAttribute('aria-pressed'), 'false');
    assert.equal(ui.rows[0].panel.firstElementChild.colSpan, 6);
});

test('al cambiar de ajuste se confirma el descarte y el foco pasa al nuevo campo', () => {
    const ui = interactiveMaterials();
    ui.rows[0].toggle.click();
    ui.rows[0].form.values.cantidad_nueva = '9';
    ui.rows[1].toggle.click();
    assert.equal(ui.modal.hidden, false);
    assert.equal(ui.rows[0].panel.hidden, false);
    assert.equal(ui.rows[1].panel.hidden, true);
    ui.cancel.click();
    assert.equal(ui.rows[0].form.values.cantidad_nueva, '9');
    ui.rows[1].toggle.click();
    ui.confirm.click();
    assert.equal(ui.rows[0].form.values.cantidad_nueva, '1');
    assert.equal(ui.rows[0].panel.hidden, true);
    assert.equal(ui.rows[1].panel.hidden, false);
    assert.equal(ui.document.activeElement, ui.rows[1].input);
});

test('confirmar un filtro descarta el ajuste y enfoca el buscador visible', () => {
    const ui = interactiveMaterials();
    ui.rows[0].toggle.click();
    ui.rows[0].form.values.motivo = 'Sin guardar';
    const search = ui.controls['[data-operation-line-search]'];
    search.value = 'MAT-16';
    search.dispatch('input');
    assert.equal(ui.modal.hidden, false);
    assert.equal(search.value, '');
    ui.confirm.click();
    assert.equal(search.value, 'MAT-16');
    assert.equal(ui.rows[0].panel.hidden, true);
    assert.equal(ui.rows[15].main.hidden, false);
    assert.equal(ui.document.activeElement, search);
});

test('Escape con datos pendientes conserva el ajuste hasta confirmar', () => {
    const ui = interactiveMaterials();
    const row = ui.rows[0];
    row.toggle.click();
    row.form.values.motivo = 'Pendiente';
    row.panel.dispatch('keydown', { key: 'Escape', target: row.input });
    assert.equal(ui.modal.hidden, false);
    assert.equal(row.panel.hidden, false);
    ui.cancel.click();
    assert.equal(row.panel.hidden, false);
    assert.equal(ui.document.activeElement, row.input);
    row.panel.dispatch('keydown', { key: 'Escape', target: row.input });
    ui.confirm.click();
    assert.equal(row.panel.hidden, true);
    assert.equal(ui.document.activeElement, row.toggle);
});

test('cada página de consumo conserva el área correspondiente sin duplicar productos', () => {
    for (const count of [1, 15, 16, 179]) {
        const products = Array.from({ length: count }, (_, index) => ({
            hidden: false, dataset: { operationComparisonArea: String(Math.floor(index / 10)) },
        }));
        const groups = Array.from({ length: Math.ceil(count / 10) }, (_, index) => ({
            hidden: false, dataset: { operationComparisonGroup: String(index) },
        }));
        const controls = Object.fromEntries(['range', 'numbers', 'prev', 'next']
            .map((name) => [`[data-operation-comparison-${name}]`, node()]));
        const footer = { hidden: true, querySelector: (selector) => controls[selector] };
        const list = {
            parentElement: { querySelector: () => footer, scrollIntoView() {} },
            querySelectorAll: (selector) => selector === '[data-operation-comparison-row]' ? products : groups,
        };
        const document = {
            readyState: 'complete', activeElement: null,
            querySelector: () => null,
            querySelectorAll: (selector) => selector === '[data-operation-comparison-list]' ? [list] : [],
            createElement: node,
        };
        vm.runInNewContext(script, { document });
        const visited = [];
        for (let page = 1; page <= Math.ceil(count / PAGE_SIZE); page += 1) {
            const visible = products.map((product, index) => !product.hidden ? index : null).filter((index) => index !== null);
            visited.push(...visible);
            const visibleAreas = [...new Set(visible.map((index) => String(Math.floor(index / 10))))];
            assert.deepEqual(groups.filter((group) => !group.hidden).map((group) => group.dataset.operationComparisonGroup), visibleAreas);
            if (page < Math.ceil(count / PAGE_SIZE)) controls['[data-operation-comparison-next]'].listeners.click();
        }
        assert.deepEqual(visited, Array.from({ length: count }, (_, index) => index));
        assert.equal(footer.hidden, count <= PAGE_SIZE);
    }
});
