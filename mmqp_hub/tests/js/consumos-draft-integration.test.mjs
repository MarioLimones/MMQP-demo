import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import vm from 'node:vm';
import { initializeArqueo, selectionRectangle, sanitizeTemplate, templateSnapshot } from '../../resources/js/arqueo-editor.js';
import { drawPrintBorders, clearPrintBorders } from '../../resources/js/arqueo-print-borders.js';
import * as state from '../../resources/js/consumos-state.js';
import * as grid from '../../resources/js/consumos-grid.js';
import * as search from '../../resources/js/consumos-search.js';
import * as side from '../../resources/js/consumos-side-grid.js';
import * as order from '../../resources/js/consumos-order-grid.js';
import * as custom from '../../resources/js/consumos-block-layout.js';
import * as recovery from '../../resources/js/consumos-draft.js';
import * as fit from '../../resources/js/consumos-text-fit.js';

// Optional DOM diagnostic runtime; production has no new dependency or live data access.
let JSDOM;
try {
    ({ JSDOM } = createRequire(import.meta.url)('jsdom'));
} catch {
    try {
        ({ JSDOM } = createRequire(join(process.env.MM_CONSUMOS_DOM_RUNTIME || join(tmpdir(), 'consumos-editor-diagnostic'), 'package.json'))('jsdom'));
    } catch { /* Tests report an explicit skip when the optional diagnostic runtime is unavailable. */ }
}
const domTest = (name, callback) => test(name, { skip: !JSDOM ? 'Optional jsdom diagnostic runtime is unavailable' : false }, callback);
const editorPath = new URL('../../resources/js/consumos-editor.js', import.meta.url);
const viewPath = new URL('../../resources/views/admin/consumos/index.blade.php', import.meta.url);
const namespace = 'qa-database:user-2';
const key = `mmqp:consumos:${namespace}:19`;
const supplierId = '10000000-0000-4000-8000-000000000001';
const productId = '10000000-0000-4000-8000-000000000002';
const html = (label = 'Producto inicial', source = []) => `<tr><td colspan="24" data-consumo-provider="${supplierId}"><div class="cell-content">Proveedor de prueba</div></td></tr><tr>${['product', 'consumo', 'stock', 'pedido'].map(field => `<td colspan="6" data-consumo-product="${productId}" data-consumo-supplier="${supplierId}" data-consumo-field="${field}"${field === 'consumo' ? ` data-consumo-sources="${JSON.stringify(source).replaceAll('"', '&quot;')}"` : ''}><div class="cell-content">${field === 'product' ? label : field === 'consumo' ? '999 inventado' : ''}</div></td>`).join('')}</tr><tr><td rowspan="71" colspan="24"><div class="cell-content"></div></td></tr>${'<tr></tr>'.repeat(70)}`;
const sheet = (changes = {}) => ({ id: 19, version: 3, title: 'Ficha del servidor', import_id: 7, suppliers: [], grid_html: html(), ...changes });
const report = { id: 7, file_name: 'informe.pdf', rows: [{ id: 'source-a', name: 'Fuente elegida', quantity: '2.000', unit: 'KG', category: 'Prueba', group: '', page: 1 }] };
const tick = () => new Promise(resolve => setImmediate(resolve));
async function settled(predicate) {
    for (let index = 0; index < 40; index++) { if (predicate()) return; await tick(); }
    assert.ok(predicate(), 'The asynchronous UI action should complete');
}

