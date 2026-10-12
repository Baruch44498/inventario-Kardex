import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const directory = path.dirname(fileURLToPath(import.meta.url));
const script = fs.readFileSync(path.join(directory, '../../public/js/operation-detail-tabs.js'), 'utf8');

const fixture = ({ reducedMotion = false } = {}) => {
    const names = ['resumen', 'ejecucion', 'materiales'];
    const tabs = names.map((name) => ({
        dataset: { operationTab: name },
        attributes: {},
        setAttribute(key, value) { this.attributes[key] = value; },
        removeAttribute(key) { delete this.attributes[key]; },
        focus() { document.activeElement = this; },
        closest() { return this; },
    }));
    const panels = names.map((name) => ({
        dataset: { operationPanel: name }, hidden: false,
        querySelector: () => null,
    }));
    const handlers = {};
    const tabList = {
        querySelectorAll: () => tabs,
        addEventListener(name, handler) { handlers[name] = handler; },
        contains: (button) => tabs.includes(button),
        getBoundingClientRect: () => ({ top: 15 }),
        scrollIntoView(options) { this.scrollOptions = options; },
    };
    const root = {
        dataset: {}, classList: { add() {} },
        querySelector: () => tabList,
        querySelectorAll: () => panels,
    };
    const document = {
        readyState: 'complete', activeElement: null,
        querySelector: () => root,
        getElementById: () => null,
    };
    const window = {
        location: { hash: '' }, innerHeight: 800,
        history: { replaceState(_, __, hash) { window.location.hash = hash; } },
        matchMedia: () => ({ matches: reducedMotion }),
        addEventListener() {},
    };
    vm.runInNewContext(script, { document, window });
    return { tabs, panels, handlers, tabList, document, window };
};

test('las pestañas indican la activa y conservan el panel al cambiar con teclado', () => {
    const ui = fixture();
    assert.equal(ui.tabs[0].attributes['aria-current'], 'page');
    assert.equal(ui.panels[1].hidden, true);
    ui.document.activeElement = ui.tabs[0];
    let prevented = false;
    ui.handlers.keydown({ key: 'ArrowRight', preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(ui.tabs[0].attributes['aria-current'], undefined);
    assert.equal(ui.tabs[1].attributes['aria-current'], 'page');
    assert.equal(ui.tabs[1].attributes['aria-selected'], 'true');
    assert.equal(ui.panels[1].hidden, false);
    assert.equal(ui.window.location.hash, '#ejecucion');
});

test('el desplazamiento de la navegación respeta movimiento reducido', () => {
    const ui = fixture({ reducedMotion: true });
    ui.handlers.click({ target: ui.tabs[2] });
    assert.equal(ui.tabList.scrollOptions.behavior, 'auto');
    assert.equal(ui.tabs[2].attributes['aria-current'], 'page');
});
