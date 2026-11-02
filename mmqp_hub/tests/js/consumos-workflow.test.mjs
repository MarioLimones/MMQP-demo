import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { join } from 'node:path';
import vm from 'node:vm';
import { initializeArqueo, selectionRectangle, sanitizeTemplate, templateSnapshot, buildGridMap } from '../../resources/js/arqueo-editor.js';
import { drawPrintBorders, clearPrintBorders } from '../../resources/js/arqueo-print-borders.js';
import * as state from '../../resources/js/consumos-state.js';
import * as grid from '../../resources/js/consumos-grid.js';
import * as search from '../../resources/js/consumos-search.js';
import * as side from '../../resources/js/consumos-side-grid.js';
import * as order from '../../resources/js/consumos-order-grid.js';
import * as custom from '../../resources/js/consumos-block-layout.js';
import * as recovery from '../../resources/js/consumos-draft.js';
import * as fit from '../../resources/js/consumos-text-fit.js';
import * as templateFile from '../../resources/js/consumos-template-file.js';
import { downloadTemplate } from '../../resources/js/arqueo-template-transfer.js';

let JSDOM;
try { ({ JSDOM } = createRequire(import.meta.url)('jsdom')); }
catch {
    try { ({ JSDOM } = createRequire(process.env.MM_CONSUMOS_DOM_RUNTIME ? join(process.env.MM_CONSUMOS_DOM_RUNTIME, 'package.json') : new URL('../../output/test-dependencies/package.json', import.meta.url))('jsdom')); }
    catch { /* The optional DOM runtime is reported as an explicit skip. */ }
}
const domTest = (name, callback) => test(name, { skip: !JSDOM ? 'Optional jsdom diagnostic runtime is unavailable' : false }, callback);
const supplierId = '10000000-0000-4000-8000-000000000001';
const productId = '10000000-0000-4000-8000-000000000002';
const secondId = '10000000-0000-4000-8000-000000000003';
const editorPath = new URL('../../resources/js/consumos-editor.js', import.meta.url);
const viewPath = new URL('../../resources/views/admin/consumos/index.blade.php', import.meta.url);
const html = (names = ['Producto de mi documento'], sources = []) => `<tr><td colspan="24" data-consumo-provider="${supplierId}"><div class="cell-content">Proveedor de prueba</div></td></tr>${names.map((name, index) => `<tr>${grid.PRODUCT_FIELDS.map(field => `<td colspan="6" data-consumo-product="${index ? secondId : productId}" data-consumo-supplier="${supplierId}" data-consumo-field="${field}"${field === 'consumo' ? ` data-consumo-sources="${JSON.stringify(sources[index] || []).replaceAll('"', '&quot;')}"` : ''}><div class="cell-content">${field === 'product' ? name : ''}</div></td>`).join('')}</tr>`).join('')}<tr><td rowspan="${72 - names.length}" colspan="24"><div class="cell-content"></div></td></tr>${'<tr></tr>'.repeat(71 - names.length)}`;
const reusable = (changes = {}) => ({ id: 4, version: 3, title: 'Documento de prueba', suppliers: [], grid_html: html(), ...changes });
const report = { id: 7, file_name: 'informe.pdf', rows: [
    { id: 'source-a', name: 'Producto del PDF', quantity: '2.000', unit: 'KG', category: 'Prueba', group: 'Grupo A', page: 1 },
    { id: 'source-b', name: 'Producto del PDF', quantity: '8.000', unit: 'KG', category: 'Prueba', group: 'Grupo B', page: 1 },
] };
const pageA = '20000000-0000-4000-8000-000000000001';
const pageB = '20000000-0000-4000-8000-000000000002';
const otherSupplierId = '10000000-0000-4000-8000-000000000004';
const otherProductId = '10000000-0000-4000-8000-000000000005';
function twoPages({ linked = false } = {}) {
    return [
        { id: pageA, title: 'Portada', grid_html: html(['Primer producto'], [linked ? ['source-a'] : []]) },
        { id: pageB, title: 'Proveedores', grid_html: html(['Segundo producto'], [linked ? ['source-b'] : []]).replaceAll(supplierId, otherSupplierId).replaceAll(productId, otherProductId) },
    ];
}
function selectPage(env, id) {
    env.get('page-select').value = id;
    env.get('page-select').dispatchEvent(new env.window.Event('change', { bubbles: true }));
}
function editCell(env, selector, value) {
    const content = env.api.tbody.querySelector(`${selector} .cell-content`);
    content.focus();
    content.textContent = value;
    content.dispatchEvent(new env.window.InputEvent('input', { bubbles: true, inputType: 'insertText', data: value }));
    env.get('title').focus();
}
const tick = () => new Promise(resolve => setImmediate(resolve));

for (const calendarAvailable of [true, false]) {
    domTest(`saving a ficha ${calendarAvailable ? 'refreshes its calendar' : 'remains successful when the calendar cannot refresh'}`, async () => {
        const saved = { id: 19, version: 1, title: 'Ficha registrada', import_id: null, suppliers: [], grid_html: html() };
        const env = boot({ savedSheets: [saved] });
        try {
            const calendar = env.window.document.createElement('section');
            calendar.id = 'consumos-history';
            calendar.dataset.historyUrl = '/history';
            env.get('app').append(calendar);
            const originalFetch = env.window.fetch;
            env.window.fetch = async (url, options) => {
                if (url !== '/history') return originalFetch(url, options);
                if (!calendarAvailable) throw new Error('Network unavailable');
                return { ok: true, redirected: false, text: async () => '<section id="consumos-history" data-history-url="/history"><p>Ficha registrada · Versión 2</p></section>' };
            };
            env.get('sheet-select').value = '19';
            env.get('sheet-select').dispatchEvent(new env.window.Event('change', { bubbles: true }));
            await settled(() => env.get('app').dataset.consumosMode === 'sheet');
            await tick();
            editCell(env, '[data-consumo-field="stock"]', '12 cajas');
            await env.api.saveTemplate();
            assert.equal(env.api.dirty, false, 'The ficha stays saved regardless of the calendar connection');
            assert.equal(env.get('draft-banner').hidden, true);
            assert.equal(env.window.localStorage.getItem('mmqp:consumos:workflow-test:user-2:19'), null);
            if (calendarAvailable) {
                assert.match(env.get('history').textContent, /Versión 2/);
                assert.equal(calendar.isConnected, false);
            } else {
                assert.match(env.get('history').querySelector('[role="status"]').textContent, /consumo está guardado/);
                assert.equal(calendar.isConnected, true);
            }
        } finally { env.dom.window.close(); }
    });
}

async function settled(predicate) {
    for (let index = 0; index < 60; index++) { if (predicate()) return; await tick(); }
    assert.ok(predicate(), 'The asynchronous workflow action should complete');
}
function boot({ documentData = reusable(), pending = [], preparedHtml = documentData?.grid_html, preparedPages = documentData?.pages, imports = [], savedSheets = [], request, storageData = {}, draftNamespace = 'workflow-test:user-2' } = {}) {
    const view = readFileSync(viewPath, 'utf8')
        .replace(/@if\(\$historyActive\) hidden @endif/g, '')
        .replace(/@unless\(\$historyActive\) hidden @endunless/g, 'hidden')
        .replace(/<script type="application\/json" id="consumos-config">[\s\S]*?<\/script>/, '<script type="application/json" id="consumos-config"></script>');
    const dom = new JSDOM(`<!doctype html><meta name="csrf-token" content="isolated-test">${view}`, { url: 'http://localhost/isolated-consumos-workflow', pretendToBeVisual: true, runScripts: 'outside-only' });
    const { window } = dom;
    Object.defineProperty(window.document, 'readyState', { value: 'complete' });
    window.confirm = () => true;
    const matches = window.Element.prototype.matches;
    window.Element.prototype.matches = function (selector) { return selector === ':popover-open' ? !!this._testPopoverOpen : matches.call(this, selector); };
    window.HTMLElement.prototype.showPopover = function () { this._testPopoverOpen = true; };
    window.HTMLElement.prototype.hidePopover = function () { this._testPopoverOpen = false; };
    window.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
    window.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new window.Event('close')); };
    const requests = [];
    window.fetch = async (url, options = {}) => {
        requests.push({ url, options });
        if (request) return request(url, options);
        let result;
        if (url === '/document') result = { document: documentData };
        else if (url === '/import' || url.startsWith('/reports/')) result = { import: report };
        else if (url.startsWith('/sheets/')) result = { sheet: savedSheets.find(item => String(item.id) === url.split('/').at(-1)) };
        else if (url.endsWith('/prepare')) result = { document: documentData, import: report, pending, sheet: { id: null, version: null, title: documentData.title, import_id: report.id, suppliers: [], grid_html: preparedHtml, ...(preparedPages ? { pages: preparedPages } : {}) } };
        else if (url.endsWith('/complete')) result = { document: { ...documentData, version: documentData.version + 1 }, sheet: { id: 19, version: 1, title: documentData.title, import_id: report.id, suppliers: [], grid_html: window.arqueoSheet.getHtml() } };
        else if (url === '/sheet/save') {
            const payload = JSON.parse(options.body);
            result = { sheet: { ...payload, id: payload.id || 19, version: (payload.version || 0) + 1 } };
        } else {
            documentData = { ...JSON.parse(options.body), id: documentData?.id || 4, version: (documentData?.version || 0) + 1 };
            result = { document: documentData };
        }
        return { ok: true, json: async () => result };
    };
    window.document.getElementById('consumos-config').textContent = JSON.stringify({ initialData: { document: documentData, imports, sheets: savedSheets, draft_namespace: draftNamespace }, urls: { documentShow: '/document', documentSave: '/document/save', import: '/import', prepare: '/imports/__IMPORT__/prepare', complete: '/imports/__IMPORT__/complete', save: '/sheet/save', report: '/reports', sheet: '/sheets' } });
    for (const [key, value] of Object.entries(storageData)) window.localStorage.setItem(key, value);
    Object.assign(window, state, grid, search, side, order, custom, recovery, fit, templateFile, { downloadTemplate, selectionRectangle, sanitizeTemplate, templateSnapshot, drawPrintBorders, clearPrintBorders, initializeArqueo: config => initializeArqueo(config, window.document) });
    vm.runInContext(readFileSync(editorPath, 'utf8').replace(/^import .*;\r?\n/gm, ''), dom.getInternalVMContext(), { filename: editorPath.pathname });
    const get = id => window.document.getElementById(`consumos-${id}`);
    const importPdf = async () => {
        get('import-button').click();
        Object.defineProperty(get('pdf'), 'files', { configurable: true, value: [new window.File(['isolated fixture'], 'informe.pdf', { type: 'application/pdf' })] });
        get('import-form').dispatchEvent(new window.Event('submit', { bubbles: true, cancelable: true }));
        await settled(() => get('app').dataset.consumosMode !== 'entry');
        await tick();
    };
    return { dom, window, requests, get, importPdf, api: window.arqueoSheet };
}

