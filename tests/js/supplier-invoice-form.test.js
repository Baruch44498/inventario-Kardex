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
        add(name) { this.values.add(name); },
        remove(name) { this.values.delete(name); },
        toggle(name, on) {
            if (on) this.values.add(name);
            else this.values.delete(name);
        },
        contains(name) { return this.values.has(name); },
    },
    addEventListener(event, callback) { this.listeners[event] = callback; },
    replaceChildren(child) { this.textContent = child.textContent; },
    focus() { this.focused = true; },
    ...values,
});

const render = ({ count = 1, unitCost = 118, taxed = true, currency = 'S/' } = {}) => {
    const summary = node();
    const physicalTotal = node();
    const physicalResult = node();
    const partialToggle = node({ checked: false });
    const fileInput = node({ files: [] });
    const fileControls = node({ hidden: true });
    const fileName = node();
    const fileRemove = node({ hidden: true });
    const fileError = node({ hidden: true });
    const documentBase = node();
    const documentIgv = node();
    const documentTotal = node();
    const rows = Array.from({ length: count }, () => {
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
            querySelector: (selector) => fields[selector],
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
        querySelector: () => form,
        createTextNode: (textContent) => ({ textContent }),
    };
    vm.runInNewContext(script, { document, Intl });
    return { form, rows, summary, partialToggle, physicalTotal, physicalResult,
        fileInput, fileControls, fileName, fileRemove, fileError, documentTotal };
};

test('resumen de 1 y 40 líneas usa todos los importes, con y sin IGV', () => {
    const single = render();
    assert.equal(single.summary.textContent, '1 productos · se factura todo el saldo recibido · Total S/ 1,180.00');
    assert.equal(single.rows[0].base.textContent, '1000.00');
    assert.equal(single.rows[0].igv.textContent, '180.00');
    assert.equal(single.documentTotal.textContent, '1180.00');

    const many = render({ count: 40, unitCost: 10, taxed: false, currency: '$' });
    assert.equal(many.documentTotal.textContent, '4000.00');
    assert.match(many.summary.textContent, /40 productos.*Total \$ 4,000\.00/);
    assert.equal(many.rows[0].igv.textContent, '0.00');
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
    assert.equal(prevented, false);
});
