import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { initializeArqueo, sanitizeTemplate, templateGeometry, templateSnapshot, validateImportGeometry } from '../../resources/js/arqueo-editor.js';

let JSDOM;
try { ({ JSDOM } = createRequire(import.meta.url)(process.env.MMQP_JSDOM_MODULE || 'jsdom')); }
catch {
    try { ({ JSDOM } = createRequire(join(process.env.MM_CONSUMOS_DOM_RUNTIME || join(tmpdir(), 'consumos-editor-diagnostic'), 'package.json'))('jsdom')); } catch {}
}
const domTest = (name, callback) => test(name, { skip: !JSDOM && 'A jsdom runtime is required for editor integration.' }, callback);
const geometry = { columns: [15, 35, 50], rows: [20, 30, 40, 186] };
const html = '<tr><td rowspan="2" class="b-top b-left"><div class="cell-content">EFECTIVO</div></td><td colspan="2"><div class="cell-content">INGRESOS</div></td></tr><tr><td><div class="cell-content">Caja</div></td><td><div class="cell-content"></div></td></tr><tr><td><div class="cell-content">Tarjeta</div></td><td><div class="cell-content"></div></td><td><div class="cell-content"></div></td></tr><tr><td colspan="3"><div class="cell-content">Observaciones</div></td></tr>';
const options = { enableImportGeometry: true };

function boot(config = {}) {
    const dom = new JSDOM('<!doctype html><html><body><div class="arqueo-column-labels"></div><div id="sheet-row-labels"></div><div id="sheet-overflow-warning"></div><div id="editor-status"></div><table id="arqueo-grid"><tbody></tbody></table></body></html>', { pretendToBeVisual: true });
    return { dom, api: initializeArqueo({ ...options, ...config }, dom.window.document), document: dom.window.document };
}

test('imported grid proportions require bounded positive numbers and matching logical dimensions', () => {
    assert.deepEqual(validateImportGeometry(geometry, { width: 3, height: 4 }), geometry);
    for (const invalid of [null, {}, { ...geometry, columns: [15, 35, 49] }, { ...geometry, rows: [0, 30, 40, 206] }, { ...geometry, columns: ['15', 35, 50] }, { ...geometry, rows: [20, Infinity, 40, 186] }, { columns: Array(101).fill(100 / 101), rows: [276] }]) assert.equal(validateImportGeometry(invalid), null);
    assert.equal(validateImportGeometry(geometry, { width: 4, height: 4 }), null);
    assert.equal(validateImportGeometry(geometry, { width: 3, height: 5 }), null);
});

domTest('import retains unequal editable rows and columns through undo, redo, save and reload', async () => {
    let saved;
    const env = boot({ onSave: value => { saved = value; } });
    let reopened;
    try {
        const original = env.api.snapshot();
        env.api.loadHtml(html, { geometry });
        assert.equal(env.api.dirty, true);
        assert.deepEqual(templateGeometry(env.api.tbody, options), geometry);
        assert.equal(env.api.getLayout().width, 3);
        assert.equal(env.api.tbody.rows.length, 4);
        assert.deepEqual([...env.document.querySelectorAll('col')].map(col => col.style.width), ['15%', '35%', '50%']);
        assert.deepEqual([...env.api.tbody.rows].map(row => row.style.height), ['20mm', '30mm', '40mm', '186mm']);
        assert.equal(env.api.tbody.rows[0].cells[0].style.getPropertyValue('--cell-height'), '50mm');
        assert.equal(env.document.querySelector('.arqueo-column-labels').textContent, 'ABC');
        assert.equal(env.document.getElementById('sheet-row-labels').style.gridTemplateRows, '20mm 30mm 40mm 186mm');
        assert.ok([...env.api.tbody.querySelectorAll('.cell-content')].every(cell => cell.getAttribute('contenteditable') === 'plaintext-only'));
        const imported = env.api.snapshot();
        assert.ok(!imported.includes('--cell-height') && !imported.includes('height:'));
        env.api.undo();
        assert.equal(env.api.snapshot(), original);
        assert.equal(env.api.getLayout().width, 24);
        assert.equal(env.api.tbody.rows.length, 73);
        assert.equal(env.api.table.hasAttribute('data-import-geometry'), false);
        assert.equal(env.document.querySelectorAll('col').length, 24);
        env.api.redo();
        assert.equal(env.api.snapshot(), imported);
        assert.equal(env.api.tbody.rows[0].cells[0].style.getPropertyValue('--cell-height'), '50mm');
        await env.api.saveTemplate();
        assert.equal(saved, imported);
        assert.equal(env.api.dirty, false);
        reopened = boot({ templateData: { html: saved } });
        assert.deepEqual(templateGeometry(reopened.api.tbody, options), geometry);
        assert.equal(reopened.api.getLayout().width, 3);
        assert.equal(reopened.api.tbody.rows.length, 4);
        assert.equal(reopened.api.snapshot(), saved);
        assert.equal(reopened.api.dirty, false);
    } finally { reopened?.dom.window.close(); env.dom.window.close(); }
});

