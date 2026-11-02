import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { initializeArqueo } from '../../resources/js/arqueo-editor.js';
import { initializeArqueoDaily, dailyValues } from '../../resources/js/arqueo-daily.js';

let JSDOM;
try { ({ JSDOM } = createRequire(import.meta.url)(process.env.MMQP_JSDOM_MODULE || 'jsdom')); } catch {}
const domTest = (name, callback) => test(name, { skip: !JSDOM && 'A jsdom runtime is required for editor integration.' }, callback);
const turn = () => new Promise(resolve => setTimeout(resolve, 0));
function boot(overrides = {}) {
    const feedback = `<p id="editor-status"${overrides.quietInitialStatus ? ' class="arqueo-feedback" hidden' : ''}></p>`;
    const dom = new JSDOM(`<!doctype html><html><body><button id="btn-save-daily"></button><button id="btn-edit-mode"></button><button id="btn-save-template"></button>${feedback}<table id="arqueo-grid"><tbody></tbody></table></body></html>`, { pretendToBeVisual: true });
    const config = { dailyMode: true, canEditDesign: true, templateHash: 'base-hash', dailySaveUrl: '/arqueo/dia', saveUrl: '/template', fecha: '2026-10-19', templateData: { html: '<tr><td><div class="cell-content">INGRESOS</div></td><td data-arqueo-input="true" class="b-bottom"><div class="cell-content"></div></td><td data-bound-emp="4" data-bound-prop="manana"><div class="cell-content"></div></td></tr>' }, empleadosDia: { 4: { manana: '12:00-16:30' } }, ...overrides };
    const document = dom.window.document;
    const api = initializeArqueo(config, document);
    const daily = initializeArqueoDaily(config, document);
    const cell = api.tbody.rows[0].cells[1];
    const write = text => { cell.querySelector('.cell-content').textContent = text; cell.querySelector('.cell-content').dispatchEvent(new dom.window.Event('input', { bubbles: true })); };
    return { dom, config, document, api, daily, cell, write };
}

domTest('document editing starts ready to edit while the daily view keeps labels locked', () => {
    for (const canEditDesign of [true, false]) {
        const env = boot({ startInEditMode: true, canEditDesign });
        try {
            assert.equal(env.api.editMode, canEditDesign);
            assert.equal(env.document.body.classList.contains('edit-mode'), canEditDesign);
            assert.equal(env.api.tbody.rows[0].cells[0].firstElementChild.getAttribute('contenteditable'), canEditDesign ? 'plaintext-only' : 'false');
            assert.equal(env.api.dirty, false);
            assert.equal(env.document.getElementById('btn-save-daily').disabled, canEditDesign);
        } finally { env.dom.window.close(); }
    }
});

domTest('quiet document startup keeps feedback hidden until an edit needs saving', () => {
    const env = boot({ startInEditMode: true, quietInitialStatus: true, initialMessage: 'Plantilla lista para editar.' });
    try {
        const feedback = env.document.getElementById('editor-status');
        assert.equal(env.api.editMode, true);
        assert.equal(env.api.dirty, false);
        assert.equal(feedback.textContent, '');
        assert.equal(feedback.hidden, true);

        env.write('ETIQUETA ACTUALIZADA');

        assert.equal(env.api.dirty, true);
        assert.equal(feedback.hidden, false);
        assert.match(feedback.textContent, /Cambios pendientes de guardar/);
        assert.equal(feedback.getAttribute('aria-live'), 'polite');
    } finally { env.dom.window.close(); }
});

domTest('daily data is saved for its date while template export and template saves stay empty', async () => {
    const env = boot();
    try {
        const requests = [];
        env.dom.window.fetch = async (url, options) => { requests.push({ url, body: JSON.parse(options.body) }); return { ok: true, json: async () => ({ success: true, templateHash: 'new-hash' }) }; };
        env.write('123,45');
        assert.equal(env.api.dirty, true);
        assert.match(env.api.getHtml(), /123,45/);
        assert.doesNotMatch(env.api.getTemplateHtml(), /123,45|12:00-16:30/);
        env.document.getElementById('btn-save-daily').click();
        await turn();
        assert.deepEqual(requests[0], { url: '/arqueo/dia', body: { fecha: '2026-10-19', templateHash: 'base-hash', values: { '0-1': '123,45' } } });
        assert.equal(env.api.dirty, false);
        env.dom.window.toggleEditMode();
        assert.equal(env.document.getElementById('btn-save-daily').disabled, true);
        await env.api.saveTemplate();
        assert.doesNotMatch(requests[1].body.template.html, /123,45/);
        assert.equal(env.config.templateHash, 'new-hash');
        assert.match(env.api.getHtml(), /123,45/);
    } finally { env.dom.window.close(); }
});