function boot({ serverSheet = sheet(), activeReport = report, draft, request } = {}) {
    const view = readFileSync(viewPath, 'utf8').replace(/<script type="application\/json" id="consumos-config">[\s\S]*?<\/script>/, '<script type="application/json" id="consumos-config"></script>');
    const dom = new JSDOM(`<!doctype html><meta name="csrf-token" content="isolated-test">${view}`, { url: 'http://localhost/isolated-consumos-dom-test', pretendToBeVisual: true, runScripts: 'outside-only' });
    const { window } = dom;
    Object.defineProperty(window.document, 'readyState', { value: 'complete' });
    window.confirm = () => true;
    const matches = window.Element.prototype.matches;
    window.Element.prototype.matches = function (selector) { return selector === ':popover-open' ? !!this._testPopoverOpen : matches.call(this, selector); };
    window.HTMLElement.prototype.showPopover = function () { this._testPopoverOpen = true; };
    window.HTMLElement.prototype.hidePopover = function () { this._testPopoverOpen = false; };
    window.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    window.HTMLDialogElement.prototype.close = function () { this.open = false; };
    const requests = [];
    window.fetch = async (url, options = {}) => {
        requests.push({ url, options });
        return request ? request(url, options) : { ok: true, json: async () => ({ import: activeReport }) };
    };
    window.document.getElementById('consumos-config').textContent = JSON.stringify({
        initialData: { sheet: serverSheet, activeImport: activeReport, imports: [activeReport], sheets: [serverSheet], draft_namespace: namespace },
        urls: { save: '/test-save', report: '/test-reports', sheet: '/test-sheets' },
    });
    if (draft) recovery.createConsumosDraftStore({ storage: window.localStorage, key }).write(draft);
    Object.assign(window, state, grid, search, side, order, custom, recovery, fit, {
        selectionRectangle, sanitizeTemplate, templateSnapshot, drawPrintBorders, clearPrintBorders,
        initializeArqueo: config => initializeArqueo(config, window.document),
    });
    // Execute the production controller with the real grid modules and real DOM events.
    vm.runInContext(readFileSync(editorPath, 'utf8').replace(/^import .*;\r?\n/gm, ''), dom.getInternalVMContext(), { filename: editorPath.pathname });
    const get = id => window.document.getElementById(`consumos-${id}`);
    return { dom, window, requests, get, api: window.arqueoSheet, stored: () => recovery.createConsumosDraftStore({ storage: window.localStorage, key }).read() };
}

domTest('a local recovery prompt leaves server HTML untouched until explicit restore', async () => {
    const env = boot({ draft: sheet({ title: 'Borrador recuperable', grid_html: html('Producto recuperado', ['source-a']) }) });
    try {
        assert.equal(env.get('draft-banner').hidden, false);
        assert.match(env.api.tbody.textContent, /Producto inicial/);
        assert.doesNotMatch(env.api.tbody.textContent, /Producto recuperado/);
        env.get('draft-restore').click();
        await settled(() => env.get('draft-banner').hidden);
        assert.equal(env.get('title').value, 'Borrador recuperable');
        assert.equal(env.api.dirty, true);
        assert.match(env.api.tbody.textContent, /Producto recuperado/);
        assert.equal(env.api.tbody.querySelector('[data-consumo-field="consumo"] .cell-content').textContent, '2 KG');
        assert.deepEqual(JSON.parse(env.api.tbody.querySelector('[data-consumo-field="consumo"]').dataset.consumoSources), ['source-a']);
        assert.equal(env.requests.length, 1);
        assert.equal(env.requests[0].url, '/test-reports/7');
        assert.ok(env.stored());
    } finally { env.dom.window.close(); }
});