domTest('splitting an imported merged cell derives each child height from its original row', () => {
    const env = boot({ confirmClear: false });
    try {
        env.api.loadHtml(html, { geometry });
        const imported = env.api.snapshot();
        env.dom.window.toggleEditMode();
        env.api.selectCell(env.api.tbody.rows[0].cells[0]);
        env.dom.window.cmd('unmerge');
        assert.equal(env.api.tbody.rows[0].cells[0].style.getPropertyValue('--cell-height'), '20mm');
        assert.equal(env.api.tbody.rows[1].cells[0].style.getPropertyValue('--cell-height'), '30mm');
        assert.deepEqual(templateGeometry(env.api.tbody, options), geometry);
        env.api.undo();
        assert.equal(env.api.snapshot(), imported);
        env.dom.window.clearGrid();
        assert.equal(templateGeometry(env.api.tbody, options), null);
        assert.equal(env.api.getLayout().width, 24);
        env.api.undo();
        assert.equal(env.api.snapshot(), imported);
    } finally { env.dom.window.close(); }
});

domTest('invalid import proportions leave the current document intact', () => {
    const env = boot();
    try {
        const before = env.api.snapshot();
        assert.throws(() => env.api.loadHtml(html, { geometry: { ...geometry, columns: [40, 60] } }), /estructura/);
        assert.equal(env.api.snapshot(), before);
        assert.equal(env.api.dirty, false);
    } finally { env.dom.window.close(); }
});

domTest('three wide imported columns can bind an employee using their actual widths', () => {
    const env = boot({ empleadosList: [{ id: 7, nombre: 'ANA' }], empleadosDia: { 7: { user: { nombre: 'ANA' }, manana: '08:00-14:00', noche: '18:00-22:00' } } });
    try {
        env.document.body.insertAdjacentHTML('beforeend', '<div id="bind-modal" hidden><select id="bind-emp"><option value=""></option><option value="7">ANA</option></select><p id="bind-error" hidden></p></div>');
        const proportions = { columns: [40, 30, 30], rows: [276] };
        env.api.loadHtml(`<tr>${'<td><div class="cell-content"></div></td>'.repeat(3)}</tr>`, { geometry: proportions });
        env.dom.window.toggleEditMode();
        env.api.selectCell(env.api.tbody.rows[0].cells[0]);
        env.dom.window.openBindModal();
        env.document.getElementById('bind-emp').value = '7';
        env.dom.window.applyBind();
        assert.deepEqual([...env.api.tbody.rows[0].cells].map(cell => cell.dataset.boundProp), ['nombre', 'manana', 'noche']);
        assert.deepEqual([...env.api.tbody.rows[0].cells].map(cell => cell.querySelector('.cell-content').textContent), ['ANA', '08:00-14:00', '18:00-22:00']);
        assert.deepEqual(templateGeometry(env.api.tbody, options), proportions);
        assert.equal(env.document.getElementById('bind-error').hidden, true);
    } finally { env.dom.window.close(); }
});

domTest('geometry metadata is opt-in and Consumos keeps its existing HTML contract', () => {
    const dom = new JSDOM('<!doctype html>');
    try {
        const imported = sanitizeTemplate(html, dom.window.document, options);
        imported.rows[0].dataset.arqueoColumns = JSON.stringify(geometry.columns);
        imported.rows[0].dataset.arqueoRows = JSON.stringify(geometry.rows);
        imported.rows[1].cells[0].dataset.consumoProduct = 'product-1';
        const consumosOptions = { cellAttributes: ['data-consumo-product'] };
        const consumos = sanitizeTemplate(imported.innerHTML, dom.window.document, consumosOptions);
        assert.equal(consumos.rows[0].hasAttribute('data-arqueo-columns'), false);
        assert.equal(consumos.rows[0].hasAttribute('data-arqueo-rows'), false);
        assert.equal(consumos.rows[1].cells[0].dataset.consumoProduct, 'product-1');
        assert.equal(templateGeometry(consumos, consumosOptions), null);
        assert.equal(typeof templateSnapshot(consumos, consumosOptions), 'string');
        imported.rows[0].dataset.arqueoRows = '[276]';
        const invalid = sanitizeTemplate(imported.innerHTML, dom.window.document, options);
        assert.equal(invalid.rows[0].hasAttribute('data-arqueo-columns'), false);
        assert.equal(invalid.rows[0].hasAttribute('data-arqueo-rows'), false);
    } finally { dom.window.close(); }
});

