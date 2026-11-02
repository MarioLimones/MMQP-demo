import test from 'node:test';
import assert from 'node:assert/strict';
import { selectionFormatState } from '../../resources/js/arqueo-format-state.js';

const plain = (overrides = {}) => ({ edges: { top: false, bottom: false, left: false, right: false }, bold: false, size: 10, align: 'left', background: null, date: false, employee: false, ...overrides });
const cell = (minR, minC, maxR = minR, maxC = minC, appearance = plain()) => ({ bounds: { minR, minC, maxR, maxC }, appearance });
const state = cells => selectionFormatState({ bounds: new Map(cells.map(item => [item, item.bounds])) }, cells, item => item.appearance);

test('empty selection has no applied format or active binding', () => {
    assert.deepEqual(state([]), { states: {}, fontSize: null, color: null, hasBinding: false });
});
test('blank cells mark no borders and no background without marking border or bold actions', () => {
    const actual = state([cell(0, 0)]);
    assert.equal(actual.states['b-none'], 'on');
    assert.equal(actual.states['bg-none'], 'on');
    assert.equal(actual.states['b-all'], 'off');
    assert.equal(actual.states['font-bold'], 'off');
    assert.equal(actual.fontSize, 10);
});
test('fully bordered single cell marks all borders and outline independently', () => {
    const actual = state([cell(0, 0, 0, 0, plain({ edges: { top: true, bottom: true, left: true, right: true } }))]);
    assert.equal(actual.states['b-all'], 'on');
    assert.equal(actual.states['b-outer'], 'on');
    assert.equal(actual.states['b-none'], 'off');
});
test('merged perimeter marks outline without claiming that interior cells have all borders', () => {
    const cells = [
        cell(0, 0, 0, 1, plain({ edges: { top: true, bottom: false, left: true, right: true } })),
        cell(1, 0, 1, 0, plain({ edges: { top: false, bottom: true, left: true, right: false } })),
        cell(1, 1, 1, 1, plain({ edges: { top: false, bottom: true, left: false, right: true } })),
    ];
    const actual = state(cells);
    assert.equal(actual.states['b-outer'], 'on');
    assert.equal(actual.states['b-all'], 'mixed');
    assert.equal(actual.states['b-none'], 'off');
});
test('partial border and mixed selections show mixed feedback, never a false full tick', () => {
    const cells = [cell(0, 0, 0, 0, plain({ edges: { top: true, bottom: false, left: false, right: false }, bold: true })), cell(0, 1)];
    const actual = state(cells);
    assert.equal(actual.states['b-top'], 'mixed');
    assert.equal(actual.states['b-all'], 'mixed');
    assert.equal(actual.states['b-none'], 'mixed');
    assert.equal(actual.states['font-bold'], 'mixed');
});
test('alignment, font size and background reflect the selection rather than the active cell', () => {
    const actual = state([cell(0, 0, 0, 0, plain({ size: 12, align: 'center', background: 'rgb(229, 231, 235)' })), cell(0, 1)]);
    assert.equal(actual.fontSize, 'mixed');
    assert.equal(actual.color, 'mixed');
    assert.equal(actual.states['align-left'], 'mixed');
    assert.equal(actual.states['align-center'], 'mixed');
    assert.equal(actual.states['bg-none'], 'mixed');
});
test('date and employee bindings are reported separately and enable removal on mixed ranges', () => {
    const actual = state([cell(0, 0, 0, 0, plain({ date: true })), cell(0, 1, 0, 1, plain({ employee: true })), cell(0, 2)]);
    assert.equal(actual.states['bind-date'], 'mixed');
    assert.equal(actual.states['bind-employee'], 'mixed');
    assert.equal(actual.hasBinding, true);
});
