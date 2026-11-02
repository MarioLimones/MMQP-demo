import test from 'node:test';
import assert from 'node:assert/strict';
import { planSideBlock, planSideBlockExtension, readSideBlock } from '../../resources/js/consumos-side-grid.js';
import { CONSUMO_ATTRIBUTES, PRODUCT_FIELDS } from '../../resources/js/consumos-grid.js';

function cell(text = '', attributes = {}) {
    const content = { textContent: text, innerText: text };
    return {
        content, attributes: new Map(Object.entries(attributes)),
        querySelector: selector => selector === '.cell-content' ? content : null,
        getAttribute(name) { return this.attributes.get(name) ?? null; },
        hasAttribute(name) { return this.attributes.has(name); },
    };
}

function fixture({ height = 73, width = 24 } = {}) {
    const layout = { width, height, map: [], bounds: new Map() };
    for (let row = 0; row < height; row++) {
        layout.map[row] = [];
        for (let col = 0; col < width; col++) {
            const item = cell(); layout.map[row][col] = item;
            layout.bounds.set(item, { minR: row, maxR: row, minC: col, maxC: col });
        }
    }
    const place = (bounds, text = '', attributes = {}, retained) => {
        const item = retained || cell(text, attributes);
        for (let row = bounds.minR; row <= bounds.maxR; row++) for (let col = bounds.minC; col <= bounds.maxC; col++) {
            layout.bounds.delete(layout.map[row][col]); layout.map[row][col] = item;
        }
        layout.bounds.set(item, { minR: bounds.minR, maxR: bounds.maxR, minC: bounds.minC, maxC: bounds.maxC });
        return item;
    };
    const create = (start, count, options) => {
        const result = planSideBlock(layout, start, count, options);
        if (result.cornerPlan) place(result.cornerPlan);
        const provider = place(result.providerPlan, 'CERVEZA', { 'data-consumo-provider': 'supplier-a' });
        result.headerPlans.forEach((plan, index) => place(plan, ['PRODUCTO', 'CONSUMO', 'STOCK', 'PEDIDO'][index]));
        const products = result.rows.map((row, index) => row.plans.map((plan, fieldIndex) => {
            const field = PRODUCT_FIELDS[fieldIndex];
            const attributes = { 'data-consumo-product': `product-${index}`, 'data-consumo-supplier': 'supplier-a', 'data-consumo-field': field };
            if (field === 'consumo') attributes['data-consumo-sources'] = index === 0 ? '["pdf-zero","pdf-same-name"]' : '[]';
            return place(plan, index === 0 ? ['Lata Amstel', '146 Uds.', '3 cajas', '2 cajas'][fieldIndex] : '', attributes);
        }));
        return { result, provider, products };
    };
    return { layout, place, create };
}

const at = (row, col, width = 1) => ({ minR: row, maxR: row, minC: col, maxC: col + width - 1 });
const snapshot = layout => JSON.stringify([...layout.bounds].map(([item, bounds]) => ({ bounds, text: item.content.innerText, attributes: [...item.attributes] })));
const positions = plan => {
    const positions = [];
    for (let row = plan.minR; row <= plan.maxR; row++) for (let col = plan.minC; col <= plan.maxC; col++) positions.push(`${row}:${col}`);
    return positions;
};

test('a photo-style block places one tall provider beside five or seven complete editable rows', () => {
    for (const count of [5, 7]) {
        const { layout } = fixture(); const before = snapshot(layout);
        const result = planSideBlock(layout, at(4, 0), count);
        assert.deepEqual(result.providerPlan, { minR: 5, maxR: 4 + count * 2, minC: 0, maxC: 6, cells: result.providerPlan.cells, first: layout.map[5][0] });
        assert.deepEqual(result.widths, [7, 4, 3, 3]);
        assert.deepEqual(result.headerPlans.map(plan => [plan.minR, plan.maxR]), Array.from({ length: 4 }, () => [4, 4]));
        assert.equal(result.rows.length, count);
        assert(result.rows.every(row => row.height === 2 && row.name === '' && row.plans.length === 4));
        const occupied = new Set();
        for (const plan of result.plans) for (const position of positions(plan)) {
            assert.equal(occupied.has(position), false, `Overlap at ${position}`); occupied.add(position);
        }
        assert.equal(occupied.size, 24 * (1 + count * 2));
        for (const plan of result.plans) for (const item of plan.cells) assert(CONSUMO_ATTRIBUTES.every(attribute => !item.hasAttribute(attribute)));
        assert.equal(snapshot(layout), before, 'No product or consumption is authored while planning');
    }
});