domTest('employees can only fill daily fields and cannot edit template or paste over labels and shifts', () => {
    const env = boot({ canEditDesign: false });
    try {
        const [label, input, shift] = env.api.tbody.rows[0].cells;
        assert.equal(label.firstElementChild.contentEditable || label.firstElementChild.getAttribute('contenteditable'), 'false');
        assert.equal(input.firstElementChild.getAttribute('contenteditable'), 'plaintext-only');
        assert.equal(shift.firstElementChild.getAttribute('contenteditable'), 'false');
        env.dom.window.toggleEditMode();
        assert.equal(env.api.editMode, false);
        const paste = new env.dom.window.Event('paste', { bubbles: true, cancelable: true });
        paste.clipboardData = { getData: type => type === 'text/plain' ? '10\t20' : '' };
        env.api.selectCell(input);
        input.firstElementChild.dispatchEvent(paste);
        assert.match(shift.textContent, /12:00-16:30/);
        assert.equal(input.textContent, '');
        assert.equal(env.api.dirty, false);
    } finally { env.dom.window.close(); }
});

domTest('failed daily save preserves the entered values and dirty warning', async () => {
    const env = boot();
    try {
        env.dom.window.fetch = async () => ({ ok: false, status: 500, json: async () => ({ success: false, message: 'No se puede guardar' }) });
        env.write('999,99');
        env.document.getElementById('btn-save-daily').click();
        await turn();
        assert.equal(env.api.dirty, true);
        assert.match(env.api.getHtml(), /999,99/);
        assert.match(env.document.getElementById('editor-status').textContent, /No se puede guardar/);
        assert.equal(env.document.getElementById('btn-save-daily').disabled, false);
    } finally { env.dom.window.close(); }
});

domTest('edits made during a daily save stay pending after the earlier values are saved', async () => {
    const env = boot();
    try {
        let finish;
        env.dom.window.fetch = () => new Promise(resolve => { finish = resolve; });
        env.write('10');
        env.document.getElementById('btn-save-daily').click();
        env.write('20');
        finish({ ok: true, json: async () => ({ success: true }) });
        await turn();
        assert.match(env.api.getHtml(), />20</);
        assert.equal(env.api.dirty, true);
    } finally { env.dom.window.close(); }
});

domTest('saving a template cannot dismiss pending unsaved daily values', async () => {
    const env = boot();
    try {
        env.dom.window.fetch = async () => ({ ok: true, json: async () => ({ success: true, templateHash: 'new' }) });
        env.write('75,00');
        env.dom.window.toggleEditMode();
        await env.api.saveTemplate();
        assert.equal(env.api.dirty, true);
        assert.match(env.api.getHtml(), /75,00/);
    } finally { env.dom.window.close(); }
});

domTest('editing an input as a design label makes it reusable; daily multiline values retain line breaks', () => {
    const env = boot();
    try {
        env.dom.window.toggleEditMode();
        env.write('NUEVA ETIQUETA');
        assert.match(env.api.getTemplateHtml(), /NUEVA ETIQUETA/);
        env.dom.window.toggleEditMode();
        assert.equal(env.cell.firstElementChild.getAttribute('contenteditable'), 'false');
        assert.deepEqual(dailyValues('<tr><td data-arqueo-input="true"><div class="cell-content">Uno<br>Dos</div></td></tr>', env.document), { '0-0': 'Uno\nDos' });
        env.document.getElementById('btn-save-daily').click();
        assert.match(env.document.getElementById('editor-status').textContent, /plantilla tiene cambios sin guardar/);
    } finally { env.dom.window.close(); }
});