domTest('single-sheet import previews, cancels, replaces only its destination and preserves unsaved changes on other sheets', async () => {
    const env = boot({ documentData: reusable({ pages: twoPages() }) });
    try {
        env.window.document.getElementById('btn-edit-mode').click();
        await settled(() => env.get('app').dataset.consumosMode === 'document');
        await tick();
        editCell(env, '[data-consumo-field="product"]', 'Primer producto sin guardar');
        selectPage(env, pageB);
        const previous = env.api.getHtml();
        const incoming = templateFile.createConsumoTemplateFile({ title: 'Hoja trasladada', pages: [{ ...twoPages()[0], title: 'Importada', grid_html: html(['Producto importado']) }] }, env.window.document);
        const choose = async text => {
            Object.defineProperty(env.get('template-file'), 'files', { configurable: true, value: [{ name: 'plantilla.json', size: text.length, text: async () => text }] });
            env.get('template-file').dispatchEvent(new env.window.Event('change', { bubbles: true }));
            await settled(() => env.get('template-dialog').open || env.window.document.getElementById('editor-status').dataset.state === 'error');
            await tick();
        };
        await choose(incoming);
        assert.match(env.get('template-summary').textContent, /Importada.*1 proveedores.*1 productos.*Proveedores/);
        assert.equal(env.api.getHtml(), previous);
        env.get('template-cancel').click();
        assert.equal(env.api.getHtml(), previous);
        assert.equal(env.get('template-dialog').open, false);
        await choose(incoming);
        env.get('template-apply').click();
        assert.equal(env.get('page-select').options.length, 2);
        assert.equal(env.get('page-select').options[0].textContent, '1. Portada');
        assert.equal(env.get('page-select').options[1].textContent, '2. Importada');
        assert.equal(env.get('page-select').value, pageB);
        assert.equal(env.get('title').value, 'Documento de prueba');
        assert.notEqual(grid.readSuppliers(env.api.tbody)[0].id, otherSupplierId);
        assert.equal(env.requests.filter(item => item.url === '/document/save').length, 0, 'Import is reviewed locally before saving');
        assert.equal(env.get('template-undo').hidden, false);
        env.get('template-undo').click();
        assert.equal(env.api.getHtml(), previous);
        assert.equal(env.get('page-select').options[0].textContent, '1. Portada');
        assert.equal(env.get('page-select').options[1].textContent, '2. Proveedores');
        assert.equal(env.get('title').value, 'Documento de prueba');
        await choose('{"format":"other"}');
        assert.equal(env.api.getHtml(), previous);
        assert.equal(env.get('template-dialog').open, false);
        await choose(incoming);
        env.get('template-apply').click();
        await env.api.saveTemplate();
        const saved = JSON.parse(env.requests.find(item => item.url === '/document/save').options.body);
        assert.equal(saved.pages.length, 2);
        assert.match(saved.pages[0].grid_html, /Primer producto/);
        assert.doesNotMatch(saved.pages[0].grid_html, /sin guardar/);
        assert.match(saved.pages[1].grid_html, /Producto importado/);
        assert(saved.suppliers.every(supplier => supplier.products.every(product => product.source_ids.length === 0 && product.stock === '' && product.pedido === '')));
        assert.equal(env.get('template-undo').hidden, true);
        const draft = JSON.parse(env.window.localStorage.getItem('mmqp:consumos:workflow-test:user-2:document:4'));
        assert.match(draft.snapshot.pages[0].grid_html, /Primer producto sin guardar/);
        selectPage(env, pageA);
        assert.match(env.api.tbody.textContent, /Primer producto sin guardar/);
        await env.api.saveTemplate();
        const next = JSON.parse(env.requests.filter(item => item.url === '/document/save').at(-1).options.body);
        assert.match(next.pages[0].grid_html, /Primer producto sin guardar/);
        assert.match(next.pages[1].grid_html, /Producto importado/);
        assert.equal(env.window.localStorage.getItem('mmqp:consumos:workflow-test:user-2:document:4'), null);
    } finally { env.dom.window.close(); }
});

domTest('undoing a sheet import back to its saved state removes its recovery draft', async () => {
    const env = boot({ documentData: reusable({ pages: twoPages() }) });
    try {
        env.window.document.getElementById('btn-edit-mode').click();
        await settled(() => env.get('app').dataset.consumosMode === 'document'); await tick();
        const incoming = templateFile.createConsumoTemplateFile({ title: 'Importada', pages: [twoPages()[1]] }, env.window.document);
        Object.defineProperty(env.get('template-file'), 'files', { configurable: true, value: [{ name: 'hoja.json', size: incoming.length, text: async () => incoming }] });
        env.get('template-file').dispatchEvent(new env.window.Event('change', { bubbles: true }));
        await settled(() => env.get('template-dialog').open); await tick();
        env.get('template-apply').click();
        assert.notEqual(env.window.localStorage.getItem('mmqp:consumos:workflow-test:user-2:document:4'), null);
        env.get('template-undo').click();
        assert.equal(env.window.localStorage.getItem('mmqp:consumos:workflow-test:user-2:document:4'), null);
        assert.equal(env.api.dirty, false);
        assert.equal(env.get('page-select').options.length, 2);
    } finally { env.dom.window.close(); }
});

domTest('entry exposes exactly edit and import, and a fresh document can be created without linking', async () => {
    const env = boot({ documentData: null });
    try {
        assert.equal(env.get('app').dataset.consumosMode, 'entry');
        assert.equal(env.get('section-nav').hidden, false);
        assert.equal(env.get('workflow-workspace').hidden, true);
        assert.equal(env.get('workflow-linking').hidden, true);
        const actions = [...env.get('workflow-entry').querySelectorAll('.consumos-entry-options button')].filter(button => !button.hidden);
        assert.equal(actions.length, 2);
        env.window.document.getElementById('btn-edit-mode').click();
        await settled(() => env.get('app').dataset.consumosMode === 'document');
        await tick();
        assert.equal(env.api.editMode, true);
        assert.equal(env.get('section-nav').hidden, true);
        assert.equal(env.api.tbody.querySelector('.consumos-cell-dropdown'), null);
        env.get('title').value = 'Mi documento nuevo';
        env.get('title').dispatchEvent(new env.window.Event('input', { bubbles: true }));
        await env.api.saveTemplate();
        assert.equal(env.api.dirty, false);
        const save = env.requests.find(item => item.url === '/document/save');
        assert.ok(save);
        assert.equal(JSON.parse(save.options.body).import_id, undefined);
        assert.deepEqual(JSON.parse(save.options.body).suppliers, []);
        env.get('workflow-back').click();
        await settled(() => env.get('app').dataset.consumosMode === 'entry');
        assert.equal(env.get('section-nav').hidden, false);
    } finally { env.dom.window.close(); }
});

