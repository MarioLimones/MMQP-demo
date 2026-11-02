import test from 'node:test';
import assert from 'node:assert/strict';
import { getOrderWidths, planOrderBlock } from '../../resources/js/consumos-order-grid.js';
import { CONSUMO_ATTRIBUTES } from '../../resources/js/consumos-grid.js';

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
    const place = (bounds, text = '', attributes = {}) => {
        const item = cell(text, attributes);
        for (let row = bounds.minR; row <= bounds.maxR; row++) for (let col = bounds.minC; col <= bounds.maxC; col++) {
            layout.bounds.delete(layout.map[row][col]); layout.map[row][col] = item;
        }
        layout.bounds.set(item, { minR: bounds.minR, maxR: bounds.maxR, minC: bounds.minC, maxC: bounds.maxC });
        return item;
    };
    return { layout, place };
}

const at = (row, col, width = 1) => ({ minR: row, maxR: row, minC: col, maxC: col + width - 1 });
const snapshot = layout => JSON.stringify([...layout.bounds].map(([item, bounds]) => ({ bounds, text: item.content.innerText, attributes: [...item.attributes] })));
const names = ['Marmen', 'Central Pizza', 'Tello', 'Pepsi', 'Mercadona', 'Mancha Plus', 'Hostelum', 'Cajas'];

test('the eight-provider ordering table covers a distinct four-column block with no consumption metadata', () => {
    const { layout } = fixture(); const before = snapshot(layout);
    const result = planOrderBlock(layout, at(4, 0), names);
    assert.deepEqual(result.widths, [7, 10, 3, 4]);
    assert.deepEqual(result.bounds, { minR: 4, maxR: 21, minC: 0, maxC: 23 });
    assert.equal(result.headerHeight, 2);
    assert.equal(result.headerPlans.length, 4);
    assert.deepEqual(result.rows.map(row => row.name), names);
    assert.deepEqual(result.rows.map(row => row.row), [6, 8, 10, 12, 14, 16, 18, 20]);
    const occupied = new Set();
    for (const plan of result.plans) {
        for (let row = plan.minR; row <= plan.maxR; row++) for (let col = plan.minC; col <= plan.maxC; col++) {
            const position = `${row}:${col}`;
            assert.equal(occupied.has(position), false, position); occupied.add(position);
        }
        for (const item of plan.cells) assert(CONSUMO_ATTRIBUTES.every(attribute => !item.hasAttribute(attribute)));
    }
    assert.equal(occupied.size, 24 * 18);
    assert.equal(snapshot(layout), before);
});

test('the selected width and starting column adapt the table while keeping four usable columns', () => {
    const { layout } = fixture();
    assert.deepEqual(getOrderWidths(24), [7, 10, 3, 4]);
    for (let width = 16; width <= 24; width++) {
        const columns = getOrderWidths(width);
        assert.equal(columns.reduce((sum, value) => sum + value, 0), width);
        assert(columns.every(value => Number.isInteger(value) && value >= 2));
    }
    const selected = planOrderBlock(layout, at(1, 2, 20), names);
    assert.equal(selected.bounds.maxC, 21);
    assert.deepEqual(selected.widths, getOrderWidths(20));
    const shifted = planOrderBlock(layout, at(1, 1), names);
    assert.equal(shifted.bounds.maxC, 23);
    assert.deepEqual(shifted.widths, getOrderWidths(23));
    assert.equal(planOrderBlock(layout, at(1, 8), names).bounds.maxC, 23);
    assert.throws(() => planOrderBlock(layout, at(1, 9), names), /ancho suficiente/);
    assert.throws(() => getOrderWidths(15), /16 casillas/);
});

test('a selected provider name is allowed only in the first provider or header cell', () => {
    for (const row of [0, 2]) {
        const { layout } = fixture(); const namedCell = layout.map[row][0];
        namedCell.content.innerText = 'Marmen';
        assert.throws(() => planOrderBlock(layout, at(0, 0), ['Marmen']), /Hay texto/);
        const before = snapshot(layout);
        const result = planOrderBlock(layout, at(0, 0), [' Marmen '], { namedCell });
        assert.equal(result.rows[0].name, 'Marmen');
        assert.equal(snapshot(layout), before);
    }
    for (const [row, col] of [[2, 7], [4, 0], [0, 7]]) {
        const { layout } = fixture(); const namedCell = layout.map[row][col];
        namedCell.content.innerText = 'Nota que conservar';
        assert.throws(() => planOrderBlock(layout, at(0, 0), names, { namedCell }), /Hay texto/);
    }
});