domTest('an imported twelve-column form does not trigger legacy doubling', () => {
    const dom = new JSDOM('<!doctype html>');
    let env;
    try {
        const body = sanitizeTemplate(`<tr>${'<td><div class="cell-content">Texto</div></td>'.repeat(12)}</tr>`, dom.window.document, options);
        body.rows[0].dataset.arqueoColumns = JSON.stringify(Array(12).fill(100 / 12));
        body.rows[0].dataset.arqueoRows = '[276]';
        env = boot({ templateData: { html: body.innerHTML } });
        assert.equal(env.api.getLayout().width, 12);
        assert.equal(env.api.tbody.rows.length, 1);
    } finally { env?.dom.window.close(); dom.window.close(); }
});

domTest('imported headings and captions can both change text size without losing their hierarchy', () => {
    const env = boot();
    try {
        const mixed = html.replace('<div class="cell-content">EFECTIVO</div>', '<div class="cell-content" style="font-size: 12px"><span style="font-size: 24px">EFECTIVO</span><br><span style="font-size: 10px">Resumen</span></div>');
        env.api.loadHtml(mixed, { geometry });
        env.dom.window.toggleEditMode();
        env.api.selectCell(env.api.tbody.rows[0].cells[0]);
        const before = env.api.snapshot();
        env.dom.window.cmd('font-up');
        let content = env.api.tbody.rows[0].cells[0].querySelector('.cell-content');
        assert.equal(content.style.fontSize, '13px');
        assert.deepEqual([...content.querySelectorAll('span')].map(node => node.style.fontSize), ['25px', '11px']);
        env.dom.window.cmd('font-down');
        assert.equal(env.api.snapshot(), before);
        env.api.undo();
        content = env.api.tbody.rows[0].cells[0].querySelector('.cell-content');
        assert.deepEqual([...content.querySelectorAll('span')].map(node => node.style.fontSize), ['25px', '11px']);
    } finally { env.dom.window.close(); }
});

domTest('import spacing survives snapshot, save and reload while unsafe and ordinary-sheet spacing is stripped', async () => {
    let saved;
    const env = boot({ onSave: value => { saved = value; } });
    let reopened;
    try {
        const spaced = html.replace('<td rowspan="2" class="b-top b-left">', '<td rowspan="2" class="b-top b-left" style="padding: 0px 4.5px 8px 1024px; line-height: .5;">')
            .replace('<div class="cell-content">EFECTIVO</div>', '<div class="cell-content" style="padding: 12.25px 6px 0px 18px; line-height: 1.2;"><span style="padding-left: 1025px; padding-right: 2em; padding-top: calc(1px + 1px); padding-bottom: var(--unsafe); line-height: 5;">EFECTIVO</span></div>');
        env.api.loadHtml(spaced, { geometry });
        const cell = env.api.tbody.rows[0].cells[0];
        const content = cell.querySelector('.cell-content');
        assert.equal(cell.style.paddingLeft, '1024px');
        assert.equal(cell.style.paddingRight, '4.5px');
        assert.equal(cell.style.lineHeight, '0.5');
        assert.equal(content.style.paddingTop, '12.25px');
        assert.equal(content.style.paddingLeft, '18px');
        assert.equal(content.style.lineHeight, '1.2');
        const unsafe = content.querySelector('span');
        for (const property of ['paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'lineHeight']) assert.equal(unsafe.style[property], '');
        const imported = env.api.snapshot();
        await env.api.saveTemplate();
        assert.equal(saved, imported);
        reopened = boot({ templateData: { html: saved } });
        assert.equal(reopened.api.snapshot(), imported);
        assert.equal(reopened.api.tbody.rows[0].cells[0].querySelector('.cell-content').style.paddingTop, '12.25px');
        const ordinary = sanitizeTemplate(saved, env.document);
        for (const node of ordinary.querySelectorAll('td, .cell-content, span')) {
            for (const property of ['paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'lineHeight']) assert.equal(node.style[property], '');
        }
        const tooLow = sanitizeTemplate('<tr><td style="line-height: .49;"><div class="cell-content" style="line-height: 1px;">Texto</div></td></tr>', env.document, options);
        assert.equal(tooLow.rows[0].cells[0].style.lineHeight, '');
        assert.equal(tooLow.querySelector('.cell-content').style.lineHeight, '');
    } finally { reopened?.dom.window.close(); env.dom.window.close(); }
});