domTest('explicit multipage recovery restores all sheets, the active sheet and links without accepting stored quantities', async () => {
    const firstPageId = '20000000-0000-4000-8000-000000000001';
    const secondPageId = '20000000-0000-4000-8000-000000000002';
    const otherSupplierId = '10000000-0000-4000-8000-000000000004';
    const otherProductId = '10000000-0000-4000-8000-000000000005';
    const otherHtml = (name, sources = []) => html(name, sources).replaceAll(supplierId, otherSupplierId).replaceAll(productId, otherProductId);
    const serverPages = [
        { id: firstPageId, title: 'Portada guardada', grid_html: html('Primer producto guardado') },
        { id: secondPageId, title: 'Proveedores guardados', grid_html: otherHtml('Segundo producto guardado') },
    ];
    const draftPages = [
        { id: firstPageId, title: 'Portada recuperada', grid_html: html('Primer producto recuperado') },
        { id: secondPageId, title: 'Proveedores recuperados', grid_html: otherHtml('Segundo producto recuperado', ['source-a']) },
    ];
    const env = boot({
        serverSheet: sheet({ pages: serverPages, grid_html: serverPages[0].grid_html }),
        draft: sheet({ title: 'Documento completo recuperado', pages: draftPages, grid_html: draftPages[0].grid_html, active_page_id: secondPageId }),
    });
    try {
        assert.equal(env.get('draft-banner').hidden, false);
        assert.match(env.api.tbody.textContent, /Primer producto guardado/);
        assert.doesNotMatch(env.api.tbody.textContent, /recuperado/);
        env.get('draft-restore').click();
        await settled(() => env.get('draft-banner').hidden);
        assert.equal(env.get('title').value, 'Documento completo recuperado');
        assert.equal(env.get('page-select').options.length, 2);
        assert.equal(env.get('page-select').value, secondPageId);
        assert.match(env.api.tbody.textContent, /Segundo producto recuperado/);
        assert.equal(env.api.tbody.querySelector('[data-consumo-field="consumo"] .cell-content').textContent, '2 KG');
        assert.deepEqual(grid.readSuppliers(env.api.tbody)[0].products[0].source_ids, ['source-a']);
        env.get('page-select').value = firstPageId;
        env.get('page-select').dispatchEvent(new env.window.Event('change', { bubbles: true }));
        assert.match(env.api.tbody.textContent, /Primer producto recuperado/);
        assert.equal(env.api.tbody.querySelector('[data-consumo-field="consumo"] .cell-content').textContent, '');
        assert.equal(env.api.dirty, true);
        env.window.dispatchEvent(new env.window.Event('pagehide'));
        const savedDraft = env.stored().snapshot;
        assert.equal(savedDraft.pages.length, 2);
        assert.equal(savedDraft.active_page_id, firstPageId);
        assert.deepEqual(savedDraft.pages.map(page => page.title), ['Portada recuperada', 'Proveedores recuperados']);
        assert.equal(env.requests.filter(item => item.url === '/test-reports/7').length, 1);
    } finally { env.dom.window.close(); }
});

domTest('a draft from an older server version remains available but cannot restore over it', () => {
    const env = boot({ serverSheet: sheet({ version: 4 }), draft: sheet({ grid_html: html('Older local copy') }) });
    try {
        assert.equal(env.get('draft-restore').disabled, true);
        env.get('draft-restore').click();
        assert.match(env.api.tbody.textContent, /Producto inicial/);
        assert.equal(env.stored().base_version, 3);
        assert.equal(env.requests.length, 0);
    } finally { env.dom.window.close(); }
});

domTest('real title input produces a scoped recoverable snapshot before page exit', () => {
    const env = boot();
    try {
        env.get('title').value = 'Edición antes de salir';
        env.get('title').dispatchEvent(new env.window.Event('input', { bubbles: true }));
        env.window.dispatchEvent(new env.window.Event('pagehide'));
        assert.equal(env.stored().snapshot.title, 'Edición antes de salir');
        assert.equal(env.stored().base_sheet_id, 19);
        assert.equal(env.stored().base_version, 3);
    } finally { env.dom.window.close(); }
});

domTest('a failed network save retains recovery and unlocks the editor for retry', async () => {
    const env = boot({ request: async () => { throw new TypeError('Network failed'); } });
    try {
        env.get('title').value = 'Conservar tras error';
        env.get('title').dispatchEvent(new env.window.Event('input', { bubbles: true }));
        await env.api.saveTemplate();
        assert.equal(env.stored().snapshot.title, 'Conservar tras error');
        assert.equal(env.api.table.inert, false);
        assert.equal(env.window.document.querySelector('.consumos-file-bar').inert, false);
        assert.match(env.window.document.getElementById('editor-status').textContent, /conectar/);
    } finally { env.dom.window.close(); }
});