test('collisions anywhere in the last rows reject the entire table without changing existing notes or bindings', () => {
    for (const variant of ['note', 'provider', 'product', 'source', 'employee', 'date', 'column-merge', 'row-merge']) {
        const { layout, place } = fixture();
        if (variant === 'note') layout.map[17][23].content.innerText = 'Recibimos el jueves';
        if (variant === 'provider') layout.map[17][0].attributes.set('data-consumo-provider', 'saved-provider');
        if (variant === 'product') layout.map[17][23].attributes.set('data-consumo-product', 'saved-product');
        if (variant === 'source') layout.map[17][23].attributes.set('data-consumo-sources', '["manual-source"]');
        if (variant === 'employee') layout.map[17][23].attributes.set('data-bound-emp', 'employee');
        if (variant === 'date') layout.map[17][23].attributes.set('data-bound-date', '2026-10-04');
        if (variant === 'column-merge') place({ minR: 16, maxR: 17, minC: 19, maxC: 20 });
        if (variant === 'row-merge') place({ minR: 15, maxR: 16, minC: 20, maxC: 23 });
        const before = snapshot(layout);
        assert.throws(() => planOrderBlock(layout, at(0, 0), names), /texto|especial|unida/);
        assert.equal(snapshot(layout), before, variant);
    }
});

test('an existing ordering block keeps all free text when a second block is planned elsewhere', () => {
    const { layout, place } = fixture();
    const first = planOrderBlock(layout, at(0, 0), ['Marmen', 'Central Pizza']);
    first.headerPlans.forEach((plan, index) => place(plan, ['PROVEEDOR', '¿QUÉ PEDIR?', '¿HECHO?', '¿CUÁNDO RECIBIMOS?'][index]));
    first.rows.forEach((row, index) => row.plans.forEach((plan, column) => place(plan, [row.name, 'Hoja trasera', index === 0 ? 'Sí' : 'Dom. / Mar.', 'El jueves'][column])));
    const before = snapshot(layout);
    const second = planOrderBlock(layout, at(6, 0), ['Pepsi', 'Mercadona']);
    assert.equal(second.rows.length, 2);
    assert.equal(snapshot(layout), before);
    assert.throws(() => planOrderBlock(layout, at(0, 0), ['Pepsi']), /Hay texto/);
    assert.equal(snapshot(layout), before);
});

test('counts, names, dimensions and A4 boundaries are checked before any cell can change', () => {
    const { layout } = fixture({ height: 18 }); const before = snapshot(layout);
    assert.equal(planOrderBlock(layout, at(0, 0), names).bounds.maxR, 17);
    assert.throws(() => planOrderBlock(layout, at(1, 0), names), /filas suficientes/);
    for (const invalid of [[], Array.from({ length: 31 }, () => 'Proveedor'), [''], [' '], ['A'.repeat(256)], ['Marmen', null], 'Marmen']) {
        assert.throws(() => planOrderBlock(layout, at(0, 0), invalid), /entre 1 y 30 proveedores/);
    }
    for (const options of [{ width: 0 }, { width: 15 }, { width: '24' }, { rowHeight: 0 }, { rowHeight: 1.5 }]) {
        assert.throws(() => planOrderBlock(layout, at(0, 0), ['Marmen'], options), /dimensiones/);
    }
    assert.throws(() => planOrderBlock(layout, at(-1, 0), ['Marmen']), /Selecciona/);
    assert.throws(() => planOrderBlock(layout, at(0, 24), ['Marmen']), /Selecciona/);
    assert.equal(snapshot(layout), before);
});

test('compact order headings and rows leave enough A4 space for the photo supplier sections', () => {
    const { layout } = fixture(); const before = snapshot(layout);
    const result = planOrderBlock(layout, at(4, 0), names, { headerHeight: 1, rowHeight: 1 });
    assert.deepEqual(result.bounds, { minR: 4, maxR: 12, minC: 0, maxC: 23 });
    assert.equal(result.headerHeight, 1);
    assert.deepEqual(result.headerPlans.map(plan => [plan.minR, plan.maxR]), [[4, 4], [4, 4], [4, 4], [4, 4]]);
    assert.deepEqual(result.rows.map(row => row.row), [5, 6, 7, 8, 9, 10, 11, 12]);
    assert(result.rows.every(row => row.height === 1 && row.plans.every(plan => plan.maxR === plan.minR)));
    assert.equal(snapshot(layout), before);
    const tall = planOrderBlock(layout, at(0, 0), ['Hostelum'], { headerHeight: 4, rowHeight: 4 });
    assert.equal(tall.rows[0].row, 4);
    assert.equal(tall.bounds.maxR, 7);
});

test('compact order dimensions preserve occupied notes and reject invalid heights before mutating', () => {
    const { layout } = fixture();
    layout.map[8][23].content.innerText = 'Confirmado';
    const before = snapshot(layout);
    assert.throws(() => planOrderBlock(layout, at(0, 0), names, { headerHeight: 1, rowHeight: 1 }), /Hay texto/);
    for (const options of [{ headerHeight: 0 }, { headerHeight: 5 }, { headerHeight: 1.5 }, { headerHeight: '1' }, { rowHeight: 5 }]) {
        assert.throws(() => planOrderBlock(layout, at(0, 0), ['Marmen'], options), /dimensiones/);
    }
    assert.equal(snapshot(layout), before);
});
