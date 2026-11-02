import test from 'node:test';
import assert from 'node:assert/strict';
import { planProductRows, PRODUCT_FIELDS } from '../../resources/js/consumos-grid.js';

function cell(text = '', attributes = {}) {
    const content = { textContent: text, innerText: text };
    return {
        content, attributes: new Map(Object.entries(attributes)),
        querySelector: selector => selector === '.cell-content' ? content : null,
        getAttribute(name) { return this.attributes.get(name) ?? null; },
        hasAttribute(name) { return this.attributes.has(name); },
    };
}

function fixture({ height = 30, width = 24, col = 2, headerRow = 2, widths = [5, 3, 2, 2], note = false, extra = 0, blankProductLabel = false } = {}) {
    const layout = { width, height, map: [], bounds: new Map() };
    for (let row = 0; row < height; row++) {
        layout.map[row] = [];
        for (let c = 0; c < width; c++) {
            const item = cell();
            layout.map[row][c] = item;
            layout.bounds.set(item, { minR: row, maxR: row, minC: c, maxC: c });
        }
    }
    const place = (row, c, w, h, text = '', attributes = {}) => {
        const item = cell(text, attributes);
        for (let r = row; r < row + h; r++) for (let cc = c; cc < c + w; cc++) {
            layout.bounds.delete(layout.map[r][cc]);
            layout.map[r][cc] = item;
        }
        layout.bounds.set(item, { minR: row, maxR: row + h - 1, minC: c, maxC: c + w - 1 });
        return item;
    };
    const bodyWidth = widths.reduce((sum, value) => sum + value, 0);
    const header = place(headerRow, col, bodyWidth + extra, 2, 'MARMEN', { 'data-consumo-provider': 'supplier-a' });
    let labelRow = headerRow + 2;
    if (note) { place(labelRow, col, bodyWidth + extra, 2, '*Cuando se recibe, escribir en el albarán'); labelRow += 2; }
    let c = col;
    for (let index = 0; index < widths.length; index++) {
        const label = index === 0 && blankProductLabel ? '' : ['PRODUCTO', 'CONSUMO', 'STOCK', 'PEDIDO'][index];
        place(labelRow, c, widths[index], 1, label);
        c += widths[index];
    }
    if (extra) { place(labelRow, c, 1, 1, 'M'); place(labelRow, c + 1, 1, 1, 'V'); }
    const product = (row, h = 2, id = 'product-a', supplier = 'supplier-a') => {
        const fields = [];
        let c = col;
        widths.forEach((w, index) => {
            const field = PRODUCT_FIELDS[index];
            const attributes = { 'data-consumo-product': id, 'data-consumo-supplier': supplier, 'data-consumo-field': field };
            if (field === 'consumo') attributes['data-consumo-sources'] = '["pdf-zero","pdf-duplicate-name"]';
            fields.push(place(row, c, w, h, ['Barbacoa', '36,124 Kg', '7 cajas', '2'][index], attributes));
            c += w;
        });
        return fields;
    };
    return { layout, header, place, product, firstRow: labelRow + 1, col, widths };
}

const snapshot = layout => JSON.stringify([...layout.bounds].map(([item, bounds]) => ({ bounds, text: item.content.textContent, attributes: [...item.attributes] })));
const target = (layout, row, col) => layout.bounds.get(layout.map[row][col]);

test('one click in any column prepares every complete product row from the labels to that cell', () => {
    for (const col of [2, 6, 8, 10, 12, 13]) {
        const { layout, header } = fixture();
        const before = snapshot(layout);
        const result = planProductRows(layout, header, target(layout, 16, col));
        assert.deepEqual(result.rows.map(row => [row.row, row.height]), [[5, 2], [7, 2], [9, 2], [11, 2], [13, 2], [15, 2]]);
        assert.equal(result.targetRow, 15);
        assert.deepEqual(result.bounds, { minR: 5, maxR: 16, minC: 2, maxC: 13 });
        assert(result.rows.every(row => row.plans.length === 4 && row.name === ''));
        assert.equal(snapshot(layout), before, 'Planning must not modify any cell');
    }
});