domTest('only a confirmed incremented server version clears local recovery after real save', async () => {
    const env = boot({ request: async (url, options) => ({ ok: true, json: async () => ({ sheet: sheet({ ...JSON.parse(options.body), version: 4 }) }) }) });
    try {
        env.get('title').value = 'Guardado confirmado';
        env.get('title').dispatchEvent(new env.window.Event('input', { bubbles: true }));
        await env.api.saveTemplate();
        assert.equal(env.stored(), null);
        assert.equal(env.api.dirty, false);
        assert.equal(env.api.table.inert, false);
        assert.equal(env.requests.length, 1);
        const payload = JSON.parse(env.requests[0].options.body);
        assert.equal(payload.version, 3);
        assert.equal(payload.title, 'Guardado confirmado');
    } finally { env.dom.window.close(); }
});

domTest('a recovery decision locks every editing surface until explicit discard', () => {
    const env = boot({ draft: sheet({ title: 'Pending recovery' }) });
    try {
        const selectors = ['#edit-toolbar', '.consumos-paper-actions', '.arqueo-sheet-scroll', '.consumos-review', '.consumos-entry-options', '.consumos-file-bar', '.consumos-popup-host', '#context-menu', '#arqueo-grid'];
        for (const selector of selectors) assert.equal(env.window.document.querySelector(selector).inert, true, selector);
        assert.notEqual(env.get('draft-banner').inert, true);
        for (const id of ['draft-restore', 'draft-discard']) {
            for (let node = env.get(id); node; node = node.parentElement) {
                assert.notEqual(node.inert, true, `${id} must remain interactive through all ancestors`);
            }
        }
        env.get('draft-discard').click();
        assert.equal(env.get('draft-banner').hidden, true);
        assert.equal(env.stored(), null);
        for (const selector of selectors) assert.equal(env.window.document.querySelector(selector).inert, false, selector);
    } finally { env.dom.window.close(); }
});

domTest('discarding a cached revision detects another tab and preserves its new copy', () => {
    const env = boot({ draft: sheet({ title: 'Old displayed revision' }) });
    try {
        const anotherTab = recovery.createConsumosDraftStore({ storage: env.window.localStorage, key });
        const newer = anotherTab.write(sheet({ title: 'Newer copy from another tab' }));
        assert.equal(newer.ok, true);
        env.get('draft-discard').click();
        assert.equal(env.get('draft-banner').hidden, false);
        assert.equal(env.api.table.inert, true);
        assert.equal(env.stored().revision, newer.draft.revision);
        assert.match(env.window.document.getElementById('editor-status').textContent, /otra pestaña/);
        // The updated prompt now refers to the current copy, which can be explicitly discarded.
        env.get('draft-discard').click();
        assert.equal(env.stored(), null);
        assert.equal(env.api.table.inert, false);
    } finally { env.dom.window.close(); }
});

domTest('restoring a cached revision checks the latest local copy before fetching its report', async () => {
    const env = boot({ draft: sheet({ title: 'Old displayed revision', grid_html: html('Old local product') }) });
    try {
        const anotherTab = recovery.createConsumosDraftStore({ storage: env.window.localStorage, key });
        const newer = anotherTab.write(sheet({ title: 'Newer local revision', grid_html: html('Newest product', ['source-a']) }));
        env.get('draft-restore').click();
        await settled(() => env.window.document.getElementById('editor-status').textContent.includes('otra pestaña'));
        assert.equal(env.requests.length, 0);
        assert.match(env.api.tbody.textContent, /Producto inicial/);
        assert.equal(env.get('draft-banner').hidden, false);
        assert.equal(env.api.table.inert, true);
        assert.equal(env.stored().revision, newer.draft.revision);
        env.get('draft-restore').click();
        await settled(() => env.get('draft-banner').hidden);
        await tick();
        assert.equal(env.get('title').value, 'Newer local revision');
        assert.match(env.api.tbody.textContent, /Newest product/);
        assert.equal(env.api.table.inert, false);
        assert.equal(env.requests.length, 1);
    } finally { env.dom.window.close(); }
});

