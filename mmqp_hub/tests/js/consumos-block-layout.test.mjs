import test from 'node:test';
import assert from 'node:assert/strict';
import { BLOCK_COLUMN_PROFILES, customColumnWidths, planCustomBlock } from '../../resources/js/consumos-block-layout.js';
import { readSuppliers } from '../../resources/js/consumos-grid.js';

function cell(text = '', attributes = {}) {
    const content = { textContent: text, innerText: text };
    return { content, attributes: new Map(Object.entries(attributes)),
        get dataset() { return Object.fromEntries([...this.attributes].filter(([key]) => key.startsWith('data-')).map(([key, value]) => [key.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase()), value])); },
        querySelector: selector => selector === '.cell-content' ? content : null,
        getAttribute(name) { return this.attributes.get(name) ?? null; },
        hasAttribute(name) { return this.attributes.has(name); } };
}
function fixture(height = 73, width = 24) {
    const layout = { height, width, map: [], bounds: new Map() };
    for (let row = 0; row < height; row++) {
        layout.map[row] = [];
        for (let col = 0; col < width; col++) {
            const item = cell(); layout.map[row][col] = item;
            layout.bounds.set(item, { minR: row, maxR: row, minC: col, maxC: col });
        }
    }
    const place = (bounds, text = '', attributes = {}) => {
        const item = cell(text, attributes);
        for (let row = bounds.minR; row <= bounds.maxR; row++) for (let col = bounds.minC; col <= bounds.maxC; col++) {
            layout.bounds.delete(layout.map[row][col]); layout.map[row][col] = item;
        }
        layout.bounds.set(item, { minR: bounds.minR, maxR: bounds.maxR, minC: bounds.minC, maxC: bounds.maxC });
        return item;
    };
    const tbody = { querySelectorAll(selector) {
        const [, attribute, value] = selector.match(/^\[([^=]+)(?:="([^"]+)")?\]$/);
        return [...layout.bounds.keys()].filter(item => value ? item.getAttribute(attribute) === value : item.hasAttribute(attribute));
    } };
    return { layout, place, tbody };
}
const at = (row = 0, col = 0) => ({ minR: row, maxR: row, minC: col, maxC: col });
const snapshot = layout => JSON.stringify([...layout.bounds].map(([item, bounds]) => ({ bounds, text: item.content.innerText, attributes: [...item.attributes] })));

test('compact rows and full-width category bands reproduce a long Central Pizza block atomically', () => {
    const { layout } = fixture(); const before = snapshot(layout);
    const entries = [{ kind: 'category', name: 'CONGELADO' }, 'ALITAS', 'AROS CEBOLLA',
        { kind: 'category', name: 'MASAS' }, 'B. PEQUEÑA', { name: 'B. MEDIANA', height: 2 }];
    const result = planCustomBlock(layout, at(1, 10), entries, { title: 'CENTRAL PIZZA', width: 14, headerHeight: 3,
        rowHeight: 1, columns: BLOCK_COLUMN_PROFILES[2], columnWidths: [4, 3, 2, 3, 1, 1] });
    assert.equal(result.typed, true);
    assert.deepEqual(result.bounds, { minR: 1, maxR: 11, minC: 10, maxC: 23 });
    assert.deepEqual(result.headerPlans.map(plan => plan.column), BLOCK_COLUMN_PROFILES[2]);
    assert.deepEqual(result.rows.map(row => [row.kind, row.row, row.height]), [['category', 5, 1], ['product', 6, 1], ['product', 7, 1], ['category', 8, 1], ['product', 9, 1], ['product', 10, 2]]);
    assert.equal(result.rows[0].plans.length, 1);
    assert.equal(result.rows[0].plans[0].maxC, 23);
    assert.equal(result.rows[1].fields.consumo.column, 'consumo');
    assert.equal(snapshot(layout), before);
});

test('optional labels, three-column GM, printed quantities and ordinary packaging have distinct plans', () => {
    const { layout } = fixture();
    const gm = planCustomBlock(layout, at(), ['C. CARAMELIZADA', 'CERVEZA'], { width: 8, title: 'GM', columns: BLOCK_COLUMN_PROFILES[1], headers: false, rowHeight: 1, headerHeight: 1 });
    assert.equal(gm.headerPlans.length, 0);
    assert.deepEqual(gm.rows[0].plans.map(plan => plan.column), ['product', 'consumo', 'stock']);
    assert.equal(gm.rows[0].row, 1);
    const quantity = planCustomBlock(layout, at(), [{ name: 'CAFÉ NORMAL', quantity: '1/2' }], { width: 12, title: 'MERCADONA', columns: BLOCK_COLUMN_PROFILES[3] });
    assert.equal(quantity.rows[0].quantity, '1/2');
    assert.equal(quantity.rows[0].plans[0].column, 'quantity');
    const packaging = planCustomBlock(layout, at(), ['BOLSAS (MAS QUE PIZZA)'], { width: 10, title: false, columns: BLOCK_COLUMN_PROFILES.at(-1) });
    assert.equal(packaging.providerPlan, null);
    assert.equal(packaging.typed, false);
    assert.deepEqual(packaging.rows[0].plans.map(plan => plan.column), ['product', 'stock', 'pedido']);
});

test('every supported width preserves exact grid coverage and a readable product column', () => {
    for (const columns of BLOCK_COLUMN_PROFILES) for (let width = columns.length + 1; width <= 24; width++) {
        const widths = customColumnWidths(width, columns);
        assert.equal(widths.reduce((sum, value) => sum + value, 0), width);
        assert(widths.every(value => Number.isInteger(value) && value >= 1));
        assert(widths[columns.indexOf('product')] >= 2);
    }
    assert.deepEqual(customColumnWidths(9, BLOCK_COLUMN_PROFILES[0], [4, 2, 1, 2]), [4, 2, 1, 2]);
});

test('late text, bindings and merges across category or column boundaries reject the entire block unchanged', () => {
    for (const variant of ['text', 'product', 'source', 'columns', 'employee', 'date', 'column-merge', 'category-merge']) {
        const { layout, place } = fixture();
        if (variant === 'text') layout.map[7][11].content.innerText = 'Una nota que conservar';
        const attributes = { product: 'data-consumo-product', source: 'data-consumo-sources', columns: 'data-consumo-columns', employee: 'data-bound-emp', date: 'data-bound-date' };
        if (attributes[variant]) layout.map[7][11].attributes.set(attributes[variant], 'saved');
        if (variant === 'column-merge') place({ minR: 6, maxR: 7, minC: 4, maxC: 5 });
        if (variant === 'category-merge') place({ minR: 4, maxR: 5, minC: 0, maxC: 11 });
        const before = snapshot(layout);
        assert.throws(() => planCustomBlock(layout, at(), ['ALITAS', { kind: 'category', name: 'MASAS' }, 'B. PEQUEÑA']), /texto|especial|unida/);
        assert.equal(snapshot(layout), before, variant);
    }
});

test('bad sizes, profiles, row heights and A4 overflow cannot mutate a block', () => {
    const { layout } = fixture(8, 24); const before = snapshot(layout);
    for (const options of [{ width: 3 }, { columns: ['product', 'stock'] }, { headerHeight: 0 }, { rowHeight: 5 },
        { columnWidths: [4, 2, 2, 2] }, { title: false }]) {
        assert.throws(() => planCustomBlock(layout, at(), ['ALITAS'], options));
    }
    assert.throws(() => planCustomBlock(layout, at(5), ['ALITAS']), /suficientes/);
    assert.throws(() => planCustomBlock(layout, at(0, 20), ['ALITAS']), /suficientes/);
    assert.throws(() => planCustomBlock(layout, at(), [{ name: 'CAFÉ', quantity: 1 }]), /cantidad impresa/);
    assert.equal(snapshot(layout), before);
});

function typedFixture(columns, missing = null) {
    const { layout, place, tbody } = fixture();
    const result = planCustomBlock(layout, at(), ['BBQ'], { title: 'MARMEN', columns, width: 14 });
    place(result.providerPlan, 'MARMEN', { 'data-consumo-provider': 'supplier', 'data-consumo-columns': columns.join(',') });
    for (const plan of result.rows[0].plans) {
        const core = ['product', 'consumo', 'stock', 'pedido'].includes(plan.column);
        if (!core || plan.column === missing) { place(plan, plan.column === 'quantity' ? '2' : ''); continue; }
        const attributes = { 'data-consumo-product': 'product', 'data-consumo-supplier': 'supplier', 'data-consumo-field': plan.column };
        if (plan.column === 'consumo') attributes['data-consumo-sources'] = '["source-a","source-b"]';
        place(plan, { product: 'BBQ', consumo: '999 authored', stock: '3 cajas', pedido: '' }[plan.column], attributes);
    }
    return { layout, tbody };
}

test('supplier extraction accepts explicit three-column GM and ignores printed quantities and M/V', () => {
    for (const columns of BLOCK_COLUMN_PROFILES.slice(0, -1)) {
        const { tbody } = typedFixture(columns);
        const suppliers = readSuppliers(tbody);
        assert.deepEqual(suppliers, [{ id: 'supplier', name: 'MARMEN', products: [{ id: 'product', name: 'BBQ', stock: '3 cajas', pedido: '', source_ids: ['source-a', 'source-b'] }] }]);
        assert.equal(JSON.stringify(suppliers).includes('999 authored'), false);
    }
});

test('a provider profile cannot omit required consumption or other core fields without rejection', () => {
    for (const columns of BLOCK_COLUMN_PROFILES.slice(0, -1)) for (const field of columns.filter(column => ['product', 'consumo', 'stock', 'pedido'].includes(column) && column !== 'product')) {
        const { tbody } = typedFixture(columns, field);
        assert.throws(() => readSuppliers(tbody), /todas las casillas/);
    }
    const { layout, tbody } = typedFixture(BLOCK_COLUMN_PROFILES[1]);
    const provider = [...layout.bounds.keys()].find(cell => cell.hasAttribute('data-consumo-provider'));
    provider.attributes.delete('data-consumo-columns');
    assert.throws(() => readSuppliers(tbody), /todas las casillas/);
    provider.attributes.set('data-consumo-columns', 'product,stock,pedido');
    assert.throws(() => readSuppliers(tbody), /no válida/);
});