test('blank photographed Product labels supply their original widths instead of redividing the provider', () => {
    const { layout, header } = fixture({ widths: [4, 3, 2, 2], blankProductLabel: true });
    const result = planProductRows(layout, header, target(layout, 10, 11));
    assert.deepEqual(result.rows[0].plans.map(plan => plan.maxC - plan.minC + 1), [4, 3, 2, 2]);
});

test('receiving instructions remain untouched and extra M/V columns cannot become product fields', () => {
    const { layout, header, col } = fixture({ widths: [3, 2, 2, 2], note: true, extra: 2 });
    const before = snapshot(layout);
    const result = planProductRows(layout, header, target(layout, 10, 10));
    assert.equal(result.bounds.minR, 7);
    assert.equal(result.bounds.maxC, col + 8);
    assert.deepEqual(result.rows[0].plans.map(plan => plan.maxC - plan.minC + 1), [3, 2, 2, 2]);
    assert.throws(() => planProductRows(layout, header, target(layout, 10, 11)), /Producto, Consumo, Stock o Pedido/);
    assert.equal(snapshot(layout), before);
});

test('all named product rows retain their names and selected text can come from another column', () => {
    const { layout, header } = fixture();
    layout.map[5][2].content.innerText = 'Alitas';
    layout.map[9][2].content.innerText = 'Kebab';
    const selected = layout.map[12][11]; selected.content.innerText = 'BBQ';
    const result = planProductRows(layout, header, target(layout, 12, 11), { namedCell: selected });
    assert.deepEqual(result.rows.map(row => row.name), ['Alitas', '', 'Kebab', 'BBQ']);
    assert.equal(result.rows.at(-1).plans[2].cells.has(selected), true);
});

test('complete products keep their exact field references, stock, order, ID and source links', () => {
    const { layout, header, product } = fixture();
    const old = product(9);
    const before = snapshot(layout);
    const result = planProductRows(layout, header, target(layout, 14, 12));
    assert.deepEqual(result.rows.map(row => row.row), [5, 7, 11, 13]);
    assert.deepEqual(result.preserved, [{ row: 9, height: 2, id: 'product-a' }]);
    for (const row of result.rows) for (const plan of row.plans) for (const part of old) assert.equal(plan.cells.has(part), false);
    assert.equal(old[1].getAttribute('data-consumo-sources'), '["pdf-zero","pdf-duplicate-name"]');
    assert.equal(snapshot(layout), before);
});

test('isolated old products of varying heights are preserved and odd gaps receive a single-row tail', () => {
    for (const oldHeight of [1, 2, 3]) {
        const { layout, header, product } = fixture();
        product(10, oldHeight);
        const result = planProductRows(layout, header, target(layout, 10 + oldHeight + 1, 12));
        assert.deepEqual(result.rows.slice(0, 3).map(row => [row.row, row.height]), [[5, 2], [7, 2], [9, 1]]);
        assert.deepEqual(result.preserved, [{ row: 10, height: oldHeight, id: 'product-a' }]);
        assert.equal(result.rows[3].row, 10 + oldHeight);
    }
});

test('an existing last product still permits filling all earlier gaps without rewriting that product', () => {
    const { layout, header, product } = fixture();
    const old = product(11, 3);
    const before = snapshot(layout);
    const result = planProductRows(layout, header, target(layout, 12, 8), { namedCell: old[1] });
    assert.deepEqual(result.rows.map(row => row.row), [5, 7, 9]);
    assert.equal(result.targetRow, 11);
    assert.equal(result.bounds.maxR, 13);
    assert.equal(snapshot(layout), before);
});

test('a distant occupied stock cell rejects every planned row atomically', () => {
    const { layout, header } = fixture();
    layout.map[14][10].content.innerText = 'Conservar esta nota';
    const before = snapshot(layout);
    assert.throws(() => planProductRows(layout, header, target(layout, 16, 13)), /Hay texto/);
    assert.equal(snapshot(layout), before);
});

