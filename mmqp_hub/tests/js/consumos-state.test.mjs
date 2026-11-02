import test from 'node:test';
import assert from 'node:assert/strict';
import { newSheet, savePayload, sourceOwners, sumQuantities, validateBinding } from '../../resources/js/consumos-state.js';

const rows = [
    { id: 'a', name: '4 quesos', quantity: '0.100', unit: 'KG', category: 'QUESOS' },
    { id: 'b', name: '4 quesos', quantity: '0.200', unit: 'KG', category: 'SIN GLUTEN' },
    { id: 'c', name: 'Barbacoa', quantity: '0.000', unit: 'KG' },
    { id: 'd', name: 'Barbacoa', quantity: '1.000', unit: 'Bolsa' },
];
const providers = [
    { id: 's1', name: 'Marmen', products: [{ id: 'p1', name: '4 quesos', stock: '', pedido: '', source_ids: ['a'] }] },
    { id: 's2', name: 'Mercadona', products: [{ id: 'p2', name: '4 quesos', stock: '', pedido: '', source_ids: [] }] },
];

test('exact sums retain zero and avoid floating point errors', () => {
    assert.deepEqual(sumQuantities(rows.slice(0, 2)), { amount: '0.3', unit: 'KG', source_count: 2 });
    assert.deepEqual(sumQuantities([rows[2]]), { amount: '0', unit: 'KG', source_count: 1 });
    assert.equal(sumQuantities([{ quantity: '9007199254740993.001', unit: 'KG' }, { quantity: '-9007199254740993.000', unit: 'KG' }]).amount, '0.001');
    assert.equal(sumQuantities([{ quantity: '+1.00101', unit: 'KG' }, { quantity: '-0.001', unit: 'KG' }]).amount, '1.00001');
});

test('similar names in different suppliers never link automatically', () => {
    const sheet = newSheet(); sheet.suppliers = structuredClone(providers);
    assert.deepEqual(sheet.suppliers[1].products[0].source_ids, []);
    assert.equal(sourceOwners(sheet.suppliers).get('a').label, 'Marmen · 4 quesos');
    assert.equal(sourceOwners(sheet.suppliers).has('b'), false);
    assert.deepEqual(validateBinding(['b'], rows, sheet.suppliers, 'p2'), { amount: '0.2', unit: 'KG', source_count: 1 });
});

test('bindings reject units, reuse, duplicates and unknown IDs', () => {
    assert.throws(() => validateBinding(['b', 'd'], rows, providers, 'p2'), /misma unidad/);
    assert.throws(() => validateBinding(['a'], rows, providers, 'p2'), /ya está vinculada/);
    assert.throws(() => validateBinding(['b', 'b'], rows, providers, 'p2'), /más de una vez/);
    assert.throws(() => validateBinding(['unknown'], rows, providers, 'p2'), /no pertenece/);
    assert.equal(validateBinding([], rows, providers, 'p2'), null);
});

test('save payload retains manual structure but sends no authored consumption', () => {
    const sheet = { ...newSheet(), id: 1, version: 2, suppliers: structuredClone(providers) };
    sheet.suppliers[0].products[0].consumption = { amount: '123', unit: 'KG' };
    const payload = savePayload(sheet);
    assert.equal(payload.version, 2);
    assert.equal(Object.hasOwn(payload.suppliers[0].products[0], 'consumption'), false);
    assert.deepEqual(payload.suppliers[0].products[0].source_ids, ['a']);
    payload.suppliers[0].products[0].source_ids.push('b');
    assert.deepEqual(sheet.suppliers[0].products[0].source_ids, ['a']);
});
