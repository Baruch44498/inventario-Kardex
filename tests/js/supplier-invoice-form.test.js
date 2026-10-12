import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const directory = path.dirname(fileURLToPath(import.meta.url));
const script = fs.readFileSync(path.join(directory, '../../public/js/supplier-invoice-form.js'), 'utf8');

const node = (values = {}) => ({
    textContent: '',
    value: '',
    hidden: false,
    listeners: {},
    classes: new Set(),
    classList: {
        values: new Set(),
        add(...names) { names.forEach((name) => this.values.add(name)); },
        remove(...names) { names.forEach((name) => this.values.delete(name)); },
        toggle(name, on) {
            if (on) this.values.add(name);
            else this.values.delete(name);
        },
        contains(name) { return this.values.has(name); },
    },
    addEventListener(event, callback) { this.listeners[event] = callback; },
    replaceChildren(child) { this.textContent = child.textContent; },
    focus() { this.focused = true; },
    scrollIntoView(options) { this.scrollOptions = options; },
    toggleAttribute(name, enabled) { this.attributes ||= new Set(); if (enabled) this.attributes.add(name); else this.attributes.delete(name); },
    ...values,
});

const render = ({ count = 1, unitCost = 118, taxed = true, currency = 'S/', finder = false, invalidField = false } = {}) => {
    const summary = node();
    const physicalTotal = node();
    const physicalResult = node();
    const partialToggle = node({ checked: false });
    const fileInput = node({ files: [] });
    const fileControls = node({ hidden: true });
    const fileName = node();
    const fileRemove = node({ hidden: true });
    const fileError = node({ hidden: true });
    const fileServerError = node({ hidden: false });
    const fileReselect = node({ hidden: false });
    const documentBase = node();
    const documentIgv = node();
    const documentTotal = node();
    const lineFinder = finder ? node({ hidden: true }) : null;
    const lineSearch = finder ? node() : null;
    const searchNext = finder ? node() : null;
    const searchResult = finder ? node() : null;
    const errorField = invalidField ? node() : null;
    const rows = Array.from({ length: count }, (_, index) => {
        const help = node();
        const quantity = node({
            value: '10',
            max: '10',
            dataset: { fullQuantity: '10' },
            closest: () => ({ querySelector: () => help }),
        });
        const base = node();
        const igv = node();
        const total = node();
        const fields = {
            '[data-invoice-quantity]': quantity,
            '[data-invoice-base]': base,
            '[data-invoice-igv]': igv,
            '[data-invoice-total]': total,
        };
        return {
            dataset: { unitCost: String(unitCost), taxed: taxed ? '1' : '0' },
            classList: node().classList,
            focus() { this.focused = true; },
            scrollIntoView(options) { this.scrollOptions = options; },
            querySelector: (selector) => selector === 'td[data-label="Producto"]'
                ? { querySelector: (tag) => node({ textContent: tag === 'strong' ? `PR-${index + 1}` : `Válvula ${index + 1}` }) }
                : fields[selector],
            quantity,
            base,
            igv,
            total,
        };
    });
    const controls = {
        '[data-partial-billing-toggle]': partialToggle,
        '#supplier-invoice-line-summary': summary,
        '#supplier-invoice-physical-total': physicalTotal,
        '#supplier-invoice-physical-result': physicalResult,
        '#supplier-invoice-currency': node({ textContent: currency }),
        '#supplier-invoice-file': fileInput,
        '.supplier-invoice-upload__controls': fileControls,
        '#supplier-invoice-file-name': fileName,
        '#supplier-invoice-file-remove': fileRemove,
        '#supplier-invoice-file-error': fileError,
        '#supplier-invoice-file-server-error': fileServerError,
        '#supplier-invoice-file-reselect': fileReselect,
        '.supplier-invoice-line-finder': lineFinder,
        '#supplier-invoice-line-search': lineSearch,
        '#supplier-invoice-line-search-next': searchNext,
        '#supplier-invoice-line-search-result': searchResult,
        '[aria-invalid="true"]': errorField,
        '[data-document-base]': documentBase,
        '[data-document-igv]': documentIgv,
        '[data-document-total]': documentTotal,
    };
    const form = node({
        querySelector: (selector) => controls[selector],
        querySelectorAll: (selector) => selector === '[data-invoice-row]'
            ? rows : rows.map((row) => row.quantity),
    });
    const document = {
        readyState: 'complete',
        querySelector: (selector) => selector === '#supplier-invoice-errors' ? (invalidField ? node() : null) : form,
        createTextNode: (textContent) => ({ textContent }),
    };
    vm.runInNewContext(script, { document, Intl });
    return { form, rows, summary, partialToggle, physicalTotal, physicalResult,
        fileInput, fileControls, fileName, fileRemove, fileError, fileServerError, fileReselect, documentTotal,
        lineFinder, lineSearch, searchNext, searchResult, errorField };
};

test('resumen de 1 y 40 líneas usa todos los importes, con y sin IGV', () => {
    const single = render();
    assert.equal(single.summary.textContent, '1 productos · se factura todo el saldo recibido · Total S/ 1,180.00');
    assert.equal(single.rows[0].base.textContent, '1000.00');
    assert.equal(single.rows[0].igv.textContent, '180.00');
    assert.equal(single.documentTotal.textContent, '1,180.00');

    const many = render({ count: 40, unitCost: 10, taxed: false, currency: '$' });
    assert.equal(many.documentTotal.textContent, '4,000.00');
    assert.match(many.summary.textContent, /40 productos.*Total \$ 4,000\.00/);
    assert.equal(many.rows[0].igv.textContent, '0.00');
});

