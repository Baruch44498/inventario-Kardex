import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const directory = path.dirname(fileURLToPath(import.meta.url));
const script = fs.readFileSync(path.join(directory, '../../public/js/supplier-invoice-tabs.js'), 'utf8');

const renderPayment = ({ balance, full, max }) => {
    const amount = {
        value: '', max: String(max), dataset: {
            supplierInvoicePaymentBalance: String(balance), supplierInvoicePaymentFull: String(full),
        },
        listeners: {},
        addEventListener(name, handler) { this.listeners[name] = handler; },
        focus() { this.focused = true; },
    };
    const preview = { textContent: '' };
    const fill = {
        hidden: true, listeners: {},
        addEventListener(name, handler) { this.listeners[name] = handler; },
    };
    const controls = {
        '[data-supplier-invoice-payment-amount]': amount,
        '[data-supplier-invoice-payment-preview]': preview,
        '[data-supplier-invoice-payment-fill]': fill,
    };
    const form = { dataset: { currency: 'PEN' }, querySelector: (selector) => controls[selector] };
    const document = {
        readyState: 'complete',
        querySelectorAll: (selector) => selector === '[data-supplier-invoice-payment-form]' ? [form] : [],
    };
    vm.runInNewContext(script, { document, Intl });
    return { amount, preview, fill };
};

test('pago de factura anterior redondea 6998.5287 a 6998.53 y marca saldo cero', () => {
    const { amount, preview, fill } = renderPayment({ balance: 6998.5287, full: 6998.53, max: 6998.53 });
    assert.equal(fill.hidden, false);
    fill.listeners.click();
    assert.equal(amount.value, '6998.53');
    assert.equal(amount.focused, true);
    assert.match(preview.textContent, /Saldo después de este pago: S\/ 0\.00/);

    amount.value = '6998.529';
    amount.listeners.input();
    assert.match(preview.textContent, /Revisa el monto/);

    amount.value = '100';
    amount.listeners.input();
    assert.match(preview.textContent, /6,898\.5287/);
});

test('pago final hacia abajo deja saldo cero sin ocultar el importe original', () => {
    const { amount, preview, fill } = renderPayment({ balance: 6898.5244, full: 6898.52, max: 6898.5244 });
    assert.match(preview.textContent, /6,898\.5244/);
    fill.listeners.click();
    assert.equal(amount.value, '6898.52');
    assert.match(preview.textContent, /S\/ 0\.00/);
});
