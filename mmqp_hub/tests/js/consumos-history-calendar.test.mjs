import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { join } from 'node:path';
import { initializeHistoryCalendar } from '../../resources/js/consumos-history-calendar.js';

let JSDOM;
try { ({ JSDOM } = createRequire(import.meta.url)('jsdom')); }
catch {
    try {
        const require = createRequire(process.env.MM_CONSUMOS_DOM_RUNTIME
            ? join(process.env.MM_CONSUMOS_DOM_RUNTIME, 'package.json')
            : new URL('../../output/test-dependencies/package.json', import.meta.url));
        ({ JSDOM } = require('jsdom'));
    } catch { /* Match the editor's optional DOM test runtime. */ }
}
const domTest = (name, callback) => test(name, { skip: !JSDOM ? 'Optional jsdom diagnostic runtime is unavailable' : false }, callback);
const markup = (day = '15', month = '2026-10') => `<section id="consumos-history" data-history-url="/admin/consumos/historial" data-history-month="${month}" data-history-day="${month}-${day}">
    <nav><a href="/admin/consumos?month=2026-11">Siguiente mes</a><a href="/admin/consumos?month=2026-10&day=2026-10-21">Hoy</a></nav>
    <form class="consumos-history-month-form" action="/admin/consumos#consumos-history"><input id="month" name="month" type="month" value="${month}"><button>Ver</button></form>
    <table><tbody><tr>${['15', '20', '21'].map(date => `<td class="${day === date ? 'is-selected' : ''}"><a data-history-date="${month}-${date}" aria-label="Día ${date}${day === date ? '. Día seleccionado' : ''}" href="/admin/consumos?month=${month}&day=${month}-${date}#consumos-history-day">${date}</a></td>`).join('')}</tr></tbody></table>
    <p data-history-status role="status"></p><section id="consumos-history-day"><h4 id="consumos-history-day-title">${day} del ${month}</h4>Registro ${day}</section>
</section>`;
const tick = () => new Promise(resolve => setImmediate(resolve));
function setup(fetcher = async () => ({ ok: true, text: async () => markup('20') })) {
    const dom = new JSDOM(`<div id="shell"><input id="draft" value="Mi borrador">${markup()}</div>`, { url: 'http://localhost/admin/consumos?month=2026-10&day=2026-10-15&ficha=7' });
    dom.window.fetch = fetcher;
    initializeHistoryCalendar(dom.window.document);
    const click = (date, options = {}) => dom.window.document.querySelector(`[data-history-date="2026-10-${date}"]`).dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...options }));
    return { dom, document: dom.window.document, click };
}

domTest('selecting a day updates only its records and URL, preserving calendar, draft and focus', async () => {
    const requests = [];
    const env = setup(async (...args) => { requests.push(args); return { ok: true, text: async () => markup('20') }; });
    const calendar = env.document.getElementById('consumos-history');
    const link = env.document.querySelector('[data-history-date="2026-10-20"]');
    const draft = env.document.getElementById('draft');
    link.focus(); env.click('20'); await tick();
    assert.equal(env.document.getElementById('consumos-history'), calendar);
    assert.equal(env.document.getElementById('draft'), draft);
    assert.equal(draft.value, 'Mi borrador');
    assert.equal(env.document.activeElement, link);
    assert.equal(link.parentElement.classList.contains('is-selected'), true);
    assert.equal(calendar.dataset.historyDay, '2026-10-20');
    assert.equal(env.dom.window.location.search, '?ficha=7&month=2026-10&day=2026-10-20');
    assert.match(requests[0][0], /\/historial\?month=2026-10&day=2026-10-20$/);
    assert.equal(calendar.hasAttribute('aria-busy'), false);
    env.dom.window.close();
});