domTest('a draft changed while its report loads is preserved without restoring the stale HTML', async () => {
    let release;
    const env = boot({ draft: sheet({ grid_html: html('Stale copy') }), request: () => new Promise(resolve => {
        release = () => resolve({ ok: true, json: async () => ({ import: report }) });
    }) });
    try {
        env.get('draft-restore').click();
        assert.equal(env.requests.length, 1);
        const anotherTab = recovery.createConsumosDraftStore({ storage: env.window.localStorage, key });
        const latest = anotherTab.write(sheet({ grid_html: html('Newer copy during fetch') }));
        release();
        await settled(() => env.window.document.getElementById('editor-status').textContent.includes('mientras se cargaba'));
        assert.match(env.api.tbody.textContent, /Producto inicial/);
        assert.equal(env.get('draft-banner').hidden, false);
        assert.equal(env.api.table.inert, true);
        assert.equal(env.stored().revision, latest.draft.revision);
    } finally { env.dom.window.close(); }
});

domTest('saving locks toolbar, popups and context actions while the server response is pending', async () => {
    let release;
    const env = boot({ request: (url, options) => new Promise(resolve => {
        release = () => resolve({ ok: true, json: async () => ({ sheet: sheet({ ...JSON.parse(options.body), version: 4 }) }) });
    }) });
    try {
        env.get('title').value = 'Pending server save';
        env.get('title').dispatchEvent(new env.window.Event('input', { bubbles: true }));
        const saving = env.api.saveTemplate();
        const selectors = ['#edit-toolbar', '.consumos-paper-actions', '.arqueo-sheet-scroll', '.consumos-review', '.consumos-entry-options', '.consumos-file-bar', '.consumos-popup-host', '#context-menu', '#arqueo-grid'];
        for (const selector of selectors) assert.equal(env.window.document.querySelector(selector).inert, true, selector);
        assert.equal(env.stored().snapshot.title, 'Pending server save');
        release();
        await saving;
        for (const selector of selectors) assert.equal(env.window.document.querySelector(selector).inert, false, selector);
        assert.equal(env.stored(), null);
        assert.equal(env.api.dirty, false);
    } finally { env.dom.window.close(); }
});