test('a wide selection determines the block width and a near-left start uses remaining A4 columns', () => {
    const { layout } = fixture();
    const selected = planSideBlock(layout, at(1, 2, 20), 5);
    assert.equal(selected.bounds.maxC, 21);
    assert.deepEqual(selected.widths, [5, 3, 2, 3]);
    const shifted = planSideBlock(layout, at(1, 1), 7);
    assert.equal(shifted.bounds.maxC, 23);
    assert.deepEqual(shifted.widths, [6, 4, 3, 3]);
    const narrowest = planSideBlock(layout, at(1, 8), 1);
    assert.deepEqual(narrowest.widths, [3, 2, 2, 2]);
    assert.throws(() => planSideBlock(layout, at(1, 9), 1), /ancho suficiente/);
});

test('a provider name typed in the selected corner can be moved without permitting unrelated text', () => {
    const { layout } = fixture(); const namedCell = layout.map[4][0]; namedCell.content.innerText = 'Cerveza';
    const before = snapshot(layout);
    assert.throws(() => planSideBlock(layout, at(4, 0), 5), /Hay texto/);
    const result = planSideBlock(layout, at(4, 0), 5, { namedCell });
    assert(result.cornerPlan.cells.has(namedCell));
    assert.equal(snapshot(layout), before);
    layout.map[12][15].content.innerText = 'Conservar nota';
    assert.throws(() => planSideBlock(layout, at(4, 0), 5, { namedCell }), /Hay texto/);
});

test('all creation boundaries and occupied or crossing cells reject atomically', () => {
    for (const variant of ['name', 'supplier', 'binding', 'horizontal-merge', 'vertical-merge', 'corner-merge']) {
        const { layout, place } = fixture();
        if (variant === 'name') layout.map[10][23].content.innerText = 'Pedido guardado';
        if (variant === 'supplier') layout.map[10][3].attributes.set('data-consumo-provider', 'another-supplier');
        if (variant === 'binding') layout.map[10][23].attributes.set('data-bound-date', '2026-10-04');
        if (variant === 'horizontal-merge') place({ minR: 5, maxR: 6, minC: 6, maxC: 7 });
        if (variant === 'vertical-merge') place({ minR: 6, maxR: 7, minC: 7, maxC: 13 });
        if (variant === 'corner-merge') place({ minR: 4, maxR: 5, minC: 0, maxC: 6 });
        const before = snapshot(layout);
        assert.throws(() => planSideBlock(layout, at(4, 0), 5), /texto|especial|unida/);
        assert.equal(snapshot(layout), before, variant);
    }
    const { layout } = fixture({ height: 11 }); const before = snapshot(layout);
    assert.equal(planSideBlock(layout, at(0, 0), 5).bounds.maxR, 10);
    assert.throws(() => planSideBlock(layout, at(1, 0), 5), /filas suficientes/);
    for (const count of [0, -1, 2.5, NaN, '5']) assert.throws(() => planSideBlock(layout, at(0, 0), count), /cuántas filas/);
    assert.throws(() => planSideBlock(layout, at(-1, 0), 1), /Selecciona/);
    assert.throws(() => planSideBlock(layout, at(0, 0), 1, { productHeight: 0 }), /dimensiones/);
    assert.equal(snapshot(layout), before);
});

test('extending five rows to seven preserves all existing names, IDs, quantities, stock and manual sources', () => {
    const { layout, create } = fixture(); const { provider, products } = create(at(2, 0), 5);
    const before = snapshot(layout);
    const description = readSideBlock(layout, provider);
    assert.equal(description.productCount, 5); assert.equal(description.productHeight, 2);
    assert.deepEqual(description.rows[0].cells, products[0]);
    const result = planSideBlockExtension(layout, provider, 7);
    assert.equal(result.existingCount, 5); assert.equal(result.rows.length, 2);
    assert.deepEqual(result.rows.map(row => row.row), [13, 15]);
    assert.equal(result.providerPlan.first, provider);
    assert(result.providerPlan.cells.has(provider));
    assert.equal(result.providerPlan.maxR, 16);
    assert.equal(result.preserved.length, 5);
    for (const product of products) for (const item of product) for (const plan of result.plans) assert.equal(plan.cells.has(item), false);
    assert.equal(products[0][1].getAttribute('data-consumo-sources'), '["pdf-zero","pdf-same-name"]');
    assert.equal(snapshot(layout), before);
});