domTest('back returns from an incomplete document without saving and offers its draft on reentry', async () => {
    const env = boot();
    try {
        env.window.document.getElementById('btn-edit-mode').click();
        await settled(() => env.get('app').dataset.consumosMode === 'document');
        await tick();
        editCell(env, '[data-consumo-provider]', '');
        env.get('workflow-back').click();
        assert.equal(env.get('app').dataset.consumosMode, 'entry');
        assert.equal(env.window.document.activeElement.id, 'btn-edit-mode');
        assert.equal(env.requests.some(item => item.url === '/document/save'), false);
        const key = 'mmqp:consumos:workflow-test:user-2:document:4';
        const draft = env.window.localStorage.getItem(key);
        assert.ok(draft);
        env.window.document.getElementById('btn-edit-mode').click();
        await settled(() => env.get('app').dataset.consumosMode === 'document');
        await tick();
        assert.equal(env.get('draft-banner').hidden, false);
        assert.equal(env.get('draft-banner').parentElement, env.get('workspace-draft-host'));
        assert.equal(env.get('draft-status').parentElement, env.get('workspace-draft-host'));
        assert.equal(env.get('draft-banner').closest('[hidden]'), null, 'Document recovery must be reachable inside the visible workspace');
        assert.equal(env.get('workflow-workspace').contains(env.get('draft-banner')), true);
        assert.equal(env.window.document.querySelectorAll('#consumos-draft-banner').length, 1);
        assert.equal(env.window.document.querySelectorAll('#consumos-draft-status').length, 1);
        assert.equal(env.get('page-select').closest('.consumos-page-bar').inert, true);
        assert.equal(env.api.table.inert, true, 'Editing stays locked while the recovery decision is pending');
        env.get('workflow-back').click();
        assert.equal(env.get('app').dataset.consumosMode, 'entry', 'A pending draft must also allow returning');
        assert.equal(env.window.document.querySelector('.consumos-entry-options').inert, false);
        assert.deepEqual(JSON.parse(env.window.localStorage.getItem(key)).snapshot, JSON.parse(draft).snapshot);
    } finally { env.dom.window.close(); }
});

domTest('deleting a page requires confirmation, preserves other pages and saves only remaining products', async () => {
    const pages = twoPages();
    const env = boot({ documentData: reusable({ pages, grid_html: pages[0].grid_html }) });
    try {
        assert.equal(env.get('page-delete').hidden, true);
        env.window.document.getElementById('btn-edit-mode').click();
        await settled(() => env.get('app').dataset.consumosMode === 'document');
        await tick();
        editCell(env, '[data-consumo-field="product"]', 'Producto que se conserva');
        selectPage(env, pageB);
        let question;
        env.window.confirm = message => { question = message; return false; };
        env.get('page-delete').click();
        assert.match(question, /Proveedores/);
        assert.equal(env.get('page-select').options.length, 2);
        assert.equal(env.get('page-select').value, pageB);
        assert.equal(env.requests.some(item => item.url === '/document/save'), false);
        env.window.confirm = () => true;
        env.get('page-delete').click();
        assert.equal(env.get('page-select').value, pageA);
        assert.equal(env.get('page-select').options.length, 1);
        assert.match(env.api.tbody.textContent, /Producto que se conserva/);
        assert.equal(env.get('page-delete').disabled, true);
        assert.equal(env.window.document.activeElement, env.get('page-select'));
        const draft = JSON.parse(env.window.localStorage.getItem('mmqp:consumos:workflow-test:user-2:document:4'));
        assert.deepEqual(draft.snapshot.pages.map(page => page.id), [pageA]);
        await env.api.saveTemplate();
        const payload = JSON.parse(env.requests.find(item => item.url === '/document/save').options.body);
        assert.deepEqual(payload.pages.map(page => page.id), [pageA]);
        assert.deepEqual(payload.suppliers.flatMap(supplier => supplier.products.map(product => product.name)), ['Producto que se conserva']);
        assert.equal(payload.grid_html, payload.pages[0].grid_html);
    } finally { env.dom.window.close(); }
});

domTest('supplier buttons append products in both top and side supplier layouts, preserve existing rows and undo cleanly', async () => {
    const buildTopLayout = doc => grid.legacyGrid([
        { id: supplierId, name: 'PROV ARRIBA', products: [{ id: productId, name: 'Prod 1', stock: '', pedido: '', source_ids: [] }] },
    ], doc);
    const buildSideLayout = doc => {
        const body = doc.createElement('tbody');
        body.innerHTML = grid.blankGrid(doc);
        const layout = buildGridMap(body.rows);
        const planned = side.planSideBlock(layout, { minR: 2, maxR: 2, minC: 0, maxC: 16 }, 2);
        if (planned.cornerPlan) grid.applyBlocks([planned.cornerPlan]);
        const provider = grid.applyBlocks([planned.providerPlan])[0];
        provider.dataset.consumoProvider = supplierId;
        provider.querySelector('.cell-content').textContent = 'PROV LADO';
        grid.applyBlocks(planned.headerPlans).forEach((cell, idx) => {
            cell.querySelector('.cell-content').textContent = ['PRODUCTO', 'CONSUMO', 'STOCK', 'PEDIDO'][idx];
        });
        planned.rows.forEach((row, rIdx) => {
            const cells = grid.applyBlocks(row.plans);
            cells.forEach((cell, fIdx) => {
                cell.dataset.consumoProduct = rIdx ? secondId : productId;
                cell.dataset.consumoSupplier = supplierId;
                cell.dataset.consumoField = grid.PRODUCT_FIELDS[fIdx];
                if (fIdx === 0) cell.querySelector('.cell-content').textContent = `Prod ${rIdx + 1}`;
                if (fIdx === 1) cell.dataset.consumoSources = '[]';
            });
        });
        return body.innerHTML;
    };
    for (const build of [buildTopLayout, buildSideLayout]) {
        const sourceDom = new JSDOM();
        const env = boot({ documentData: reusable({ grid_html: build(sourceDom.window.document) }) });
        sourceDom.window.close();
        try {
            env.window.document.getElementById('btn-edit-mode').click();
            await settled(() => env.get('app').dataset.consumosMode === 'document');
            await tick();
            const original = env.api.getHtml();
            const suppliers = grid.readSuppliers(env.api.tbody);
            for (const supplier of suppliers) {
                const before = env.api.getHtml();
                const provider = [...env.api.tbody.querySelectorAll('[data-consumo-provider]')].find(cell => cell.dataset.consumoProvider === supplier.id);
                const button = provider.querySelector('.consumos-add-product');
                assert.ok(button.classList.contains('print-hidden'));
                button.click();
                const after = grid.readSuppliers(env.api.tbody);
                const changed = after.find(item => item.id === supplier.id);
                assert.equal(changed.products.length, supplier.products.length + 1, `${supplier.name}: ${env.window.document.getElementById('editor-status').textContent}`);
                assert.deepEqual(changed.products.slice(0, -1), supplier.products);
                assert.equal(changed.products.at(-1).name, '');
                assert.deepEqual(changed.products.at(-1).source_ids, []);
                assert.equal(new Set(changed.products.map(product => product.id)).size, changed.products.length);
                assert.deepEqual(after.filter(item => item.id !== supplier.id), suppliers.filter(item => item.id !== supplier.id));
                assert.equal(env.api.getHtml().includes('consumos-add-product'), false, 'Editor controls must not be persisted');
                env.api.undo();
                assert.equal(env.api.getHtml(), before);
                env.api.redo();
                assert.equal(grid.readSuppliers(env.api.tbody).find(item => item.id === supplier.id).products.length, supplier.products.length + 1);
                env.api.undo();
            }
            assert.equal(env.api.getHtml(), original);
        } finally { env.dom.window.close(); }
    }
});

domTest('adding a product preserves PDF links and saved data, then focuses the new name', async () => {
    const env = boot({ documentData: reusable({ grid_html: html(['Producto existente'], [['source-a']]) }) });
    try {
        const hiddenButton = env.api.tbody.querySelector('.consumos-add-product');
        const original = env.api.getHtml();
        hiddenButton.click();
        assert.equal(env.api.getHtml(), original, 'The action only works while editing the document');
        env.window.document.getElementById('btn-edit-mode').click();
        await settled(() => env.get('app').dataset.consumosMode === 'document');
        await tick();
        env.api.tbody.querySelector('.consumos-add-product').click();
        const suppliers = grid.readSuppliers(env.api.tbody);
        assert.equal(suppliers[0].products.length, 2, env.window.document.getElementById('editor-status').textContent);
        assert.deepEqual(suppliers[0].products[0].source_ids, ['source-a']);
        assert.equal(env.window.document.activeElement.closest('td').dataset.consumoProduct, suppliers[0].products[1].id);
        await env.api.saveTemplate();
        const saved = JSON.parse(env.requests.find(item => item.url === '/document/save').options.body);
        assert.equal(saved.suppliers[0].products.length, 2);
        assert.equal(saved.grid_html.includes('consumos-add-product'), false);
    } finally { env.dom.window.close(); }
});