domTest('empty contenteditable input after staged linking and saves does not recurse or lose its draft', async t => {
    const templateDom = new JSDOM('<!doctype html>');
    const suppliers = [
        {
            id: '10000000-0000-4000-8000-000000000001',
            name: 'PROVEEDOR TEST',
            products: Array.from({ length: 12 }, (_, idx) => ({
                id: `10000000-0000-4000-8000-${String(idx + 10).padStart(12, '0')}`,
                name: `Producto ${idx + 1}`,
                stock: '',
                pedido: '',
                source_ids: [],
            })),
        },
    ];
    const sampleBody = templateDom.window.document.createElement('tbody');
    sampleBody.innerHTML = grid.legacyGrid(suppliers, templateDom.window.document);
    sampleBody.rows[44].cells[0].querySelector('.cell-content').textContent = 'Nota de prueba que se va a borrar';
    const template = sampleBody.innerHTML;
    const productNames = suppliers[0].products.map(p => p.name);
    templateDom.window.close();
    const diagnosticReport = { ...report, rows: Array.from({ length: 191 }, (_, index) => ({
        id: `diag-source-${index}`, name: productNames[index] || `Extra source ${index}`,
        quantity: String(index + 1), unit: 'Unidad', category: 'Diagnostic', group: '', page: 1,
    })) };
    const env = boot({
        serverSheet: sheet({ grid_html: template }), activeReport: diagnosticReport,
        request: async (url, options) => ({ ok: true, json: async () => ({ sheet: sheet({ ...JSON.parse(options.body), version: JSON.parse(options.body).version + 1 }) }) }),
    });
    try {
        const changes = [];
        env.api.table.addEventListener('arqueo:changed', event => changes.push(event.detail.dirty));
        for (let index = 0; index < 9; index++) {
            const product = env.api.tbody.querySelectorAll('[data-consumo-field="product"]')[index];
            env.api.selectCell(product, false, true);
            env.window.consumosOpenProduct();
            env.get('bind-search').value = '';
            env.get('bind-search').dispatchEvent(new env.window.Event('input', { bubbles: true }));
            const choice = env.get('bind-list').querySelector(`input[value="diag-source-${index}"]`);
            assert.ok(choice, `A report source should be available for product ${index}`);
            choice.checked = true;
            choice.dispatchEvent(new env.window.Event('change', { bubbles: true }));
            env.get('bind-form').dispatchEvent(new env.window.Event('submit', { bubbles: true, cancelable: true }));
            assert.equal(env.get('bind-error').hidden, true);
            if (index % 3 === 2) {
                await env.api.saveTemplate();
                assert.equal(env.api.dirty, false);
                assert.equal(env.stored(), null);
            }
        }
        env.window.toggleEditMode();
        // Logical A45 is selected through the real grid map, independently of merges.
        const target = env.api.getLayout().map[44][0];
        const content = target.querySelector('.cell-content');
        const originalText = content.textContent;
        assert.notEqual(originalText, '', 'Clearing text must actually change the saved fixture');
        const beforeEmpty = changes.length;
        const start = performance.now();
        env.api.selectCell(target, false, true);
        content.focus();
        content.dispatchEvent(new env.window.InputEvent('beforeinput', { bubbles: true, cancelable: true, inputType: 'insertText', data: '' }));
        content.textContent = '';
        content.dispatchEvent(new env.window.InputEvent('input', { bubbles: true, inputType: 'insertText', data: '' }));
        env.get('title').focus(); // Real focusout flushes the pending cell text.
        env.window.dispatchEvent(new env.window.Event('pagehide'));
        const elapsed = performance.now() - start;
        assert.equal(content.textContent, '');
        assert.equal(env.api.dirty, true);
        assert.ok(env.stored(), 'The cleared cell should be retained in local recovery');
        assert.ok(changes.length - beforeEmpty < 10, 'input, focusout and recovery must not recursively notify changes');
        const rehydrated = grid.readSuppliers(env.api.tbody);
        assert.equal(rehydrated.flatMap(provider => provider.products).filter(product => product.source_ids.length).length, 9);
        t.diagnostic(`A45 originally ${JSON.stringify(originalText)}; empty input/focusout/pagehide ${elapsed.toFixed(1)} ms; ${changes.length - beforeEmpty} changed events; ${env.api.tbody.querySelectorAll('td').length} cells.`);
        for (const inputType of ['deleteContentBackward', 'deleteContentForward']) {
            const eventCount = changes.length;
            content.focus();
            content.dispatchEvent(new env.window.InputEvent('beforeinput', { bubbles: true, cancelable: true, inputType, data: null }));
            // Rich editors can retain an empty BR after native keyboard deletion.
            content.innerHTML = '<br>';
            content.dispatchEvent(new env.window.InputEvent('input', { bubbles: true, inputType, data: null }));
            env.get('title').focus();
            env.window.dispatchEvent(new env.window.Event('pagehide'));
            assert.ok(changes.length - eventCount < 10, `${inputType} must not recursively notify changes`);
            assert.ok(env.stored());
        }
        await new Promise(resolve => env.window.setTimeout(resolve, 650));
        assert.ok(changes.length - beforeEmpty < 20, 'the delayed draft write must remain bounded');
        assert.ok(env.stored());
    } finally { env.dom.window.close(); }
});