test('new extension fields begin with empty consumption and expanding an already expanded block is repeatable', () => {
    const { layout, create, place } = fixture(); const { provider, products } = create(at(0, 0), 5);
    const extension = planSideBlockExtension(layout, provider, 7);
    place(extension.providerPlan, '', {}, provider);
    extension.rows.forEach((row, index) => row.plans.forEach((plan, fieldIndex) => {
        const field = PRODUCT_FIELDS[fieldIndex];
        const attributes = { 'data-consumo-product': `added-${index}`, 'data-consumo-supplier': 'supplier-a', 'data-consumo-field': field };
        if (field === 'consumo') attributes['data-consumo-sources'] = '[]';
        place(plan, '', attributes);
    }));
    const second = planSideBlockExtension(layout, provider, 8);
    assert.equal(second.existingCount, 7); assert.equal(second.rows.length, 1);
    assert.equal(second.rows[0].row, 15);
    assert.equal(products[0][1].content.innerText, '146 Uds.');
    const unchanged = planSideBlockExtension(layout, provider, 7);
    assert.deepEqual(unchanged.rows, []);
    assert.equal(unchanged.providerPlan.cells.size, 1);
});

test('occupied provider extensions and product areas refuse every new row without changing existing links', () => {
    for (const variant of ['left-text', 'right-text', 'left-binding', 'right-binding', 'crossing-merge', 'another-supplier']) {
        const { layout, create, place } = fixture(); const { provider } = create(at(0, 0), 5);
        if (variant === 'left-text') layout.map[14][0].content.innerText = 'FRIGO';
        if (variant === 'right-text') layout.map[14][22].content.innerText = 'Stock existente';
        if (variant === 'left-binding') layout.map[14][0].attributes.set('data-bound-emp', 'employee');
        if (variant === 'right-binding') layout.map[14][22].attributes.set('data-consumo-field', 'stock');
        if (variant === 'crossing-merge') place({ minR: 10, maxR: 11, minC: 21, maxC: 23 });
        if (variant === 'another-supplier') layout.map[14][0].attributes.set('data-consumo-provider', 'supplier-b');
        const before = snapshot(layout);
        assert.throws(() => planSideBlockExtension(layout, provider, 7));
        assert.equal(snapshot(layout), before, variant);
    }
});

test('shrink, insufficient space and damaged existing structures retain all products', () => {
    const small = fixture({ height: 14 }); const { provider: limited } = small.create(at(0, 0), 5);
    assert.throws(() => planSideBlockExtension(small.layout, limited, 7), /filas suficientes/);
    for (const variant of ['shrink', 'label', 'missing-field', 'foreign-provider', 'duplicate-id', 'invalid-sources', 'wrong-source-field', 'product-provider', 'employee']) {
        const { layout, create } = fixture(); const { provider, products, result } = create(at(0, 0), 5);
        if (variant === 'label') layout.map[0][7].content.innerText = 'NOMBRE';
        if (variant === 'missing-field') products[2][3].attributes.delete('data-consumo-field');
        if (variant === 'foreign-provider') products[2][3].attributes.set('data-consumo-supplier', 'supplier-b');
        if (variant === 'duplicate-id') products[2][3].attributes.set('data-consumo-product', 'product-1');
        if (variant === 'invalid-sources') products[2][1].attributes.set('data-consumo-sources', '[9]');
        if (variant === 'wrong-source-field') products[2][0].attributes.set('data-consumo-sources', '["source"]');
        if (variant === 'product-provider') products[2][0].attributes.set('data-consumo-provider', 'supplier-b');
        if (variant === 'employee') products[2][0].attributes.set('data-bound-emp', 'employee');
        const before = snapshot(layout);
        assert.throws(() => planSideBlockExtension(layout, provider, variant === 'shrink' ? 4 : 7));
        assert.equal(snapshot(layout), before, variant);
        assert.equal(result.productCount, 5);
    }
});

