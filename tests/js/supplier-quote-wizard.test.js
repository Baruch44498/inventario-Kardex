import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const source = fs.readFileSync(path.join(root, 'public/js/cotizacion-proveedor-wizard.js'), 'utf8');
const module = { exports: {} };
vm.runInNewContext(source, { module, document: { addEventListener() {} } });
const {
    supplierQuoteDecimalFromPaste: decimal,
    supplierQuotePastePlan: plan,
    supplierQuoteRequestedAmount: requestedAmount,
    supplierQuoteBindOptionsToggle: bindOptionsToggle,
    supplierQuoteSyncLineBadges: syncLineBadges,
} = module.exports;

test('el pegado entiende coma o punto decimal y no transforma cifras ambiguas en cero', () => {
    assert.equal(decimal('12,50'), '12.50');
    assert.equal(decimal('12.50'), '12.50');
    assert.equal(decimal('1.234,56'), '1234.56');
    assert.equal(decimal('1,234.56'), '1234.56');
    assert.equal(decimal('-4,50'), null);
    assert.equal(decimal('fórmula'), null);
});

test('el plan de pegado respeta el orden con 1, 5, 20 y 60 líneas', () => {
    for (const count of [1, 5, 20, 60]) {
        const pasted = Array.from({ length: count }, (_, i) => `${i + 1},50`).join('\n');
        const result = plan(pasted, count);
        assert.equal(result.values.length, count);
        assert.equal(result.overflow, 0);
        assert.deepEqual(Array.from(result.values, ({ position }) => position),
            Array.from({ length: count }, (_, i) => i));
        assert.equal(result.values[count - 1].value, `${count}.50`);
    }
});

test('ignora filas vacías y cuenta valores sin fila o inválidos', () => {
    const result = plan('1,20\n\n3,40\ntexto\n5,60', 3);
    assert.equal(result.values.length, 3);
    assert.equal(result.values[0].value, '1.20');
    assert.equal(result.values[1].value, '3.40');
    assert.equal(result.values[2].value, null);
    assert.equal(result.overflow, 1);
});

test('cantidad solicitada conserva unidades exactas ante presentaciones indivisibles', () => {
    assert.deepEqual({ ...requestedAmount(24, 12) }, { quantity: 2, useBase: false });
    assert.deepEqual({ ...requestedAmount(8, 12) }, { quantity: 8, useBase: true });
    assert.deepEqual({ ...requestedAmount(2.5, 1) }, { quantity: 2.5, useBase: false });
});

const editableLine = (initialOptions = '0') => {
    const quantity = { value: '8' };
    const options = {
        hidden: false,
        listeners: {},
        contains(element) { return element === quantity; },
        addEventListener(name, listener) { this.listeners[name] = listener; },
    };
    const toggle = {
        attributes: {}, listeners: {}, focusCount: 0, textContent: '',
        setAttribute(name, value) { this.attributes[name] = value; },
        addEventListener(name, listener) { this.listeners[name] = listener; },
        focus() { this.focusCount += 1; },
        click() { this.listeners.click(); },
    };
    const controls = {
        '[data-line-options]': options,
        '[data-line-options-toggle]': toggle,
    };
    return {
        quantity, options, toggle,
        line: { dataset: { initialOptions }, querySelector(selector) { return controls[selector]; } },
    };
};

test('1, 5, 20 y 60 líneas completas inician cerradas y conservan entradas al consultar detalles', () => {
    for (const count of [1, 5, 20, 60]) {
        const rows = Array.from({ length: count }, () => editableLine());
        rows.forEach(({ line }) => bindOptionsToggle(line, () => null));
        assert.ok(rows.every(({ options, toggle }) =>
            options.hidden && toggle.attributes['aria-expanded'] === 'false'
                && toggle.textContent === 'Más detalles'));
        const target = rows[count - 1];
        target.toggle.click();
        assert.equal(target.options.hidden, false);
        assert.equal(target.toggle.attributes['aria-expanded'], 'true');
        assert.equal(target.toggle.textContent, 'Ocultar detalles');
        target.toggle.click();
        assert.equal(target.quantity.value, '8');
        assert.ok(rows.slice(0, -1).every(({ options }) => options.hidden));
    }
});

test('un detalle pendiente inicia abierto y Escape devuelve el foco sin borrar datos', () => {
    const target = editableLine('1');
    bindOptionsToggle(target.line, () => target.quantity);
    assert.equal(target.toggle.attributes['aria-expanded'], 'true');
    let prevented = false;
    target.options.listeners.keydown({
        key: 'Escape', preventDefault() { prevented = true; },
    });
    assert.equal(prevented, true);
    assert.equal(target.options.hidden, true);
    assert.equal(target.toggle.focusCount, 1);
    assert.equal(target.toggle.textContent, 'Más detalles');
    assert.equal(target.quantity.value, '8');
});

test('badges siguen vinculación, descuento efectivo, presentación y datos secundarios', () => {
    const controls = {
        '[data-line-link-type]': { value: 'SOLICITADO' },
        '[data-line-requisition-detail]': { value: '42' },
        '[data-line-alternative]': { hidden: true },
        '[data-line-discount-mode]': { value: 'SIN_DESCUENTO' },
        '[data-line-discount-type]': { value: '' },
        '[data-line-discount-value]': { value: '' },
        '[data-line-discount-badge]': { hidden: true },
        '[data-line-presentation]': { selectedOptions: [{ value: '', dataset: {} }] },
        '[data-line-presentation-badge]': { hidden: true, textContent: '' },
        '[data-line-brand]': { value: '' },
        '[data-line-brand-summary]': { hidden: true, textContent: '' },
        '[data-line-observation]': { value: 'Del requerimiento' },
        '[data-line-note-summary]': { hidden: true },
    };
    const line = { querySelector(selector) { return controls[selector]; } };
    syncLineBadges(line, true);
    assert.equal(controls['[data-line-note-summary]'].hidden, true);
    controls['[data-line-link-type]'].value = 'ALTERNATIVA';
    controls['[data-line-discount-mode]'].value = 'APLICAR';
    controls['[data-line-discount-type]'].value = 'PORCENTAJE';
    controls['[data-line-discount-value]'].value = '5';
    controls['[data-line-presentation]'].selectedOptions[0] = {
        value: '9', dataset: { name: 'Caja' }, textContent: 'Caja',
    };
    controls['[data-line-brand]'].value = 'Marca A';
    syncLineBadges(line);
    assert.equal(controls['[data-line-alternative]'].hidden, false);
    assert.equal(controls['[data-line-discount-badge]'].hidden, false);
    assert.equal(controls['[data-line-presentation-badge]'].textContent, 'Presentación: Caja');
    assert.equal(controls['[data-line-brand-summary]'].textContent, 'Marca: Marca A');
    assert.equal(controls['[data-line-note-summary]'].hidden, false);
    controls['[data-line-requisition-detail]'].value = '';
    controls['[data-line-discount-value]'].value = '0';
    controls['[data-line-presentation]'].selectedOptions[0].value = '';
    syncLineBadges(line);
    assert.equal(controls['[data-line-alternative]'].hidden, true);
    assert.equal(controls['[data-line-discount-badge]'].hidden, true);
    assert.equal(controls['[data-line-presentation-badge]'].hidden, true);
});