test('15 y 45 líneas mantienen sus importes y la comparación usa el total numérico', () => {
    const fifteen = render({ count: 15 });
    assert.equal(fifteen.rows.length, 15);
    assert.equal(fifteen.documentTotal.textContent, '17,700.00');

    const many = render({ count: 45, unitCost: 10, taxed: false, currency: '$' });
    assert.equal(many.rows.length, 45);
    assert.equal(many.documentTotal.textContent, '4,500.00');
    assert.match(many.summary.textContent, /45 productos.*Total \$ 4,500\.00/);
    many.physicalTotal.value = '4,500.00';
    many.physicalTotal.listeners.input();
    assert.equal(many.physicalResult.textContent, 'Coincide con el total conciliado.');
    many.physicalTotal.value = '4,499.00';
    many.physicalTotal.listeners.input();
    assert.equal(many.physicalResult.textContent, 'Diferencia de $ 1.00 respecto al total conciliado.');
    many.physicalTotal.value = 'incorrecto';
    many.physicalTotal.listeners.input();
    assert.match(many.physicalResult.textContent, /^Importe inválido/);
    assert.equal(many.physicalResult.classList.contains('supplier-invoice-physical-check__result--invalid'), true);
});

test('el localizador de 45 líneas no elimina filas ni modifica cantidades y admite teclado', () => {
    const ui = render({ count: 45, finder: true });
    assert.equal(ui.lineFinder.hidden, false);
    ui.lineSearch.value = 'valvula 45';
    ui.lineSearch.listeners.input();
    assert.match(ui.searchResult.textContent, /1 coincidencia/);
    assert.equal(ui.rows[44].classList.contains('supplier-invoice-line--found'), true);
    assert.equal(ui.rows.length, 45);
    let prevented = false;
    ui.lineSearch.listeners.keydown({ key: 'Enter', preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(ui.rows[44].focused, true);
    assert.equal(ui.rows[44].quantity.value, '10');
    ui.lineSearch.value = 'sin producto';
    ui.lineSearch.listeners.input();
    assert.equal(ui.searchNext.disabled, true);
    assert.match(ui.searchResult.textContent, /Sin coincidencias/);
});

test('la respuesta inválida enfoca el primer control con error', () => {
    const ui = render({ invalidField: true });
    assert.equal(ui.errorField.focused, true);
    assert.equal(ui.errorField.scrollOptions.block, 'center');
});

test('modo parcial marca solo la línea reducida y compara importes visibles', () => {
    const ui = render({ count: 2 });
    ui.partialToggle.checked = true;
    ui.partialToggle.listeners.change();
    ui.rows[0].quantity.value = '5';
    ui.rows[0].quantity.listeners.input();

    assert.equal(ui.summary.textContent, 'Facturación parcial: 1 de 2 líneas con cantidad menor · Total S/ 1,770.00');
    assert.equal(ui.rows[0].classList.contains('supplier-invoice-line--reduced'), true);
    assert.equal(ui.rows[1].classList.contains('supplier-invoice-line--reduced'), false);
    ui.physicalTotal.value = '1770.00';
    ui.physicalTotal.listeners.input();
    assert.equal(ui.physicalResult.textContent, 'Coincide con el total conciliado.');
    ui.physicalTotal.value = '1760.00';
    ui.physicalTotal.listeners.input();
    assert.equal(ui.physicalResult.textContent, 'Diferencia de S/ 10.00 respecto al total conciliado.');

    ui.partialToggle.checked = false;
    ui.partialToggle.listeners.change();
    assert.equal(ui.rows[0].quantity.value, '10');
    assert.equal(ui.rows[0].classList.contains('supplier-invoice-line--reduced'), false);
});

test('archivo mayor de 15 MB se muestra y bloquea el envío; Quitar restablece el campo', () => {
    const ui = render();
    ui.fileInput.files = [{ name: 'comprobante.pdf', size: 16 * 1024 * 1024 }];
    ui.fileInput.listeners.change();
    assert.equal(ui.fileControls.hidden, false);
    assert.match(ui.fileName.textContent, /comprobante\.pdf · 16 MB/);
    assert.equal(ui.fileError.hidden, false);
    assert.equal(ui.fileInput.attributes.has('aria-invalid'), true);
    let prevented = false;
    ui.form.listeners.submit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(ui.fileInput.focused, true);

    ui.fileInput.files = [];
    ui.fileRemove.listeners.click();
    assert.equal(ui.fileError.hidden, true);
    assert.equal(ui.fileName.textContent, 'Ningún archivo seleccionado');

    ui.fileInput.files = [{ name: 'limite.pdf', size: 15 * 1024 * 1024 }];
    ui.fileInput.listeners.change();
    prevented = false;
    ui.form.listeners.submit({ preventDefault() { prevented = true; } });
    assert.equal(ui.fileError.hidden, true);
    assert.equal(ui.fileServerError.hidden, true);
    assert.equal(ui.fileReselect.hidden, true);
    assert.equal(ui.fileInput.attributes.has('aria-invalid'), false);
    assert.equal(prevented, false);
});