test('compact supplier sections fit the photo rows within a 73-row page without repeated headings', () => {
    const { layout } = fixture(); const before = snapshot(layout);
    const sections = [5, 3, 11, 9, 1];
    let row = 13;
    for (const [index, count] of sections.entries()) {
        const result = planSideBlock(layout, at(row, 0, 17), count, {
            providerWidth: 5, productHeight: 1, headerHeight: index === 0 ? 1 : 0,
        });
        assert.equal(result.rows.length, count);
        assert.equal(result.headerPlans.length, index === 0 ? 4 : 0);
        assert.equal(Boolean(result.cornerPlan), index === 0);
        assert.equal(result.rows[0].row, row + (index === 0 ? 1 : 0));
        assert.equal(result.productHeight, 1);
        assert.equal(result.plans.length, 1 + count * 4 + (index === 0 ? 5 : 0));
        assert.equal(result.bounds.maxC, 16);
        row = result.bounds.maxR + 1;
    }
    assert.equal(row, 43);
    assert.equal(snapshot(layout), before);
});

test('a headerless compact block at the first grid row can extend while retaining sources and stock', () => {
    const { layout, create, place } = fixture();
    const { provider, products } = create(at(0, 0), 5, { productHeight: 1, headerHeight: 0 });
    const before = snapshot(layout);
    const description = readSideBlock(layout, provider);
    assert.equal(description.headerHeight, 0);
    assert.equal(description.productHeight, 1);
    assert.equal(description.bounds.minR, 0);
    assert.deepEqual(description.headerCells, []);
    const extension = planSideBlockExtension(layout, provider, 7);
    assert.equal(extension.headerHeight, 0);
    assert.deepEqual(extension.rows.map(row => row.row), [5, 6]);
    assert.equal(extension.providerPlan.maxR, 6);
    assert.equal(snapshot(layout), before);
    place(extension.providerPlan, '', {}, provider);
    extension.rows.forEach((row, index) => row.plans.forEach((plan, fieldIndex) => {
        const field = PRODUCT_FIELDS[fieldIndex];
        place(plan, '', {
            'data-consumo-product': `compact-added-${index}`,
            'data-consumo-supplier': 'supplier-a', 'data-consumo-field': field,
            ...(field === 'consumo' ? { 'data-consumo-sources': '[]' } : {}),
        });
    }));
    assert.equal(readSideBlock(layout, provider).productCount, 7);
    assert.equal(products[0][1].getAttribute('data-consumo-sources'), '["pdf-zero","pdf-same-name"]');
    assert.equal(products[0][2].content.innerText, '3 cajas');
    assert.equal(planSideBlockExtension(layout, provider, 8).rows[0].row, 7);
});

test('compact creation and extension refuse occupied cells atomically, including a named cell without headers', () => {
    const creation = fixture(); const namedCell = creation.layout.map[3][0];
    namedCell.content.innerText = 'Frigo';
    const named = planSideBlock(creation.layout, at(3, 0), 3, { productHeight: 1, headerHeight: 0, namedCell });
    assert(named.providerPlan.cells.has(namedCell));
    creation.layout.map[5][23].content.innerText = 'Guardar pedido';
    const creationBefore = snapshot(creation.layout);
    assert.throws(() => planSideBlock(creation.layout, at(3, 0), 3, { productHeight: 1, headerHeight: 0, namedCell }), /Hay texto/);
    assert.equal(snapshot(creation.layout), creationBefore);

    const extension = fixture(); const { provider } = extension.create(at(3, 0), 3, { productHeight: 1, headerHeight: 0 });
    extension.layout.map[7][21].attributes.set('data-consumo-sources', '["saved-source"]');
    const extensionBefore = snapshot(extension.layout);
    assert.throws(() => planSideBlockExtension(extension.layout, provider, 5), /especial/);
    assert.equal(snapshot(extension.layout), extensionBefore);
    for (const options of [{ headerHeight: -1 }, { headerHeight: 2 }, { headerHeight: '0' }, { productHeight: 5 }]) {
        assert.throws(() => planSideBlock(creation.layout, at(0, 0), 1, options), /dimensiones/);
    }
});
