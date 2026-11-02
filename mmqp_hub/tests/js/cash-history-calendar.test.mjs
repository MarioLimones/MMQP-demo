import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
let JSDOM;
try { ({ JSDOM } = require(process.env.MMQP_JSDOM_MODULE || 'jsdom')); } catch {}
const domOptions = { skip: !JSDOM && 'Optional jsdom diagnostic runtime is unavailable' };
const alpine = readFileSync(new URL('../../node_modules/alpinejs/dist/cdn.js', import.meta.url), 'utf8');
const view = readFileSync(new URL('../../resources/views/admin/cuadre/historial.blade.php', import.meta.url), 'utf8');
const opening = view.match(/<div class="cash-calendar [\s\S]*?">/)[0];
const tick = () => new Promise(resolve => setTimeout(resolve, 50));

async function calendar() {
    const dom = new JSDOM(`${opening}
        <button data-cash-date="2026-11-02" @click="openDay('2026-11-02', $el)" :aria-expanded="selectedDate === '2026-11-02'">2</button>
        <button data-cash-date="2026-11-03" @click="openDay('2026-11-03', $el)" :aria-expanded="selectedDate === '2026-11-03'">3</button>
        <aside x-ref="dayPanel" x-show="selectedDate" :style="{left: panelLeft + 'px', top: panelTop + 'px'}">
            <button x-ref="dayHeading" @click="closeDay()">Fecha seleccionada</button>
            <div x-ref="dayContent">Apertura y cierre</div>
        </aside></div>`, { runScripts: 'dangerously', pretendToBeVisual: true });
    const { window } = dom;
    const document = window.document;
    Object.defineProperty(window, 'innerWidth', { value: 555, configurable: true });
    document.querySelector('.cash-calendar').getBoundingClientRect = () => ({ left: 16, right: window.innerWidth - 16 });
    for (const day of document.querySelectorAll('[data-cash-date]')) {
        day.getBoundingClientRect = () => ({ left: 16, right: 80, top: 150 });
    }
    document.querySelector('aside').getBoundingClientRect = () => ({ width: Math.min(360, window.innerWidth - 24), height: 400 });
    window.eval(alpine);
    await tick();
    return { dom, window, document, day: document.querySelector('[data-cash-date]'), panel: document.querySelector('aside') };
}

test('day details survive scrolling and outside clicks until the date is toggled again', domOptions, async () => {
    const { dom, window, document, day, panel } = await calendar();
    try {
        day.click(); await tick();
        assert.equal(day.getAttribute('aria-expanded'), 'true');
        window.dispatchEvent(new window.Event('scroll'));
        document.body.click(); await tick();
        assert.notEqual(panel.style.display, 'none');
        assert.equal(day.getAttribute('aria-expanded'), 'true');
        day.click(); await tick();
        assert.equal(panel.style.display, 'none');
        assert.equal(document.activeElement, day);
    } finally { dom.window.close(); }
});

test('an open day fits after resize and can be closed using its date or Escape', domOptions, async () => {
    const { dom, window, document, day, panel } = await calendar();
    try {
        day.click(); await tick();
        Object.defineProperty(window, 'innerWidth', { value: 320, configurable: true });
        window.dispatchEvent(new window.Event('resize')); await tick();
        assert.equal(day.getAttribute('aria-expanded'), 'true');
        assert.ok(parseFloat(panel.style.left) >= 12);
        assert.ok(parseFloat(panel.style.left) + 296 <= 320);
        document.querySelector('[x-ref="dayHeading"]').click(); await tick();
        assert.equal(panel.style.display, 'none');
        day.click(); await tick();
        window.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true })); await tick();
        assert.equal(panel.style.display, 'none');
    } finally { dom.window.close(); }
});
