import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { join } from 'node:path';
import { initializeConsumosNavigation } from '../../resources/js/consumos-navigation.js';
import { initializeHistoryCalendar } from '../../resources/js/consumos-history-calendar.js';

let JSDOM;
try { ({ JSDOM } = createRequire(import.meta.url)('jsdom')); }
catch {
    try {
        ({ JSDOM } = createRequire(process.env.MM_CONSUMOS_DOM_RUNTIME
            ? join(process.env.MM_CONSUMOS_DOM_RUNTIME, 'package.json')
            : new URL('../../output/test-dependencies/package.json', import.meta.url))('jsdom'));
    } catch { /* Report the optional diagnostic dependency as an explicit skip. */ }
}
const domTest = (name, callback) => test(name, { skip: !JSDOM ? 'Optional jsdom diagnostic runtime is unavailable' : false }, callback);
const calendar = (month = '2026-10', day = '15') => `<section id="consumos-history" data-history-url="/admin/consumos/historial" data-history-month="${month}" data-history-day="${month}-${day}"><a id="next-month" href="/admin/consumos?month=2026-11">Siguiente mes</a><section id="consumos-history-day"><h4 id="consumos-history-day-title">${day}</h4></section><p data-history-status></p></section>`;
function setup(search = '') {
    const dom = new JSDOM(`<nav id="consumos-section-nav"><a id="consumos-prepare-link" href="/admin/consumos" aria-controls="consumos-prepare-panel">Preparar consumo</a><a id="consumos-history-link" href="/admin/consumos?section=historial" aria-controls="consumos-history-panel">Historial de consumos</a></nav><section id="consumos-prepare-panel"><input id="stock" value="4 cajas"></section><section id="consumos-history-panel" hidden>${calendar()}</section>`, { url: `http://localhost/admin/consumos${search}` });
    initializeConsumosNavigation(dom.window.document);
    const get = id => dom.window.document.getElementById(id);
    const click = (section, options = {}) => {
        const event = new dom.window.MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...options });
        get(`consumos-${section}-link`).dispatchEvent(event);
        return event;
    };
    return { dom, get, click };
}

domTest('preparation is the default and switching sections preserves unsaved inputs and both panels', () => {
    const { dom, get, click } = setup();
    try {
        const editor = get('consumos-prepare-panel');
        const records = get('consumos-history');
        const stock = get('stock');
        stock.value = '9 cajas sin guardar';
        assert.equal(editor.hidden, false);
        assert.equal(get('consumos-history-panel').hidden, true);
        assert.equal(get('consumos-prepare-link').getAttribute('aria-current'), 'page');
        click('history');
        assert.equal(editor.hidden, true);
        assert.equal(get('consumos-history-panel').hidden, false);
        assert.equal(get('consumos-history-link').getAttribute('aria-current'), 'page');
        assert.equal(get('consumos-prepare-link').hasAttribute('aria-current'), false);
        assert.equal(new URL(dom.window.location.href).searchParams.get('section'), 'historial');
        click('prepare');
        assert.equal(get('consumos-prepare-panel'), editor);
        assert.equal(get('consumos-history'), records);
        assert.equal(get('stock'), stock);
        assert.equal(stock.value, '9 cajas sin guardar');
        assert.equal(editor.hidden, false);
        const entries = dom.window.history.length;
        click('prepare');
        assert.equal(dom.window.history.length, entries, 'Clicking the active section adds no history entry');
    } finally { dom.window.close(); }
});

domTest('dated calendar links, historical bookmarks and explicit section URLs open the intended section', () => {
    for (const [query, historyActive] of [['?section=historial', true], ['?month=2026-10&day=2026-10-15', true], ['#consumos-history-day', true], ['?section=preparar&month=2026-10', false]]) {
        const { dom, get } = setup(query);
        try {
            assert.equal(get('consumos-prepare-panel').hidden, historyActive);
            assert.equal(get('consumos-history-panel').hidden, !historyActive);
        } finally { dom.window.close(); }
    }
});

domTest('browser history changes section without losing an editor input or pushing another URL', () => {
    const { dom, get, click } = setup();
    try {
        click('history');
        click('prepare');
        const count = dom.window.history.length;
        dom.window.history.replaceState(null, '', '?section=historial');
        dom.window.dispatchEvent(new dom.window.PopStateEvent('popstate'));
        assert.equal(get('consumos-history-panel').hidden, false);
        assert.equal(get('stock').value, '4 cajas');
        assert.equal(dom.window.history.length, count);
    } finally { dom.window.close(); }
});

domTest('calendar refresh and navigation between sections preserve the selected month and avoid extra requests', async () => {
    const { dom, get, click } = setup();
    try {
        let calls = 0;
        dom.window.fetch = async () => { calls++; return { ok: true, text: async () => calendar('2026-11', '01') }; };
        initializeHistoryCalendar(dom.window.document);
        click('history');
        get('next-month').click();
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(calls, 1);
        const november = get('consumos-history');
        click('prepare');
        click('history');
        assert.equal(get('consumos-history'), november);
        assert.equal(november.dataset.historyMonth, '2026-11');
        assert.equal(new URL(dom.window.location.href).searchParams.get('month'), '2026-11');
        dom.window.history.replaceState(null, '', '?section=preparar');
        dom.window.dispatchEvent(new dom.window.PopStateEvent('popstate'));
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(calls, 1, 'Going back to preparation must not fetch or reset the hidden calendar');
        assert.equal(get('consumos-prepare-panel').hidden, false);
    } finally { dom.window.close(); }
});

domTest('modified clicks keep native link behavior for opening another tab', () => {
    const { dom, get, click } = setup();
    try {
        dom.window.document.addEventListener('click', event => {
            assert.equal(event.defaultPrevented, false);
            event.preventDefault(); // jsdom cannot perform native new-tab navigation.
        });
        click('history', { ctrlKey: true });
        assert.equal(get('consumos-prepare-panel').hidden, false);
        assert.equal(dom.window.location.search, '');
    } finally { dom.window.close(); }
});