domTest('new blank product rows map on a dedicated screen and explicit missing rows are skipped', async () => {
    const documentData = reusable({ grid_html: html(['', 'Producto que no está']) });
    const pending = [{ product_id: productId, product_name: '', supplier_name: 'Proveedor de prueba', reason: 'new' }, { product_id: secondId, product_name: 'Producto que no está', supplier_name: 'Proveedor de prueba', reason: 'missing' }];
    const env = boot({ documentData, pending });
    try {
        await env.importPdf();
        assert.equal(env.get('app').dataset.consumosMode, 'linking');
        assert.equal(env.get('section-nav').hidden, true);
        assert.equal(env.get('workflow-workspace').hidden, true);
        assert.equal(env.get('linking-list').querySelectorAll('[data-map-product]').length, 2);
        assert.equal(env.get('linking-meter').max, 2);
        assert.equal(env.get('linking-meter').value, 0);
        assert.equal(env.get('linking-save').disabled, true);
        env.get('linking-list').querySelector('[data-map-product]').click();
        assert.equal(env.get('product-dropdown')._testPopoverOpen, true);
        const choices = env.get('bind-list').querySelectorAll('input');
        assert.equal(choices.length, 2, 'Repeated names must remain separate source choices');
        choices[0].checked = true;
        choices[0].dispatchEvent(new env.window.Event('change', { bubbles: true }));
        env.get('bind-form').dispatchEvent(new env.window.Event('submit', { bubbles: true, cancelable: true }));
        assert.equal(env.get('bind-error').hidden, true);
        assert.equal(env.api.tbody.querySelector('[data-consumo-field="product"] .cell-content').textContent, 'Producto del PDF');
        assert.match(env.get('linking-progress').textContent, /1 de 2/);
        assert.equal(env.get('linking-meter').max, 2);
        assert.equal(env.get('linking-meter').value, 1);
        const skip = env.get('linking-list').querySelector(`[data-skip-product="${secondId}"]`);
        assert.match(skip.closest('label').textContent, /Dejar vacío en esta hoja/);
        skip.checked = true;
        skip.dispatchEvent(new env.window.Event('change', { bubbles: true }));
        assert.match(env.get('linking-list').querySelector(`[data-map-product="${secondId}"]`).textContent, /Revisado · Sin consumo en esta hoja/);
        assert.equal(env.get('linking-meter').max, 2);
        assert.equal(env.get('linking-meter').value, 2);
        assert.equal(env.get('linking-save').disabled, false);
        env.get('linking-save').click();
        await settled(() => env.get('app').dataset.consumosMode === 'sheet');
        assert.equal(env.get('section-nav').hidden, true);
        const complete = env.requests.find(item => item.url === '/imports/7/complete');
        const payload = JSON.parse(complete.options.body);
        assert.deepEqual(payload.links, [{ product_id: productId, source_ids: ['source-a'], product_name: 'Producto del PDF' }, { product_id: secondId, source_ids: [], skip: true }]);
        assert.equal(env.api.tbody.querySelector('[data-consumo-field="consumo"] .cell-content').textContent, '2 KG');
        assert.equal([...env.api.tbody.querySelectorAll('[data-consumo-field="consumo"] .cell-content')][1].textContent, '');
        assert.equal(env.api.tbody.querySelector('.consumos-cell-dropdown'), null);
        assert.equal(env.api.editMode, false);
    } finally { env.dom.window.close(); }
});

domTest('a later PDF reuses remembered links automatically and only requests review for unresolved products', async () => {
    const env = boot({
        documentData: reusable({ grid_html: html(['Producto de mi documento'], [['source-b']]) }),
        preparedHtml: html(),
        pending: [{ product_id: productId, product_name: 'Producto de mi documento', supplier_name: 'Proveedor', reason: 'missing' }],
    });
    try {
        await env.importPdf();
        await settled(() => env.get('app').dataset.consumosMode === 'linking');
        assert.equal(env.get('workflow-linking').hidden, false);
        assert.equal(env.get('linking-save').disabled, true);
        assert.deepEqual(env.requests.map(item => item.url), ['/import', '/imports/7/prepare']);
        assert.equal(JSON.parse(env.requests[1].options.body).review_all, undefined);
        assert.deepEqual(grid.readSuppliers(env.api.tbody)[0].products[0].source_ids, []);
    } finally { env.dom.window.close(); }
});

domTest('document omits redundant header controls and preserves saving and design tools', async () => {
    const env = boot();
    try {
        env.window.document.getElementById('btn-edit-mode').click();
        await settled(() => env.get('app').dataset.consumosMode === 'document');
        await tick();
        assert.equal(env.get('front-template'), null);
        assert.equal(env.get('reference-template'), null);
        assert.equal(env.get('title').type, 'hidden');
        assert.equal(env.api.editMode, true);
        assert.equal(env.api.tbody.querySelector('.consumos-cell-dropdown'), null);
        await env.api.saveTemplate();
        const payload = JSON.parse(env.requests.find(item => item.url === '/document/save').options.body);
        assert.equal(payload.id, 4);
        assert.equal(payload.version, 3);
        assert.equal(payload.import_id, undefined);
        assert.ok(payload.suppliers.length > 0);
        assert.equal(payload.suppliers.flatMap(supplier => supplier.products).some(product => product.source_ids.length), false);
    } finally { env.dom.window.close(); }
});

domTest('an unfinished import resumes after reload without another upload and restores links plus explicit skips', async () => {
    const documentData = reusable({ grid_html: html(['', 'Producto ausente']) });
    const pending = [{ product_id: productId, product_name: '', supplier_name: 'Proveedor', reason: 'new' }, { product_id: secondId, product_name: 'Producto ausente', supplier_name: 'Proveedor', reason: 'missing' }];
    let storageData;
    const first = boot({ documentData, pending });
    try {
        await first.importPdf();
        first.get('linking-list').querySelector('[data-map-product]').click();
        const source = first.get('bind-list').querySelector('input[value="source-a"]');
        source.checked = true; source.dispatchEvent(new first.window.Event('change', { bubbles: true }));
        first.get('bind-form').dispatchEvent(new first.window.Event('submit', { bubbles: true, cancelable: true }));
        const skip = first.get('linking-list').querySelector(`[data-skip-product="${secondId}"]`);
        skip.checked = true; skip.dispatchEvent(new first.window.Event('change', { bubbles: true }));
        first.window.dispatchEvent(new first.window.Event('pagehide'));
        storageData = Object.fromEntries(Array.from({ length: first.window.localStorage.length }, (_, index) => { const key = first.window.localStorage.key(index); return [key, first.window.localStorage.getItem(key)]; }));
        assert.ok(storageData['mmqp:consumos:workflow-test:user-2:pending-import']);
    } finally { first.dom.window.close(); }
    const resumed = boot({ documentData, pending, storageData });
    try {
        resumed.get('import-button').click();
        assert.equal(resumed.get('resume-import-panel').hidden, false);
        resumed.get('resume-import').click();
        await settled(() => resumed.get('app').dataset.consumosMode === 'linking');
        assert.equal(resumed.get('draft-banner').hidden, false);
        resumed.get('draft-restore').click();
        await settled(() => resumed.get('draft-banner').hidden);
        assert.match(resumed.get('linking-progress').textContent, /2 de 2/);
        assert.equal(resumed.get('linking-save').disabled, false);
        assert.equal(resumed.get('linking-list').querySelector(`[data-skip-product="${secondId}"]`).checked, true);
        assert.equal(resumed.requests.some(item => item.url === '/import'), false);
        assert.deepEqual(JSON.parse(resumed.api.tbody.querySelector('[data-consumo-field="consumo"]').dataset.consumoSources), ['source-a']);
        resumed.get('linking-save').click();
        await settled(() => resumed.get('app').dataset.consumosMode === 'sheet');
        assert.equal(resumed.window.localStorage.getItem('mmqp:consumos:workflow-test:user-2:pending-import'), null);
        assert.equal(resumed.get('draft-status').textContent, '');
    } finally { resumed.dom.window.close(); }
});

domTest('skip-only decisions are recoverable and a changed document blocks stale import recovery', async () => {
    const pointerKey = 'mmqp:consumos:workflow-test:user-2:pending-import';
    const oldDraftKey = 'mmqp:consumos:workflow-test:user-2:mapping:4:3:7';
    const pending = [{ product_id: productId, product_name: 'Producto de mi documento', supplier_name: 'Proveedor', reason: 'missing' }];
    let storageData;
    const first = boot({ pending });
    try {
        await first.importPdf();
        const skip = first.get('linking-list').querySelector('[data-skip-product]');
        skip.checked = true; skip.dispatchEvent(new first.window.Event('change', { bubbles: true }));
        first.window.dispatchEvent(new first.window.Event('pagehide'));
        storageData = { [pointerKey]: first.window.localStorage.getItem(pointerKey), [oldDraftKey]: first.window.localStorage.getItem(oldDraftKey) };
        assert.deepEqual(JSON.parse(storageData[oldDraftKey]).snapshot.skipped_product_ids, [productId]);
    } finally { first.dom.window.close(); }
    const changed = boot({ pending, storageData, request: async () => ({ ok: true, json: async () => ({ document: reusable({ version: 4 }) }) }) });
    try {
        changed.get('import-button').click();
        changed.get('resume-import').click();
        await settled(() => changed.get('import-error').hidden === false);
        assert.match(changed.get('import-error').textContent, /documento ha cambiado/);
        assert.equal(changed.get('app').dataset.consumosMode, 'entry');
        assert.equal(changed.requests.some(item => item.url.endsWith('/prepare')), false);
        assert.equal(changed.window.localStorage.getItem(oldDraftKey), storageData[oldDraftKey]);
        changed.get('resume-discard').click();
        assert.equal(changed.window.localStorage.getItem(pointerKey), null);
        assert.equal(changed.window.localStorage.getItem(oldDraftKey), storageData[oldDraftKey]);
    } finally { changed.dom.window.close(); }
});

