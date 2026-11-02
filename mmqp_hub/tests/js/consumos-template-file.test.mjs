import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { randomUUID } from 'node:crypto';
import { createConsumoTemplateFile, readConsumoTemplateFile } from '../../resources/js/consumos-template-file.js';
import { applyBlocks, blankGrid, PRODUCT_FIELDS, readSuppliers } from '../../resources/js/consumos-grid.js';
import { buildGridMap } from '../../resources/js/arqueo-editor.js';
import { planSideBlock } from '../../resources/js/consumos-side-grid.js';
import { planOrderBlock } from '../../resources/js/consumos-order-grid.js';

let JSDOM;
try { ({ JSDOM } = createRequire(import.meta.url)(process.env.MMQP_JSDOM_MODULE || 'jsdom')); }
catch { try { ({ JSDOM } = createRequire(new URL('../../output/test-dependencies/package.json', import.meta.url))('jsdom')); } catch {} }
const domTest = (name, callback) => test(name, { skip: !JSDOM && 'A jsdom runtime is required for template integration.' }, callback);
const dom = JSDOM ? new JSDOM('') : null;
const document = dom?.window.document;
const bodyFor = html => { const body = document.createElement('tbody'); body.innerHTML = html; return body; };

function buildSampleGrid(blocks = [{ name: 'PROVEEDOR A', row: 2, count: 4 }, { name: 'PROVEEDOR B', row: 14, count: 3 }]) {
    const body = bodyFor(blankGrid(document));
    for (const block of blocks) {
        const layout = buildGridMap(body.rows);
        const planned = planSideBlock(layout, { minR: block.row, maxR: block.row, minC: 0, maxC: 11 }, block.count);
        const supplierId = randomUUID();
        if (planned.cornerPlan) applyBlocks([planned.cornerPlan]);
        const provider = applyBlocks([planned.providerPlan])[0];
        provider.dataset.consumoProvider = supplierId;
        provider.querySelector('.cell-content').textContent = block.name;
        applyBlocks(planned.headerPlans).forEach((cell, idx) => {
            cell.querySelector('.cell-content').textContent = ['PRODUCTO', 'CONSUMO', 'STOCK', 'PEDIDO'][idx];
        });
        planned.rows.forEach((row, rIdx) => {
            const productId = randomUUID();
            const cells = applyBlocks(row.plans);
            cells.forEach((cell, fIdx) => {
                cell.dataset.consumoProduct = productId;
                cell.dataset.consumoSupplier = supplierId;
                cell.dataset.consumoField = PRODUCT_FIELDS[fIdx];
                if (fIdx === 0) cell.querySelector('.cell-content').textContent = `${block.name} Prod ${rIdx + 1}`;
                if (fIdx === 1) cell.dataset.consumoSources = '[]';
            });
        });
    }
    return body.innerHTML;
}

const template = () => ({ title: 'Plantilla de proveedores', pages: [
    { id: randomUUID(), title: 'Proveedores principales', grid_html: buildSampleGrid() },
] });
const suppliesTemplate = () => ({ title: 'Suministros', pages: [
    { id: randomUUID(), title: 'Suministros', grid_html: buildSampleGrid([
        { name: 'PROV 1', row: 2, count: 3 },
        { name: 'PROV 2', row: 12, count: 2 },
        { name: 'PROV 3', row: 20, count: 2 },
    ]) },
] });

domTest('portable template preserves one sheet, labels, borders and formatting without operational data', () => {
    const data = template();
    const body = bodyFor(data.pages[0].grid_html);
    body.querySelector('[data-consumo-field="consumo"]').dataset.consumoSources = '["private-pdf-row"]';
    for (const cell of body.querySelectorAll('[data-consumo-field]:not([data-consumo-field="product"])')) cell.querySelector('.cell-content').textContent = '123 private amount';
    body.querySelector('[data-consumo-field="stock"] .cell-content').style.backgroundColor = 'red';
    data.pages[0].grid_html = body.innerHTML;
    const text = createConsumoTemplateFile({ ...data, import_id: 11, version: 22, suppliers: ['must not travel'] }, document);
    assert(!text.includes('private'));
    assert(!text.includes('import_id'));
    assert(!text.includes('must not travel'));
    const clean = JSON.parse(text).template;
    assert.deepEqual(clean.pages.map(page => page.title), data.pages.map(page => page.title));
    const cleanBody = bodyFor(clean.pages[0].grid_html);
    assert.equal(cleanBody.querySelector('[data-consumo-field="stock"] .cell-content').style.backgroundColor, 'red');
    assert.equal(cleanBody.querySelectorAll('[data-consumo-field="product"]').length, 7);
    assert.equal(cleanBody.querySelector('[data-consumo-provider]').className, body.querySelector('[data-consumo-provider]').className);
});

