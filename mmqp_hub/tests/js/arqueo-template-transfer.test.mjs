import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { initializeArqueo } from '../../resources/js/arqueo-editor.js';
import { createTemplateFile, MAX_TEMPLATE_FILE_BYTES } from '../../resources/js/arqueo-template-file.js';
import { initializeTemplateTransfer } from '../../resources/js/arqueo-template-transfer.js';

let JSDOM;
try { ({ JSDOM } = createRequire(import.meta.url)(process.env.MMQP_JSDOM_MODULE || 'jsdom')); } catch {}
const domTest = (name, callback) => test(name, { skip: !JSDOM }, callback);
const options = { enableImportGeometry: true };
const sample = '<tr data-arqueo-columns="[50,50]" data-arqueo-rows="[138,138]"><td colspan="2" class="b-top b-left b-right"><div class="cell-content" style="font-size: 18px; font-weight: bold">CAJA DE PRUEBA</div></td></tr><tr><td class="solid-border" style="background-color: rgb(230, 230, 230)"><div class="cell-content">Efectivo</div></td><td class="solid-border"><div class="cell-content"></div></td></tr>';
const turn = () => new Promise(resolve => setTimeout(resolve, 0));

function boot({ realEditor = false } = {}) {
    const dom = new JSDOM(`<!doctype html><html><body><button id="btn-export-template">Exportar plantilla</button><button id="btn-import-template">Importar plantilla</button><input type="file" id="arqueo-template-file"><button id="btn-save-template">Guardar diseño</button><p id="editor-status"></p><table id="arqueo-grid"><tbody></tbody></table></body></html>`, { pretendToBeVisual: true });
    const { document } = dom.window;
    const statuses = [];
    const loads = [];
    let currentHtml = sample;
    let downloads = [];
    let changedMode = false;
    const blobs = [];
    dom.window.URL.createObjectURL = blob => { blobs.push(blob); return 'blob:qa-template'; };
    dom.window.URL.revokeObjectURL = () => {};
    dom.window.HTMLAnchorElement.prototype.click = function () { downloads.push({ filename: this.download, href: this.href }); };
    let api;
    if (realEditor) api = initializeArqueo(options, document);
    else {
        api = { getHtml: () => currentHtml, loadHtml: html => { loads.push(html); currentHtml = html; }, editMode: false, dirty: true, status: (message, error) => statuses.push({ message, error }) };
        dom.window.arqueoSheet = api;
        dom.window.toggleEditMode = () => { changedMode = true; };
    }
    const transfer = initializeTemplateTransfer({ ...options, fecha: '2026-10-20' }, document);
    const fileInput = document.getElementById('arqueo-template-file');
    const select = file => {
        Object.defineProperty(fileInput, 'files', { configurable: true, value: [file] });
        fileInput.dispatchEvent(new dom.window.Event('change'));
    };
    const json = () => createTemplateFile(sample, document, options);
    return { dom, document, api, transfer, statuses, loads, blobs, get downloads() { return downloads; }, get changedMode() { return changedMode; }, select, json, setCurrent: value => { currentHtml = value; } };
}

domTest('export downloads the open unsaved design locally without marking it saved', () => {
    const env = boot();
    try {
        env.document.getElementById('btn-export-template').click();
        assert.deepEqual(env.downloads, [{ filename: 'plantilla-arqueo-2026-10-20.json', href: 'blob:qa-template' }]);
        assert.equal(env.blobs.length, 1);
        assert.equal(env.blobs[0].type, 'application/json;charset=utf-8');
        assert.equal(env.api.dirty, true);
        assert.deepEqual(env.loads, []);
        assert.match(env.statuses.at(-1).message, /exportada/);
        assert.equal(env.document.querySelector('a[download]'), null);
    } finally { env.dom.window.close(); }
});

domTest('a downloaded design can restore a blank editor and the complete import can be undone', async () => {
    const env = boot({ realEditor: true });
    try {
        const blank = env.api.snapshot();
        assert.equal(env.api.tbody.rows.length, 73);
        assert.equal(env.api.tbody.textContent, '');
        const text = env.json();
        env.select({ name: 'mi-plantilla.json', size: new TextEncoder().encode(text).length, text: async () => text });
        await turn();
        assert.equal(env.api.getLayout().width, 2);
        assert.equal(env.api.tbody.rows.length, 2);
        assert.match(env.api.tbody.textContent, /CAJA DE PRUEBA/);
        assert.equal(env.api.tbody.rows[0].cells[0].colSpan, 2);
        assert.equal(env.api.tbody.rows[1].cells[0].style.backgroundColor, 'rgb(230, 230, 230)');
        assert.equal(env.api.editMode, true);
        assert.equal(env.api.dirty, true);
        assert.equal(env.document.activeElement.id, 'btn-save-template');
        env.api.undo();
        assert.equal(env.api.snapshot(), blank);
        env.api.redo();
        assert.match(env.api.tbody.textContent, /CAJA DE PRUEBA/);
    } finally { env.dom.window.close(); }
});

domTest('invalid or unsupported uploads leave the open design unchanged', async () => {
    const env = boot();
    try {
        const files = [
            { name: 'modelo.pdf', size: 12, text: async () => { throw new Error('Must not read PDFs'); } },
            { name: 'vacío.json', size: 0, text: async () => '' },
            { name: 'grande.json', size: MAX_TEMPLATE_FILE_BYTES + 1, text: async () => { throw new Error('Must not read oversized files'); } },
            { name: 'roto.json', size: 5, text: async () => '{roto' },
        ];
        for (const file of files) { env.select(file); await turn(); }
        assert.equal(env.statuses.filter(status => status.error).length, files.length);
        assert.deepEqual(env.loads, []);
        assert.equal(env.transfer.reading, false);
    } finally { env.dom.window.close(); }
});

domTest('the most recently selected file wins when reads finish in reverse order', async () => {
    const env = boot();
    let first;
    try {
        const oldText = env.json();
        const newText = createTemplateFile(sample.replace('CAJA DE PRUEBA', 'SEGUNDA PLANTILLA'), env.document, options);
        env.select({ name: 'primera.json', size: 1000, text: () => new Promise(resolve => { first = resolve; }) });
        env.select({ name: 'segunda.json', size: 1000, text: async () => newText });
        await turn();
        first(oldText); await turn();
        assert.equal(env.loads.length, 1);
        assert.match(env.loads[0], /SEGUNDA PLANTILLA/);
        assert.equal(env.transfer.reading, false);
    } finally { env.dom.window.close(); }
});

domTest('edits made while the file is being read are preserved', async () => {
    const env = boot();
    let finish;
    try {
        const text = env.json();
        env.select({ name: 'plantilla.json', size: 1000, text: () => new Promise(resolve => { finish = resolve; }) });
        env.setCurrent('NUEVO TRABAJO SIN GUARDAR');
        finish(text); await turn();
        assert.deepEqual(env.loads, []);
        assert.match(env.statuses.at(-1).message, /hoja ha cambiado/);
        assert.equal(env.statuses.at(-1).error, true);
    } finally { env.dom.window.close(); }
});