test('a section title or merge across product boundaries cannot be replaced by the batch', () => {
    for (const crossing of ['section', 'horizontal', 'vertical']) {
        const { layout, header, place } = fixture();
        if (crossing === 'section') place(11, 2, 12, 1, 'CONGELADO');
        if (crossing === 'horizontal') place(11, 6, 3, 2);
        if (crossing === 'vertical') place(10, 2, 5, 2);
        const before = snapshot(layout);
        assert.throws(() => planProductRows(layout, header, target(layout, 16, 13)), /casilla unida/);
        assert.equal(snapshot(layout), before);
    }
});

test('raw conflicting product names cannot overwrite each other when typed rows are built', () => {
    const { layout, header } = fixture();
    layout.map[11][2].content.innerText = 'Alitas';
    const selected = layout.map[12][10]; selected.content.innerText = 'BBQ';
    const before = snapshot(layout);
    assert.throws(() => planProductRows(layout, header, target(layout, 12, 10), { namedCell: selected }), /textos distintos/);
    assert.equal(snapshot(layout), before);
});

test('incomplete, foreign, duplicated or corrupt product metadata rejects the complete batch', () => {
    for (const variant of ['missing-field', 'foreign-provider', 'wrong-source-field', 'bad-source', 'duplicate-id', 'employee']) {
        const { layout, header, product } = fixture();
        const old = product(11);
        if (variant === 'missing-field') old[3].attributes.delete('data-consumo-field');
        if (variant === 'foreign-provider') old[1].attributes.set('data-consumo-supplier', 'supplier-b');
        if (variant === 'wrong-source-field') old[0].attributes.set('data-consumo-sources', '["source"]');
        if (variant === 'bad-source') old[1].attributes.set('data-consumo-sources', '[9]');
        if (variant === 'duplicate-id') product(20, 2, 'product-a');
        if (variant === 'employee') old[0].attributes.set('data-bound-emp', 'emp-1');
        const before = snapshot(layout);
        assert.throws(() => planProductRows(layout, header, target(layout, 16, 13)));
        assert.equal(snapshot(layout), before, variant);
    }
});

test('another provider in the requested body is protected and side-by-side blocks remain independent', () => {
    const { layout, header, place } = fixture();
    const beside = place(11, 14, 10, 2, 'CENTRAL PIZZA', { 'data-consumo-provider': 'supplier-b' });
    assert.equal(planProductRows(layout, header, target(layout, 16, 13)).rows.length, 6);
    assert.equal(beside.content.innerText, 'CENTRAL PIZZA');
    place(11, 2, 12, 2, 'OTRO PROVEEDOR', { 'data-consumo-provider': 'supplier-c' });
    const before = snapshot(layout);
    assert.throws(() => planProductRows(layout, header, target(layout, 16, 13)), /ocupadas|producto incompleto/);
    assert.equal(snapshot(layout), before);
});

test('header labels, outside columns and insufficient final space fail without partial rows', () => {
    const { layout, header } = fixture({ height: 20 });
    const before = snapshot(layout);
    assert.throws(() => planProductRows(layout, header, target(layout, 4, 8)), /debajo de Producto/);
    assert.throws(() => planProductRows(layout, header, target(layout, 8, 1)), /ancho/);
    assert.throws(() => planProductRows(layout, header, target(layout, 19, 8)), /casillas suficientes/);
    assert.throws(() => planProductRows(layout, header, { minR: 22, maxR: 22, minC: 8, maxC: 8 }), /casillas suficientes/);
    assert.equal(snapshot(layout), before);
});

test('unknown label layouts are rejected rather than guessing where editable rows begin', () => {
    const { layout, header } = fixture();
    layout.map[4][7].content.innerText = 'OTRA COLUMNA';
    const before = snapshot(layout);
    assert.throws(() => planProductRows(layout, header, target(layout, 12, 8)), /conservar sus encabezados/);
    assert.equal(snapshot(layout), before);
});
