import test from 'node:test';
import assert from 'node:assert/strict';
import { searchRows } from '../../resources/js/consumos-search.js';

const rows = [
    { id: 'a', name: 'FANTA LIMÓN K, 1 LITRO', quantity: '0.000' },
    { id: 'b', name: 'K 1 LITRO', quantity: '12.000' },
    { id: 'c', name: 'BARBACOA', quantity: '36.147' },
    { id: 'd', name: 'BARBACOA', quantity: '0.000' },
];

test('search accepts accents, punctuation, reordered terms and abbreviated input without changing rows', () => {
    assert.deepEqual(searchRows(rows, 'litro k 1').map(row => row.id), ['a', 'b']);
    assert.deepEqual(searchRows(rows, 'limon').map(row => row.id), ['a']);
    assert.equal(rows[0].name, 'FANTA LIMÓN K, 1 LITRO');
});

test('search keeps distinct repeated names and zero amounts; no match is an empty list', () => {
    assert.deepEqual(searchRows(rows, 'barba').map(row => row.id), ['c', 'd']);
    assert.deepEqual(searchRows(rows, 'BBQ'), []);
    assert.deepEqual(searchRows(rows, ''), rows);
    assert.ok(rows.every(row => !('source_ids' in row)));
});