domTest('saving the selected sheet defers other edits and new sheets until each is saved individually', async () => {
    const pages = twoPages();
    const env = boot({ documentData: reusable({ pages, grid_html: pages[0].grid_html }) });
    try {
        env.window.document.getElementById('btn-edit-mode').click();
        await settled(() => env.get('app').dataset.consumosMode === 'document');
        await tick();
        assert.equal(env.get('page-select').options.length, 2);
        editCell(env, '[data-consumo-field="product"]', 'Primer producto editado');
        selectPage(env, pageB);
        assert.match(env.api.tbody.textContent, /Segundo producto/);
        editCell(env, '[data-consumo-field="product"]', 'Segundo producto editado');
        env.get('page-title').value = 'Otros proveedores';
        env.get('page-title').dispatchEvent(new env.window.Event('input', { bubbles: true }));
        env.get('page-up').click();
        assert.deepEqual([...env.get('page-select').options].map(option => option.value), [pageB, pageA]);
        selectPage(env, pageA);
        assert.match(env.api.tbody.textContent, /Primer producto editado/);
        assert.equal(env.api.dirty, true);
        env.get('page-add').click();
        const addedId = env.get('page-select').value;
        assert.notEqual(addedId, pageA);
        assert.notEqual(addedId, pageB);
        assert.match(addedId, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
        assert.equal(env.get('page-select').options.length, 3);
        assert.equal(grid.readSuppliers(env.api.tbody).length, 0);
        editCell(env, 'td', 'Anotación libre de embalajes');
        env.get('page-title').value = 'Embalajes';
        env.get('page-title').dispatchEvent(new env.window.Event('input', { bubbles: true }));
        env.get('page-up').click();
        assert.deepEqual([...env.get('page-select').options].map(option => option.value), [pageB, addedId, pageA]);
        env.get('page-down').click();
        assert.deepEqual([...env.get('page-select').options].map(option => option.value), [pageB, pageA, addedId]);
        selectPage(env, pageA);
        assert.match(env.api.tbody.textContent, /Primer producto editado/);
        await env.api.saveTemplate();
        const payload = JSON.parse(env.requests.find(item => item.url === '/document/save').options.body);
        assert.deepEqual(payload.pages.map(page => [page.id, page.title]), [[pageB, 'Proveedores'], [pageA, 'Portada']]);
        assert.match(payload.pages[0].grid_html, /Segundo producto/);
        assert.doesNotMatch(payload.pages[0].grid_html, /Segundo producto editado/);
        assert.match(payload.pages[1].grid_html, /Primer producto editado/);
        assert.equal(payload.grid_html, payload.pages[0].grid_html);
        assert.deepEqual(payload.suppliers.flatMap(supplier => supplier.products.map(product => product.name)), ['Segundo producto', 'Primer producto editado']);
        assert.notEqual(env.window.localStorage.getItem('mmqp:consumos:workflow-test:user-2:document:4'), null);
        selectPage(env, addedId);
        await env.api.saveTemplate();
        const second = JSON.parse(env.requests.filter(item => item.url === '/document/save').at(-1).options.body);
        assert.deepEqual(second.pages.map(page => [page.id, page.title]), [[pageB, 'Proveedores'], [pageA, 'Portada'], [addedId, 'Embalajes']]);
        assert.match(second.pages[2].grid_html, /Anotación libre de embalajes/);
        selectPage(env, pageB);
        await env.api.saveTemplate();
        const third = JSON.parse(env.requests.filter(item => item.url === '/document/save').at(-1).options.body);
        assert.deepEqual(third.pages.map(page => [page.id, page.title]), [[pageB, 'Otros proveedores'], [pageA, 'Portada'], [addedId, 'Embalajes']]);
        assert.match(third.pages[0].grid_html, /Segundo producto editado/);
        assert.equal(payload.suppliers.flatMap(supplier => supplier.products).some(product => product.source_ids.length), false);
        assert.equal(env.api.dirty, false);
        env.window.dispatchEvent(new env.window.Event('pagehide'));
        assert.equal(env.window.localStorage.getItem('mmqp:consumos:workflow-test:user-2:document:4'), null);
    } finally { env.dom.window.close(); }
});

domTest('a saved multipage ficha selection preserves stock edits and real source links when switching sheets', async () => {
    const pages = twoPages({ linked: true });
    const saved = { id: 19, version: 2, title: 'Ficha completa', import_id: 7, suppliers: [], pages, grid_html: pages[0].grid_html };
    const env = boot({ savedSheets: [saved], imports: [report] });
    try {
        assert.equal(env.get('entry-sheets'), null);
        env.get('sheet-select').value = '19';
        env.get('sheet-select').dispatchEvent(new env.window.Event('change', { bubbles: true }));
        await settled(() => env.get('app').dataset.consumosMode === 'sheet');
        await tick();
        assert.equal(env.get('section-nav').hidden, true);
        assert.deepEqual(env.requests.map(item => item.url), ['/sheets/19', '/reports/7']);
        assert.equal(env.api.editMode, false);
        assert.equal(env.get('title').value, 'Ficha completa');
        assert.equal(env.get('page-select').options.length, 2);
        assert.equal(env.api.tbody.querySelector('[data-consumo-field="consumo"] .cell-content').textContent, '2 KG');
        editCell(env, '[data-consumo-field="stock"]', '11');
        selectPage(env, pageB);
        assert.equal(env.api.tbody.querySelector('[data-consumo-field="consumo"] .cell-content').textContent, '8 KG');
        editCell(env, '[data-consumo-field="stock"]', '22');
        selectPage(env, pageA);
        assert.equal(env.api.tbody.querySelector('[data-consumo-field="stock"] .cell-content').textContent, '11');
        assert.deepEqual(grid.readSuppliers(env.api.tbody)[0].products[0].source_ids, ['source-a']);
        await env.api.saveTemplate();
        const payload = JSON.parse(env.requests.find(item => item.url === '/sheet/save').options.body);
        assert.equal(payload.pages.length, 2);
        assert.deepEqual(payload.suppliers.flatMap(supplier => supplier.products.map(product => [product.stock, product.source_ids])), [['11', ['source-a']], ['22', ['source-b']]]);
        assert.equal(env.api.dirty, false);
    } finally { env.dom.window.close(); }
});

domTest('viewing saved sheets with server-calculated quantities does not create a phantom recovery draft', async () => {
    const pages = twoPages({ linked: true });
    for (const [index, page] of pages.entries()) {
        // Real server responses include calculated consumption in every page's HTML.
        page.grid_html = page.grid_html.replace(/(data-consumo-field="consumo"[^>]*><div class="cell-content">)/,
            match => `${match}${index ? '8' : '2'} KG`);
    }
    const saved = { id: 19, version: 2, title: 'Ficha completa', import_id: 7, suppliers: [], pages, grid_html: pages[0].grid_html };
    const env = boot({ savedSheets: [saved], imports: [report] });
    const draftKey = 'mmqp:consumos:workflow-test:user-2:19';
    try {
        env.get('sheet-select').value = '19';
        env.get('sheet-select').dispatchEvent(new env.window.Event('change', { bubbles: true }));
        await settled(() => env.get('app').dataset.consumosMode === 'sheet');
        await tick();
        for (const id of [pageB, pageA, pageB]) {
            selectPage(env, id);
            assert.equal(env.api.dirty, false);
            assert.equal(env.api.tbody.querySelector('[data-consumo-field="consumo"] .cell-content').textContent, id === pageA ? '2 KG' : '8 KG');
            assert.deepEqual(grid.readSuppliers(env.api.tbody)[0].products[0].source_ids, [id === pageA ? 'source-a' : 'source-b']);
            env.window.dispatchEvent(new env.window.Event('pagehide'));
            assert.equal(env.window.localStorage.getItem(draftKey), null, 'Navigation without editing must not create a draft');
        }
        editCell(env, '[data-consumo-field="stock"]', '11');
        await env.api.saveTemplate();
        for (const id of [pageA, pageB]) {
            selectPage(env, id);
            assert.equal(env.api.tbody.querySelector('[data-consumo-field="consumo"] .cell-content').textContent, id === pageA ? '2 KG' : '8 KG');
            assert.deepEqual(grid.readSuppliers(env.api.tbody)[0].products[0].source_ids, [id === pageA ? 'source-a' : 'source-b']);
            env.window.dispatchEvent(new env.window.Event('pagehide'));
            assert.equal(env.window.localStorage.getItem(draftKey), null, 'A confirmed save must stay clean on every page');
        }
    } finally { env.dom.window.close(); }
});

domTest('an existing imported consumption prepares mapping without uploading the PDF again', async () => {
    const pending = [{ product_id: productId, product_name: 'Producto de mi documento', supplier_name: 'Proveedor', reason: 'new' }];
    const env = boot({ pending, imports: [report] });
    try {
        env.window.document.getElementById('btn-edit-mode').click();
        await settled(() => env.get('app').dataset.consumosMode === 'document');
        await tick();
        await env.api.saveTemplate();
        env.get('workflow-back').click();
        await settled(() => env.get('app').dataset.consumosMode === 'entry');
        env.requests.length = 0;
        env.get('import-button').click();
        assert.equal(env.get('import-dialog').open, true);
        assert.equal(env.get('use-existing').disabled, true);
        env.get('existing-import').value = '7';
        env.get('existing-import').dispatchEvent(new env.window.Event('change', { bubbles: true }));
        assert.equal(env.get('use-existing').disabled, false);
        env.get('use-existing').click();
        await settled(() => env.get('app').dataset.consumosMode === 'linking');
        await tick();
        assert.deepEqual(env.requests.map(item => item.url), ['/reports/7', '/document', '/imports/7/prepare']);
        assert.equal(env.requests.some(item => item.options.body instanceof env.window.FormData), false);
        assert.equal(env.get('import-dialog').open, false);
        assert.equal(env.get('linking-list').querySelectorAll('[data-map-product]').length, 1);
    } finally { env.dom.window.close(); }
});

function scopedImportFixture(storageData = {}) {
    const pages = twoPages();
    const documentData = reusable({ pages, grid_html: pages[0].grid_html });
    const request = async (url, options) => {
        let result;
        if (url === '/document') result = { document: documentData };
        else if (url === '/import' || url === '/reports/7') result = { import: report };
        else if (url.endsWith('/prepare') || url.endsWith('/complete')) {
            const ids = JSON.parse(options.body).page_ids || pages.map(page => page.id);
            const selected = pages.filter(page => ids.includes(page.id));
            const sheet = { id: null, version: null, title: documentData.title, import_id: report.id, suppliers: [], pages: selected, grid_html: selected[0].grid_html };
            if (url.endsWith('/prepare')) result = { document: documentData, import: report, page_ids: ids, sheet, pending: selected.map(page => ({ page_id: page.id, product_id: page.id === pageA ? productId : otherProductId, product_name: page.title, supplier_name: 'Proveedor', reason: 'manual-review' })) };
            else result = { document: { ...documentData, version: 4 }, sheet: { ...sheet, id: 19, version: 1 } };
        } else throw new Error(`Unexpected scoped import request: ${url}`);
        return { ok: true, json: async () => result };
    };
    return boot({ documentData, imports: [report], request, storageData });
}
function markImportPage(env, id, checked) {
    const checkbox = env.get('import-pages-list').querySelector(`input[value="${id}"]`);
    checkbox.checked = checked;
    checkbox.dispatchEvent(new env.window.Event('change', { bubbles: true }));
}
domTest('import lists every saved page checked, counts products, and requires at least one page', async () => {
    const env = scopedImportFixture();
    try {
        env.get('import-button').click();
        assert.equal(env.get('section-nav').hidden, true);
        const choices = [...env.get('import-pages-list').querySelectorAll('input')];
        assert.deepEqual(choices.map(input => [input.value, input.checked]), [[pageA, true], [pageB, true]]);
        assert.match(env.get('import-pages-list').textContent, /Portada.*1 productos.*Proveedores.*1 productos/);
        assert.match(env.get('import-pages-summary').textContent, /2 de 2.*2 productos/);
        env.get('existing-import').value = '7';
        env.get('existing-import').dispatchEvent(new env.window.Event('change', { bubbles: true }));
        markImportPage(env, pageA, false);
        assert.match(env.get('import-pages-summary').textContent, /1 de 2.*1 productos/);
        markImportPage(env, pageB, false);
        assert.equal(env.get('import-submit').disabled, true);
        assert.equal(env.get('use-existing').disabled, true);
        env.get('resume-discard').click();
        assert.equal(env.get('use-existing').disabled, true, 'Refreshing the pending notice must not enable a zero-page import');
        Object.defineProperty(env.get('pdf'), 'files', { configurable: true, value: [new env.window.File(['fixture'], 'informe.pdf', { type: 'application/pdf' })] });
        env.get('import-form').dispatchEvent(new env.window.Event('submit', { bubbles: true, cancelable: true }));
        await tick();
        assert.equal(env.requests.length, 0, 'Invalid selection is rejected before uploading the PDF');
        assert.match(env.get('import-error').textContent, /Selecciona al menos una hoja/);
        env.get('cancel-import').click();
        assert.equal(env.get('section-nav').hidden, false);
        env.get('import-button').click();
        assert.equal(env.get('section-nav').hidden, true);
        assert.equal(env.get('import-pages-list').querySelectorAll('input:checked').length, 2);
    } finally { env.dom.window.close(); }
});

for (const upload of [false, true]) {
    domTest(`${upload ? 'a new PDF' : 'an existing report'} forwards only checked template pages through completion`, async () => {
        const env = scopedImportFixture();
        try {
            env.get('import-button').click();
            markImportPage(env, pageA, false);
            if (upload) {
                Object.defineProperty(env.get('pdf'), 'files', { configurable: true, value: [new env.window.File(['fixture'], 'informe.pdf', { type: 'application/pdf' })] });
                env.get('import-form').dispatchEvent(new env.window.Event('submit', { bubbles: true, cancelable: true }));
            } else {
                env.get('existing-import').value = '7';
                env.get('existing-import').dispatchEvent(new env.window.Event('change', { bubbles: true }));
                env.get('use-existing').click();
            }
            await settled(() => env.get('app').dataset.consumosMode === 'linking' && env.get('app').getAttribute('aria-busy') === 'false');
            assert.deepEqual(JSON.parse(env.requests.find(item => item.url.endsWith('/prepare')).options.body).page_ids, [pageB]);
            assert.equal(env.get('page-select').options.length, 1);
            assert.equal(env.get('page-select').value, pageB);
            assert.equal(env.get('linking-list').querySelectorAll('[data-map-product]').length, 1);
            const skip = env.get('linking-list').querySelector('[data-skip-product]');
            skip.checked = true;
            skip.dispatchEvent(new env.window.Event('change', { bubbles: true }));
            env.get('linking-save').click();
            await settled(() => env.get('app').dataset.consumosMode === 'sheet' && env.get('app').getAttribute('aria-busy') === 'false');
            const completed = JSON.parse(env.requests.find(item => item.url.endsWith('/complete')).options.body);
            assert.deepEqual(completed.page_ids, [pageB]);
            assert.deepEqual(completed.links.map(link => link.product_id), [otherProductId]);
            assert.equal(env.requests.some(item => item.url === '/import'), upload);
            assert.equal(env.window.localStorage.getItem('mmqp:consumos:workflow-test:user-2:pending-import'), null);
        } finally { env.dom.window.close(); }
    });
}

domTest('resuming a subset keeps its original pages and isolated mapping draft despite new default checkboxes', async () => {
    const pointerKey = 'mmqp:consumos:workflow-test:user-2:pending-import';
    const subsetKey = 'mmqp:consumos:workflow-test:user-2:mapping:4:3:7:pages:2';
    const allPagesKey = 'mmqp:consumos:workflow-test:user-2:mapping:4:3:7';
    let storageData;
    const first = scopedImportFixture();
    try {
        first.get('import-button').click();
        markImportPage(first, pageA, false);
        first.get('existing-import').value = '7';
        first.get('existing-import').dispatchEvent(new first.window.Event('change', { bubbles: true }));
        first.get('use-existing').click();
        await settled(() => first.get('app').dataset.consumosMode === 'linking' && first.get('app').getAttribute('aria-busy') === 'false');
        const skip = first.get('linking-list').querySelector('[data-skip-product]');
        skip.checked = true;
        skip.dispatchEvent(new first.window.Event('change', { bubbles: true }));
        first.window.dispatchEvent(new first.window.Event('pagehide'));
        assert.deepEqual(JSON.parse(first.window.localStorage.getItem(pointerKey)).page_ids, [pageB]);
        assert.ok(first.window.localStorage.getItem(subsetKey));
        assert.equal(first.window.localStorage.getItem(allPagesKey), null);
        storageData = Object.fromEntries(Array.from({ length: first.window.localStorage.length }, (_, index) => {
            const key = first.window.localStorage.key(index);
            return [key, first.window.localStorage.getItem(key)];
        }));
    } finally { first.dom.window.close(); }
    const resumed = scopedImportFixture(storageData);
    try {
        resumed.get('import-button').click();
        assert.equal(resumed.get('import-pages-list').querySelectorAll('input:checked').length, 2);
        assert.match(resumed.get('resume-import-message').textContent, /Hojas: Proveedores/);
        resumed.get('resume-import').click();
        await settled(() => resumed.get('app').dataset.consumosMode === 'linking' && resumed.get('app').getAttribute('aria-busy') === 'false');
        assert.deepEqual(JSON.parse(resumed.requests.find(item => item.url.endsWith('/prepare')).options.body).page_ids, [pageB]);
        assert.equal(resumed.get('page-select').options.length, 1);
        resumed.get('draft-restore').click();
        await settled(() => resumed.get('draft-banner').hidden && resumed.get('app').getAttribute('aria-busy') === 'false');
        assert.equal(resumed.get('linking-list').querySelector('[data-skip-product]').checked, true);
        assert.match(resumed.get('linking-progress').textContent, /1 de 1 revisados/);
    } finally { resumed.dom.window.close(); }
});

domTest('unresolved binding review survives reload in its own draft and is forwarded through preparation and completion', async () => {
    const documentData = reusable({ grid_html: html(['', 'Producto gestionado en otra tienda']) });
    const pending = [
        { product_id: productId, product_name: '', supplier_name: 'Proveedor', reason: 'new' },
        { product_id: secondId, product_name: 'Producto gestionado en otra tienda', supplier_name: 'Proveedor', reason: 'missing' },
    ];
    const pointerKey = 'mmqp:consumos:workflow-test:user-2:pending-import';
    const ordinaryDraftKey = 'mmqp:consumos:workflow-test:user-2:mapping:4:3:7';
    let storageData;
    const first = boot({ documentData, pending, imports: [report] });
    try {
        first.get('import-button').click();
        assert.equal(first.get('review-all'), null);
        first.get('existing-import').value = '7';
        first.get('existing-import').dispatchEvent(new first.window.Event('change', { bubbles: true }));
        first.get('use-existing').click();
        await settled(() => first.get('app').dataset.consumosMode === 'linking' && first.get('app').getAttribute('aria-busy') === 'false');
        const prepared = JSON.parse(first.requests.find(item => item.url.endsWith('/prepare')).options.body);
        assert.equal(prepared.review_all, undefined);
        assert.match(first.get('linking-progress').textContent, /Las elecciones guardadas ya se han aplicado/);
        first.get('linking-list').querySelector(`[data-map-product="${productId}"]`).click();
        const source = first.get('bind-list').querySelector('[value="source-a"]');
        source.checked = true;
        source.dispatchEvent(new first.window.Event('change', { bubbles: true }));
        first.get('bind-form').dispatchEvent(new first.window.Event('submit', { bubbles: true, cancelable: true }));
        const skip = first.get('linking-list').querySelector(`[data-skip-product="${secondId}"]`);
        skip.checked = true;
        skip.dispatchEvent(new first.window.Event('change', { bubbles: true }));
        first.window.dispatchEvent(new first.window.Event('pagehide'));
        assert.ok(first.window.localStorage.getItem(ordinaryDraftKey));
        assert.equal(JSON.parse(first.window.localStorage.getItem(pointerKey)).review_all, undefined);
        storageData = Object.fromEntries(Array.from({ length: first.window.localStorage.length }, (_, index) => {
            const key = first.window.localStorage.key(index);
            return [key, first.window.localStorage.getItem(key)];
        }));
    } finally { first.dom.window.close(); }
    const resumed = boot({ documentData, pending, imports: [report], storageData });
    try {
        resumed.get('import-button').click();
        resumed.get('resume-import').click();
        await settled(() => resumed.get('app').dataset.consumosMode === 'linking' && resumed.get('app').getAttribute('aria-busy') === 'false');
        assert.equal(JSON.parse(resumed.requests.find(item => item.url.endsWith('/prepare')).options.body).review_all, undefined);
        assert.equal(resumed.get('draft-banner').hidden, false);
        const recoveryBanner = resumed.get('draft-banner');
        const recoveryStatus = resumed.get('draft-status');
        assert.equal(recoveryBanner.parentElement, resumed.get('linking-draft-host'));
        assert.equal(recoveryStatus.parentElement, resumed.get('linking-draft-host'));
        assert.equal(resumed.get('workflow-linking').contains(recoveryBanner), true);
        assert.equal(recoveryBanner.closest('[hidden]'), null, 'Pending review recovery must not remain inside the hidden editor');
        assert.equal(resumed.window.document.querySelectorAll('#consumos-draft-banner').length, 1);
        assert.equal(resumed.window.document.querySelectorAll('#consumos-draft-status').length, 1);
        assert.equal(resumed.get('linking-list').inert, true);
        assert.equal(resumed.get('linking-save').inert, true);
        resumed.get('linking-list').querySelector('[data-map-product]').click();
        assert.equal(Boolean(resumed.get('product-dropdown')._testPopoverOpen), false, 'Product selection stays locked until recovery is resolved');
        resumed.get('draft-restore').click();
        await settled(() => resumed.get('draft-banner').hidden && resumed.get('app').getAttribute('aria-busy') === 'false');
        assert.equal(resumed.get('linking-list').inert, false);
        assert.equal(resumed.get('linking-save').inert, false);
        assert.deepEqual(grid.readSuppliers(resumed.api.tbody)[0].products[0].source_ids, ['source-a']);
        assert.match(resumed.get('linking-progress').textContent, /2 de 2/);
        assert.equal(resumed.get('linking-list').querySelector(`[data-skip-product="${secondId}"]`).checked, true);
        resumed.get('linking-save').click();
        await settled(() => resumed.get('app').dataset.consumosMode === 'sheet' && resumed.get('app').getAttribute('aria-busy') === 'false');
        assert.equal(resumed.get('draft-banner'), recoveryBanner, 'Move the existing recovery panel instead of duplicating it');
        assert.equal(resumed.get('draft-status'), recoveryStatus);
        assert.equal(recoveryBanner.parentElement, resumed.get('workspace-draft-host'));
        assert.equal(recoveryStatus.parentElement, resumed.get('workspace-draft-host'));
        assert.equal(resumed.window.document.querySelectorAll('#consumos-draft-banner').length, 1);
        assert.equal(resumed.window.document.querySelectorAll('#consumos-draft-status').length, 1);
        const complete = JSON.parse(resumed.requests.find(item => item.url.endsWith('/complete')).options.body);
        assert.equal(complete.review_all, undefined);
        assert.deepEqual(complete.links.map(link => [link.product_id, link.source_ids, Boolean(link.skip)]), [[productId, ['source-a'], false], [secondId, [], true]]);
        assert.equal(resumed.window.localStorage.getItem(pointerKey), null);
        assert.equal(resumed.window.localStorage.getItem(ordinaryDraftKey), null);
        assert.equal(resumed.requests.some(item => item.url === '/import'), false);
    } finally { resumed.dom.window.close(); }
});

domTest('import options distinguish repeated file names by their original import dates', () => {
    const imports = [
        { ...report, created_at: '2026-10-17T08:05:00Z' },
        { ...report, id: 8, created_at: '2026-10-18T09:10:00Z' },
    ];
    const env = boot({ imports });
    try {
        const choices = [...env.get('existing-import').options].filter(option => option.value);
        assert.equal(choices.length, 2);
        assert.match(choices[0].textContent, /informe\.pdf.*17\/10\/2026.*2 filas/);
        assert.match(choices[1].textContent, /informe\.pdf.*18\/10\/2026.*2 filas/);
        assert.notEqual(choices[0].textContent, choices[1].textContent);
    } finally { env.dom.window.close(); }
});

domTest('manual links can repeat a source across sheets but prevent assigning it to two products on the same sheet', async () => {
    const pages = twoPages();
    pages[1].grid_html = html(['Segundo producto', 'Tercer producto']).replaceAll(supplierId, otherSupplierId).replaceAll(productId, otherProductId);
    const pending = [
        { product_id: productId, product_name: 'Primer producto', supplier_name: 'Proveedor', reason: 'new' },
        { product_id: otherProductId, product_name: 'Segundo producto', supplier_name: 'Otro proveedor', reason: 'new' },
        { product_id: secondId, product_name: 'Tercer producto', supplier_name: 'Otro proveedor', reason: 'new' },
    ];
    const env = boot({ documentData: reusable({ pages, grid_html: pages[0].grid_html }), pending });
    try {
        await env.importPdf();
        env.get('linking-list').querySelector(`[data-map-product="${productId}"]`).click();
        env.get('bind-search').value = '';
        env.get('bind-search').dispatchEvent(new env.window.Event('input', { bubbles: true }));
        const first = env.get('bind-list').querySelector('[value="source-a"]');
        first.checked = true;
        first.dispatchEvent(new env.window.Event('change', { bubbles: true }));
        env.get('bind-form').dispatchEvent(new env.window.Event('submit', { bubbles: true, cancelable: true }));
        env.get('linking-list').querySelector(`[data-map-product="${otherProductId}"]`).click();
        assert.equal(env.get('page-select').value, pageB);
        env.get('bind-search').value = '';
        env.get('bind-search').dispatchEvent(new env.window.Event('input', { bubbles: true }));
        assert.equal(env.get('bind-list').querySelector('[value="source-a"]').disabled, false, 'The same product may appear on a separate photographed sheet');
        const second = env.get('bind-list').querySelector('[value="source-a"]');
        second.checked = true;
        second.dispatchEvent(new env.window.Event('change', { bubbles: true }));
        env.get('bind-form').dispatchEvent(new env.window.Event('submit', { bubbles: true, cancelable: true }));
        env.get('linking-list').querySelector(`[data-map-product="${secondId}"]`).click();
        env.get('bind-search').value = '';
        env.get('bind-search').dispatchEvent(new env.window.Event('input', { bubbles: true }));
        assert.equal(env.get('bind-list').querySelector('[value="source-a"]').disabled, true, 'A source cannot feed two different rows within the same sheet');
        const third = env.get('bind-list').querySelector('[value="source-b"]');
        third.checked = true;
        third.dispatchEvent(new env.window.Event('change', { bubbles: true }));
        env.get('bind-form').dispatchEvent(new env.window.Event('submit', { bubbles: true, cancelable: true }));
        assert.match(env.get('linking-progress').textContent, /3 de 3/);
        assert.equal(env.get('linking-save').disabled, false);
        selectPage(env, pageA);
        assert.deepEqual(grid.readSuppliers(env.api.tbody)[0].products[0].source_ids, ['source-a']);
        assert.equal(env.api.tbody.querySelector('[data-consumo-field="consumo"] .cell-content').textContent, '2 KG');
        env.get('linking-save').click();
        await settled(() => env.get('app').dataset.consumosMode === 'sheet' && env.get('app').getAttribute('aria-busy') === 'false');
        const payload = JSON.parse(env.requests.find(item => item.url.endsWith('/complete')).options.body);
        assert.deepEqual(payload.links.map(link => [link.product_id, link.source_ids]), [[productId, ['source-a']], [otherProductId, ['source-a']], [secondId, ['source-b']]]);
    } finally { env.dom.window.close(); }
});

domTest('printing prepares every sheet in order without persisting editor controls or changing active edits', async () => {
    const pages = twoPages();
    const env = boot({ documentData: reusable({ pages, grid_html: pages[0].grid_html }) });
    try {
        env.window.document.getElementById('btn-edit-mode').click();
        await settled(() => env.get('app').dataset.consumosMode === 'document');
        await tick();
        editCell(env, '[data-consumo-field="product"]', 'Edición pendiente de imprimir');
        const before = env.api.getHtml();
        env.window.dispatchEvent(new env.window.Event('beforeprint'));
        const host = env.get('print-pages');
        assert.equal(host.querySelectorAll('.consumos-print-sheet').length, 2);
        assert.equal(host.querySelectorAll('.consumos-print-grid').length, 2);
        assert.equal(host.querySelector('button,input,select,[contenteditable="true"],[contenteditable="plaintext-only"]'), null);
        assert.match(host.querySelectorAll('.consumos-print-sheet')[0].textContent, /Edición pendiente de imprimir/);
        assert.match(host.querySelectorAll('.consumos-print-sheet')[1].textContent, /Segundo producto/);
        assert.equal(env.api.getHtml(), before);
        assert.equal(env.api.dirty, true);
        env.window.dispatchEvent(new env.window.Event('afterprint'));
        assert.equal(host.children.length, 0);
        assert.equal(env.api.getHtml(), before);
    } finally { env.dom.window.close(); }
});

domTest('a nineteen-page subset preserves and restores mapping drafts with a production-length namespace', async () => {
    const draftNamespace = 'a'.repeat(64);
    const identifier = (prefix, index) => `${prefix}-0000-4000-8000-${String(index + 1).padStart(12, '0')}`;
    const pages = Array.from({ length: 20 }, (_, index) => ({
        id: identifier('30000000', index), title: `Plantilla ${index + 1}`,
        grid_html: html([`Producto ${index + 1}`]).replaceAll(supplierId, identifier('40000000', index)).replaceAll(productId, identifier('50000000', index)),
    }));
    const documentData = reusable({ pages, grid_html: pages[0].grid_html });
    const request = async (url, options) => {
        let result;
        if (url === '/document') result = { document: documentData };
        else if (url === '/reports/7') result = { import: report };
        else if (url.endsWith('/prepare')) {
            const ids = JSON.parse(options.body).page_ids;
            const selected = pages.filter(page => ids.includes(page.id));
            result = { document: documentData, import: report, page_ids: ids,
                sheet: { id: null, version: null, title: documentData.title, import_id: report.id, suppliers: [], pages: selected, grid_html: selected[0].grid_html },
                pending: selected.map(page => ({ page_id: page.id, product_id: identifier('50000000', pages.indexOf(page)), product_name: page.title, supplier_name: 'Proveedor', reason: 'manual-review' })) };
        } else throw new Error(`Unexpected large-subset request: ${url}`);
        return { ok: true, json: async () => result };
    };
    const pointerKey = `mmqp:consumos:${draftNamespace}:pending-import`;
    let storageData;
    const first = boot({ documentData, imports: [report], draftNamespace, request });
    try {
        first.get('import-button').click();
        markImportPage(first, pages[19].id, false);
        first.get('existing-import').value = '7';
        first.get('existing-import').dispatchEvent(new first.window.Event('change', { bubbles: true }));
        first.get('use-existing').click();
        await settled(() => first.get('app').dataset.consumosMode === 'linking' && first.get('app').getAttribute('aria-busy') === 'false');
        assert.equal(first.get('page-select').options.length, 19);
        const skip = first.get('linking-list').querySelector('[data-skip-product]');
        skip.checked = true;
        skip.dispatchEvent(new first.window.Event('change', { bubbles: true }));
        first.window.dispatchEvent(new first.window.Event('pagehide'));
        const keys = Array.from({ length: first.window.localStorage.length }, (_, index) => first.window.localStorage.key(index));
        const mappingKey = keys.find(key => key.includes(':mapping:4:3:7:pages:'));
        assert.ok(mappingKey, 'A valid nineteen-page selection must retain its local mapping draft');
        assert.ok(mappingKey.length <= 512, 'The scoped key must fit the recovery store with the real SHA-256 namespace length');
        const draft = JSON.parse(first.window.localStorage.getItem(mappingKey));
        assert.equal(draft.snapshot.pages.length, 19);
        assert.deepEqual(draft.snapshot.skipped_product_ids, [identifier('50000000', 0)]);
        assert.deepEqual(JSON.parse(first.window.localStorage.getItem(pointerKey)).page_ids, pages.slice(0, 19).map(page => page.id));
        storageData = Object.fromEntries(keys.map(key => [key, first.window.localStorage.getItem(key)]));
    } finally { first.dom.window.close(); }
    const resumed = boot({ documentData, imports: [report], draftNamespace, request, storageData });
    try {
        resumed.get('import-button').click();
        assert.equal(resumed.get('import-pages-list').querySelectorAll('input:checked').length, 20);
        resumed.get('resume-import').click();
        await settled(() => resumed.get('app').dataset.consumosMode === 'linking' && resumed.get('app').getAttribute('aria-busy') === 'false');
        assert.equal(resumed.get('page-select').options.length, 19);
        assert.equal(resumed.get('draft-banner').hidden, false);
        resumed.get('draft-restore').click();
        await settled(() => resumed.get('draft-banner').hidden && resumed.get('app').getAttribute('aria-busy') === 'false');
        assert.equal(resumed.get('linking-list').querySelector('[data-skip-product]').checked, true);
        assert.match(resumed.get('linking-progress').textContent, /1 de 19 revisados/);
    } finally { resumed.dom.window.close(); }
});

domTest('stale resume refreshes saved page choices before importing the current document', async () => {
    const oldPages = twoPages();
    const oldDocument = reusable({ pages: oldPages, grid_html: oldPages[0].grid_html });
    const addedPageId = '20000000-0000-4000-8000-000000000003';
    const addedProductId = '10000000-0000-4000-8000-000000000006';
    const newPages = [
        { ...oldPages[1], title: 'Proveedores actualizados' },
        { id: addedPageId, title: 'Hoja recién añadida', grid_html: html(['Producto nuevo']).replaceAll(productId, addedProductId) },
    ];
    const currentDocument = { ...oldDocument, version: 4, pages: newPages, grid_html: newPages[0].grid_html };
    const pointerKey = 'mmqp:consumos:workflow-test:user-2:pending-import';
    const pointer = JSON.stringify({ document_id: 4, document_version: 3, import_id: 7, review_all: true, page_ids: [pageA] });
    const request = async (url, options) => {
        let result;
        if (url === '/document') result = { document: currentDocument };
        else if (url === '/reports/7') result = { import: report };
        else if (url.endsWith('/prepare')) {
            const ids = JSON.parse(options.body).page_ids;
            const selected = newPages.filter(page => ids.includes(page.id));
            result = { document: currentDocument, import: report, page_ids: ids,
                sheet: { id: null, version: null, title: currentDocument.title, import_id: report.id, suppliers: [], pages: selected, grid_html: selected[0].grid_html },
                pending: selected.map(page => ({ page_id: page.id, product_id: page.id === pageB ? otherProductId : addedProductId, product_name: page.title, supplier_name: 'Proveedor', reason: 'manual-review' })) };
        } else throw new Error(`Unexpected stale-resume request: ${url}`);
        return { ok: true, json: async () => result };
    };
    const env = boot({ documentData: oldDocument, imports: [report], request, storageData: { [pointerKey]: pointer } });
    try {
        env.get('import-button').click();
        assert.match(env.get('import-pages-list').textContent, /Portada/);
        env.get('resume-import').click();
        await settled(() => !env.get('import-error').hidden && env.get('app').getAttribute('aria-busy') === 'false');
        assert.match(env.get('import-error').textContent, /El documento ha cambiado/);
        const choices = [...env.get('import-pages-list').querySelectorAll('input')];
        assert.deepEqual(choices.map(input => [input.value, input.checked]), [[pageB, true], [addedPageId, true]]);
        assert.match(env.get('import-pages-list').textContent, /Proveedores actualizados.*Hoja recién añadida/);
        assert.doesNotMatch(env.get('import-pages-list').textContent, /Portada/);
        assert.match(env.get('import-pages-summary').textContent, /2 de 2.*2 productos/);
        assert.equal(env.get('resume-import').disabled, true);
        assert.equal(env.window.localStorage.getItem(pointerKey), pointer, 'The stale recovery pointer stays intact until explicit discard');
        env.get('resume-discard').click();
        assert.equal(env.get('resume-import-panel').hidden, true);
        env.get('existing-import').value = '7';
        env.get('existing-import').dispatchEvent(new env.window.Event('change', { bubbles: true }));
        env.get('use-existing').click();
        await settled(() => env.get('app').dataset.consumosMode === 'linking' && env.get('app').getAttribute('aria-busy') === 'false');
        const prepared = JSON.parse(env.requests.find(item => item.url.endsWith('/prepare')).options.body);
        assert.equal(prepared.document_version, 4);
        assert.deepEqual(prepared.page_ids, [pageB, addedPageId]);
    } finally { env.dom.window.close(); }
});