domTest('import generates independent identities and retains product to supplier relationships on one sheet', () => {
    const original = suppliesTemplate();
    const text = createConsumoTemplateFile(original, document);
    const imported = readConsumoTemplateFile(text, document);
    const oldIds = new Set(original.pages.flatMap(page => [page.id, ...readSuppliers(bodyFor(page.grid_html)).flatMap(supplier => [supplier.id, ...supplier.products.map(product => product.id)])]));
    for (const page of imported.pages) {
        assert(!oldIds.has(page.id));
        for (const supplier of readSuppliers(bodyFor(page.grid_html))) {
            assert(!oldIds.has(supplier.id));
            for (const product of supplier.products) { assert(!oldIds.has(product.id)); assert.deepEqual(product.source_ids, []); assert.equal(product.stock, ''); assert.equal(product.pedido, ''); }
        }
    }
    assert.deepEqual(readSuppliers(bodyFor(imported.pages[0].grid_html)).map(supplier => [supplier.name, supplier.products.length]), [['PROV 1', 3], ['PROV 2', 2], ['PROV 3', 2]]);
});

domTest('rejects foreign versions, executable HTML, broken geometry, duplicate identities and missing fields atomically', () => {
    const data = template();
    const serialized = createConsumoTemplateFile(data, document);
    for (const input of ['{', serialized.replace('mmqp.consumos-template', 'mmqp.arqueo-template'), serialized.replace('"version":1', '"version":9')]) assert.throws(() => readConsumoTemplateFile(input, document));
    const corruptions = [
        html => html + '<script>alert(1)</script>',
        html => html.replace('<td', '<td onclick="alert(1)"'),
        html => html.replace('<td', '<td style="background:url(https://example.com)"'),
        html => html.replace('</td>', ''),
        html => html.replace(/colspan="\d+"/, 'colspan="99"'),
        html => html.replace(/ data-consumo-field="product"/, ''),
    ];
    for (const [index, corrupt] of corruptions.entries()) {
        const brokenHtml = corrupt(data.pages[0].grid_html);
        assert.notEqual(brokenHtml, data.pages[0].grid_html, `Corruption ${index} must actually change the fixture`);
        assert.throws(() => createConsumoTemplateFile({ ...data, pages: [{ ...data.pages[0], grid_html: brokenHtml }] }, document));
    }
    assert.throws(() => createConsumoTemplateFile({ ...data, pages: [data.pages[0], { ...data.pages[0], id: randomUUID() }] }, document));
    assert.throws(() => createConsumoTemplateFile({ ...data, pages: Array(21).fill(data.pages[0]) }, document));
    const multiple = { ...data, pages: [data.pages[0], suppliesTemplate().pages[0]] };
    assert.throws(() => createConsumoTemplateFile(multiple, document), /una sola hoja/);
    assert.throws(() => readConsumoTemplateFile(JSON.stringify({ format: 'mmqp.consumos-template', version: 1, template: multiple }), document), /una sola hoja/);
});

domTest('untyped packaging stock and order values are cleared while product names survive', () => {
    const data = suppliesTemplate();
    const body = bodyFor(data.pages[0].grid_html);
    // Free grid at the right bottom; one header row and a packaging row below it.
    const map = buildGridMap(body.rows).map;
    ['PRODUCTO', 'STOCK', 'PEDIDO'].forEach((text, index) => { map[68][index + 14].textContent = text; });
    ['BOLSA ROLLING', '999 packaging stock', '888 packaging order'].forEach((text, index) => {
        const cell = map[69][index + 14]; cell.textContent = text; cell.classList.add('b-bottom');
    });
    data.pages[0].grid_html = body.innerHTML;
    const clean = createConsumoTemplateFile(data, document);
    assert(clean.includes('BOLSA ROLLING'));
    assert(!clean.includes('packaging stock'));
    assert(!clean.includes('packaging order'));
});

domTest('cleaning ordering columns stops at the next table and preserves headers and notes on repeated export', () => {
    const body = bodyFor(buildSampleGrid([{ name: 'PROV PEDIDOS', row: 20, count: 2 }]));
    const orderBlock = planOrderBlock(buildGridMap(body.rows), { minR: 1, maxR: 1, minC: 0, maxC: 23 }, ['Proveedor 1', 'Proveedor 2']);
    applyBlocks(orderBlock.headerPlans).forEach((cell, idx) => {
        cell.querySelector('.cell-content').textContent = ['PROVEEDOR', '¿QUÉ PEDIR?', '¿HECHO?', '¿CUÁNDO RECIBIMOS?'][idx];
    });
    orderBlock.rows.forEach(row => {
        const cells = applyBlocks(row.plans);
        cells[0].querySelector('.cell-content').textContent = row.name;
        cells[1].querySelector('.cell-content').textContent = 'Nota libre de pedido';
    });
    const data = { title: 'Pedidos', pages: [{ id: randomUUID(), title: 'Portada', grid_html: body.innerHTML }] };
    const originalText = [...body.querySelectorAll('td')].map(cell => cell.textContent);
    const first = createConsumoTemplateFile(data, document);
    const result = JSON.parse(first).template;
    assert.deepEqual([...bodyFor(result.pages[0].grid_html).querySelectorAll('td')].map(cell => cell.textContent), originalText);
    assert.equal(createConsumoTemplateFile(result, document), first, 'Repeated exports must be stable');
    assert.equal(bodyFor(result.pages[0].grid_html).textContent.match(/Nota libre de pedido/g).length, 2);
});
