import test from 'node:test';
import assert from 'node:assert/strict';
import { mergePrintEdges } from '../../resources/js/arqueo-print-borders.js';

const edge = (axis, position, start, end) => ({ axis, position, start, end });
const ordered = edges => [...edges].sort((a, b) => a.axis.localeCompare(b.axis)
    || a.position - b.position || a.start - b.start || a.end - b.end);

test('duplicate and overlapping cell borders print as one continuous stroke', () => {
    const actual = mergePrintEdges([
        edge('x', 10, 20, 50),
        edge('x', 10, 0, 30),
        edge('x', 10, 20, 50),
        edge('x', 10, 5, 15),
    ]);

    assert.deepEqual(actual, [edge('x', 10, 0, 50)]);
});

test('touching row segments join even when the bridging segment arrives last', () => {
    const actual = mergePrintEdges([
        edge('y', 120, 40, 60),
        edge('y', 120, 0, 20),
        edge('y', 120, 20, 40),
    ]);

    assert.deepEqual(actual, [edge('y', 120, 0, 60)]);
});

test('unbordered input stays empty and distinct cell sides stay distinct', () => {
    assert.deepEqual(mergePrintEdges([]), []);
    const edges = [edge('x', 15, 0, 50), edge('x', 35, 0, 50), edge('y', 0, 15, 35)];

    assert.deepEqual(ordered(mergePrintEdges(edges)), ordered(edges));
});

test('subpixel coordinates identify a shared border without joining a different position', () => {
    const actual = mergePrintEdges([
        edge('x', 100.12341, 0, 10),
        edge('x', 100.12349, 10, 20),
        edge('x', 100.12351, 0, 20),
    ]);

    assert.deepEqual(ordered(actual), [edge('x', 100.123, 0, 20), edge('x', 100.124, 0, 20)]);
});

test('crossing horizontal and vertical borders never merge with each other', () => {
    const edges = [edge('x', 25, 0, 50), edge('y', 25, 0, 50)];

    assert.deepEqual(ordered(mergePrintEdges(edges)), ordered(edges));
});

test('a deliberately unbordered gap survives between collinear segments', () => {
    const edges = [edge('y', 60, 10.25, 30), edge('y', 60, 0, 10.125)];

    assert.deepEqual(ordered(mergePrintEdges(edges)), ordered(edges));
});

test('preparing the print layer does not alter the saved border coordinates', () => {
    const edges = [edge('x', 80, 20, 40), edge('x', 80, 0, 20)];
    const before = structuredClone(edges);

    mergePrintEdges(edges);

    assert.deepEqual(edges, before);
});