domTest('a slow old response cannot replace the latest day, even if it ignores cancellation', async () => {
    const requests = [];
    const env = setup((url, options) => new Promise(resolve => requests.push({ resolve, options })));
    env.click('20'); env.click('21');
    assert.equal(requests[0].options.signal.aborted, true);
    requests[1].resolve({ ok: true, text: async () => markup('21') }); await tick();
    requests[0].resolve({ ok: true, text: async () => markup('20') }); await tick();
    assert.equal(env.document.getElementById('consumos-history').dataset.historyDay, '2026-10-21');
    env.dom.window.close();
});

domTest('failed or expired requests keep the last records and allow retry without reloading', async () => {
    for (const response of [{ ok: false }, { ok: true, redirected: true }, { ok: true, text: async () => '<html>Login</html>' }]) {
        let fail = true;
        const env = setup(async () => fail ? response : { ok: true, text: async () => markup('20') });
        env.click('20'); await tick();
        assert.match(env.document.querySelector('[data-history-status]').textContent, /No se han podido cargar/);
        assert.equal(env.document.getElementById('consumos-history').dataset.historyDay, '2026-10-15');
        assert.match(env.dom.window.location.search, /day=2026-10-15/);
        fail = false; env.click('20'); await tick();
        assert.equal(env.document.getElementById('consumos-history').dataset.historyDay, '2026-10-20');
        env.dom.window.close();
    }
});

domTest('month form replaces only the calendar and back restores the day without pushing another entry', async () => {
    const env = setup(async url => ({ ok: true, text: async () => url.includes('month=2026-11') ? markup('01', '2026-11') : markup('15') }));
    const shell = env.document.getElementById('shell');
    env.document.getElementById('month').value = '2026-11';
    env.document.querySelector('form').dispatchEvent(new env.dom.window.Event('submit', { bubbles: true, cancelable: true })); await tick();
    assert.equal(env.document.getElementById('shell'), shell);
    assert.equal(env.document.getElementById('consumos-history').dataset.historyMonth, '2026-11');
    env.dom.window.history.back();
    await new Promise(resolve => env.dom.window.setTimeout(resolve, 20));
    assert.equal(env.document.getElementById('consumos-history').dataset.historyDay, '2026-10-15');
    assert.equal(env.dom.window.history.length, 2);
    env.dom.window.close();
});

domTest('modified clicks retain native navigation and a save refresh discards any older navigation response', async () => {
    const requests = [];
    const env = setup(() => new Promise(resolve => requests.push(resolve)));
    // The default is preserved for opening links in another tab.
    const link = env.document.querySelector('[data-history-date="2026-10-20"]');
    const event = new env.dom.window.MouseEvent('click', { bubbles: true, cancelable: true, ctrlKey: true });
    // Avoid jsdom trying to perform the native navigation after checking the handler.
    env.document.addEventListener('click', current => {
        if (current.ctrlKey) { assert.equal(current.defaultPrevented, false); current.preventDefault(); }
    });
    link.dispatchEvent(event); assert.equal(requests.length, 0);
    env.click('20');
    env.document.getElementById('consumos-history').outerHTML = markup('21');
    requests[0]({ ok: true, text: async () => markup('20') }); await tick();
    assert.equal(env.document.getElementById('consumos-history').dataset.historyDay, '2026-10-21');
    env.click('20'); requests[1]({ ok: true, text: async () => markup('20') }); await tick();
    assert.equal(env.document.getElementById('consumos-history').dataset.historyDay, '2026-10-20');
    env.dom.window.close();
});

domTest('the jump-to-records anchor keeps its native scrolling without fetching or pushing a day', async () => {
    let calls = 0;
    const env = setup(async () => { calls++; return { ok: true, text: async () => markup('20') }; });
    const anchor = env.document.createElement('a');
    anchor.href = '#consumos-history-day'; anchor.textContent = 'Ir a los registros';
    env.document.getElementById('consumos-history').append(anchor);
    const click = new env.dom.window.MouseEvent('click', { bubbles: true, cancelable: true });
    anchor.dispatchEvent(click);
    assert.equal(click.defaultPrevented, false);
    await tick();
    assert.equal(calls, 0);
    env.dom.window.close();
});
