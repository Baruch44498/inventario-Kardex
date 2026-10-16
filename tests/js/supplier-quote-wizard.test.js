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
