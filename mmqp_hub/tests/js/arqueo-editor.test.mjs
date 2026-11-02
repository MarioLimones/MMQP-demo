import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ArqueoHistory, buildGridMap, selectionRectangle, employeeBindingTargets, employeeBindingAnchor, planEmployeeBinding, postTemplate, contextMenuPosition, moreOptionsPosition, parseTabularText, planGridPaste, gridSelectionText, clipboardValues } from '../../resources/js/arqueo-editor.js';

const cell = (name, rowSpan = 1, colSpan = 1) => ({ name, rowSpan, colSpan });
const row = (...cells) => ({ cells });

test('more options opens to the right of its trigger when there is space', () => {
    assert.deepEqual(moreOptionsPosition({ top: 50, bottom: 90, right: 180 }, { width: 240, height: 200 }, { width: 1024, height: 800 }), { left: 188, top: 50 });
});

test('more options aligns the right edge below its trigger near the viewport edge', () => {
    assert.deepEqual(moreOptionsPosition({ top: 30, bottom: 70, right: 980 }, { width: 240, height: 200 }, { width: 1024, height: 800 }), { left: 740, top: 78 });
});

test('more options moves above its trigger if there is no room underneath', () => {
    assert.deepEqual(moreOptionsPosition({ top: 740, bottom: 780, right: 980 }, { width: 240, height: 200 }, { width: 1024, height: 800 }), { left: 740, top: 532 });
});

test('more options stays within a shifted visual viewport', () => {
    const position = moreOptionsPosition({ top: 530, bottom: 550, right: 390 }, { width: 240, height: 200 }, { width: 390, height: 500, offsetLeft: 40, offsetTop: 100 });
    assert.deepEqual(position, { left: 150, top: 322 });
});

test('Excel tabular text preserves blank fields and uses CRLF rows', () => {
    assert.deepEqual(parseTabularText('A\tB\t\r\n1\t\t3\r\n'), [['A', 'B', ''], ['1', '', '3']]);
});

test('Excel quoted fields preserve embedded tabs, newlines, and escaped quotes', () => {
    assert.deepEqual(parseTabularText('"A\tB"\t"C\r\nD"\r\n"Say ""Hi"""\tE'), [['A\tB', 'C\nD'], ['Say "Hi"', 'E']]);
});

test('an intentional empty last record is retained while the final line terminator is removed', () => {
    assert.deepEqual(parseTabularText('A\n\n'), [['A'], ['']]);
    assert.deepEqual(parseTabularText(''), [['']]);
});

test('unclosed quoted clipboard data is rejected instead of silently changed', () => {
    assert.throws(() => parseTabularText('"A\tB'), /comillas sin cerrar/);
});

test('ordinary single-cell clipboard text preserves literal quotes', () => {
    assert.deepEqual(clipboardValues('"INGRESOS"'), [['"INGRESOS"']]);
    assert.deepEqual(clipboardValues('"Heading'), [['"Heading']]);
});

test('encoded grid single-cell text decodes escaped quotes for an exact round trip', () => {
    assert.deepEqual(clipboardValues('"""INGRESOS"""', true), [['"INGRESOS"']]);
});

test('paste plans a rectangle without mutating any destination cell', () => {
    const a = cell('a'), b = cell('b'), c = cell('c'), d = cell('d');
    const grid = buildGridMap([row(a, b), row(c, d)]);
    const before = JSON.stringify([a, b, c, d]);
    assert.deepEqual(planGridPaste(grid, grid.bounds.get(a), [['ONE', 'TWO'], ['THREE', 'FOUR']]).writes, [{ cell: a, text: 'ONE' }, { cell: b, text: 'TWO' }, { cell: c, text: 'THREE' }, { cell: d, text: 'FOUR' }]);
    assert.equal(JSON.stringify([a, b, c, d]), before);
});

test('paste is entirely rejected at the right and bottom sheet boundaries', () => {
    const a = cell('a'), b = cell('b'), c = cell('c'), d = cell('d');
    const grid = buildGridMap([row(a, b), row(c, d)]);
    assert.equal(planGridPaste(grid, grid.bounds.get(b), [['A', 'B']]).error, 'outside');
    assert.equal(planGridPaste(grid, grid.bounds.get(c), [['A'], ['B']]).error, 'outside');
});

test('multiple populated source fields never collapse into one merged destination', () => {
    const merged = cell('merged', 1, 2), next = cell('next');
    const grid = buildGridMap([row(merged, next)]);
    assert.equal(planGridPaste(grid, grid.bounds.get(merged), [['FIRST', 'SECOND']]).error, 'merged-values');
    assert.equal(planGridPaste(grid, grid.bounds.get(merged), [['SAME', 'SAME']]).error, 'merged-values');
});

test('merged clipboard values with blank covered slots are preserved once', () => {
    const merged = cell('merged', 2, 2), next = cell('next'), lower = cell('lower');
    const grid = buildGridMap([row(merged, next), row(lower)]);
    const plan = planGridPaste(grid, grid.bounds.get(merged), [['VALUE', '', 'RIGHT'], ['', '', 'BOTTOM']]);
    assert.deepEqual(plan.writes, [{ cell: merged, text: 'VALUE' }, { cell: next, text: 'RIGHT' }, { cell: lower, text: 'BOTTOM' }]);
});

test('paste refuses a rowspan beginning outside the target range', () => {
    const left = cell('left'), span = cell('span', 2), bottom = cell('bottom');
    const grid = buildGridMap([row(left, span), row(bottom)]);
    assert.equal(planGridPaste(grid, grid.bounds.get(bottom), [['NEW', 'OTHER']]).error, 'partial-merge');
});

test('copying a merged rectangle exports blank slots and round-trips multiline words', () => {
    const merged = cell('merged', 1, 2), third = cell('third');
    const grid = buildGridMap([row(merged, third)]);
    const text = gridSelectionText(grid, [merged, third], item => item === merged ? 'A\n"B"' : 'C\tD');
    assert.deepEqual(parseTabularText(text), [['A\n"B"', '', 'C\tD']]);
    assert.equal(planGridPaste(grid, grid.bounds.get(merged), parseTabularText(text)).writes.length, 2);
});

test('ragged rows are padded with empty destination fields for rectangular pasting', () => {
    const a = cell('a'), b = cell('b'), c = cell('c'), d = cell('d');
    const grid = buildGridMap([row(a, b), row(c, d)]);
    const plan = planGridPaste(grid, grid.bounds.get(a), [['A', 'B'], ['C']]);
    assert.deepEqual(plan.writes.at(-1), { cell: d, text: '' });
});

test('context menu fits the visible bottom and right edges', () => {
    assert.deepEqual(contextMenuPosition({x:1000,y:800}, {width:360,height:400}, {width:1080,height:870}), {left:712,top:462});
});

test('context menu uses the visible viewport when the layout viewport is larger', () => {
    assert.deepEqual(contextMenuPosition({x:900,y:1000}, {width:300,height:300}, {width:390,height:500,offsetLeft:40,offsetTop:100}), {left:122,top:292});
});

test('context menu keeps a margin when opened at the top-left edge', () => {
    assert.deepEqual(contextMenuPosition({x:0,y:0}, {width:360,height:400}, {width:1440,height:1000}), {left:8,top:8});
});

test('logical positions include rowspans without moving the following cells', () => {
    const a = cell('a', 2, 2), b = cell('b'), c = cell('c'), d = cell('d');
    const grid = buildGridMap([row(a, b), row(c), row(d, cell('e'), cell('f'))]);
    assert.equal(grid.width, 3);
    assert.equal(grid.map[1][0], a);
    assert.equal(grid.map[1][1], a);
    assert.equal(grid.map[1][2], c);
    assert.deepEqual(grid.bounds.get(c), { minR: 1, maxR: 1, minC: 2, maxC: 2 });
    assert.equal(grid.map[2][0], d);
});

test('selection expands to fully include overlapping merged cells', () => {
    const a = cell('a'), b = cell('b', 2, 2), c = cell('c'), d = cell('d'), e = cell('e'), f = cell('f');
    const grid = buildGridMap([row(a, b, c), row(d, e), row(f, cell('g'), cell('h'), cell('i'))]);
    const selection = selectionRectangle(grid, [a, e]);
    assert.deepEqual({ minR: selection.minR, maxR: selection.maxR, minC: selection.minC, maxC: selection.maxC }, { minR: 0, maxR: 1, minC: 0, maxC: 3 });
    assert.deepEqual(new Set(selection.cells), new Set([a, b, c, d, e]));
});

test('empty selections have no rectangle', () => {
    assert.equal(selectionRectangle(buildGridMap([row(cell('a'))]), []), null);
});

test('employee fields use the next distinct cells after colspans', () => {
    const name = cell('name', 1, 4), morning = cell('morning', 1, 4), night = cell('night', 1, 4);
    const grid = buildGridMap([row(name, morning, night)]);
    assert.deepEqual(employeeBindingTargets(grid, name), [name, morning, night]);
});

test('employee binding is atomic when the row has insufficient cells', () => {
    const name = cell('name', 1, 6), morning = cell('morning', 1, 6);
    assert.deepEqual(employeeBindingTargets(buildGridMap([row(name, morning)]), name), []);
});

test('employee binding rejects cells occupied by a rowspan starting earlier', () => {
    const spanning = cell('spanning', 2), top = cell('top'), name = cell('name');
    const grid = buildGridMap([row(top, spanning), row(name, cell('night'))]);
    assert.deepEqual(employeeBindingTargets(grid, name), []);
});

test('employee planning reserves three columns for every field, including future empty shifts', () => {
    const cells = Array.from({ length: 9 }, (_, i) => cell(`c${i}`));
    const before = JSON.stringify(cells);
    const plan = planEmployeeBinding(buildGridMap([row(...cells)]), cells[0]);
    assert.deepEqual(plan.fields.map(field => ({ prop: field.prop, cells: field.cells, width: field.width })), [
        { prop: 'nombre', cells: cells.slice(0, 3), width: 3 },
        { prop: 'manana', cells: cells.slice(3, 6), width: 3 },
        { prop: 'noche', cells: cells.slice(6, 9), width: 3 },
    ]);
    assert.equal(JSON.stringify(cells), before);
});

test('employee planning measures each actual field anchor and permits wider names and several shifts', () => {
    const cells = Array.from({ length: 14 }, (_, i) => cell(`c${i}`));
    const anchors = [];
    const widths = { nombre: 5, manana: 6, noche: 3 };
    const plan = planEmployeeBinding(buildGridMap([row(...cells)]), cells[0], { requiredWidth: (prop, anchor) => { anchors.push(anchor); return widths[prop]; } });
    assert.deepEqual(anchors, [cells[0], cells[5], cells[11]]);
    assert.deepEqual(plan.fields.map(field => field.width), [5, 6, 3]);
    assert.equal(new Set(plan.fields.flatMap(field => field.cells)).size, 14);
});

test('employee planning preserves already wide merged cells without splitting them', () => {
    const fields = [cell('name', 2, 6), cell('morning', 2, 4), cell('night', 2, 4)];
    const plan = planEmployeeBinding(buildGridMap([row(...fields), row()]), fields[0]);
    assert.deepEqual(plan.fields.map(field => field.cells), fields.map(field => [field]));
    assert.deepEqual(plan.fields.map(field => field.width), [6, 4, 4]);
    assert(plan.fields.every(field => field.rowSpan === 2));
});

test('employee planning refuses an insufficient right edge before changing anything', () => {
    const cells = Array.from({ length: 8 }, (_, i) => cell(`c${i}`));
    const before = JSON.stringify(cells);
    assert.equal(planEmployeeBinding(buildGridMap([row(...cells)]), cells[0]).error, 'outside');
    assert.equal(JSON.stringify(cells), before);
});

test('employee planning is atomic when a later neighbouring cell contains protected data', () => {
    const cells = Array.from({ length: 12 }, (_, i) => cell(`c${i}`));
    const plan = planEmployeeBinding(buildGridMap([row(...cells)]), cells[0], { canUse: item => item !== cells[7] });
    assert.equal(plan.error, 'occupied');
    assert.equal(plan.cell, cells[7]);
    assert.equal(plan.fields, undefined);
    assert(cells.every(item => item.colSpan === 1));
});

test('employee planning never crosses a drawn division outside explicit authorisation', () => {
    const cells = Array.from({ length: 9 }, (_, i) => cell(`c${i}`));
    cells[1].classList = { contains: name => name === 'b-right' };
    assert.equal(planEmployeeBinding(buildGridMap([row(...cells)]), cells[0]).error, 'border');
    const plan = planEmployeeBinding(buildGridMap([row(...cells)]), cells[0], { canRemoveDivider: (left, right) => left === cells[1] && right === cells[2] });
    assert.equal(plan.fields.length, 3);
    assert.equal(plan.removedDividers, 1);
});

test('employee planning can regroup an explicitly selected full grid while preserving field perimeters', () => {
    const cells = Array.from({ length: 9 }, (_, i) => ({ ...cell(`c${i}`), classList: { contains: name => name === 'solid-border' } }));
    const plan = planEmployeeBinding(buildGridMap([row(...cells)]), cells[0], { canRemoveDivider: () => true });
    assert.equal(plan.removedDividers, 6);
    assert.deepEqual(plan.fields.map(field => field.width), [3, 3, 3]);
    const limited = new Set(cells.slice(0, 3));
    assert.equal(planEmployeeBinding(buildGridMap([row(...cells)]), cells[0], { canRemoveDivider: (left, right) => limited.has(left) && limited.has(right) }).error, 'border');
});

test('employee planning conserves different backgrounds and partial horizontal borders by rejecting a merge', () => {
    const cells = Array.from({ length: 9 }, (_, i) => cell(`c${i}`));
    cells[1].style = { backgroundColor: 'gray' };
    assert.equal(planEmployeeBinding(buildGridMap([row(...cells)]), cells[0]).error, 'format');
    cells[1].style = {};
    cells[1].classList = { contains: name => name === 'b-top' };
    assert.equal(planEmployeeBinding(buildGridMap([row(...cells)]), cells[0]).error, 'format');
});

test('employee planning rejects rowspans that start before the chosen row', () => {
    const top = cell('top'), crossing = cell('crossing', 2), name = cell('name');
    const grid = buildGridMap([row(top, crossing, ...Array.from({ length: 9 }, (_, i) => cell(`top${i}`))), row(name, ...Array.from({ length: 9 }, (_, i) => cell(`bottom${i}`)))]);
    assert.equal(planEmployeeBinding(grid, name).error, 'shape');
});

test('employee planning requires equal cell heights but supports matching multi-row fields', () => {
    const mismatched = [cell('name', 2), ...Array.from({ length: 8 }, (_, i) => cell(`c${i}`))];
    assert.equal(planEmployeeBinding(buildGridMap([row(...mismatched), row()]), mismatched[0]).error, 'shape');
    const sameHeight = Array.from({ length: 9 }, (_, i) => cell(`c${i}`, 2));
    const plan = planEmployeeBinding(buildGridMap([row(...sameHeight), row()]), sameHeight[0]);
    assert.deepEqual(plan.fields.map(field => field.rowSpan), [2, 2, 2]);
});

test('editing either shift finds its employee name across merged fields', () => {
    const fields = ['nombre', 'manana', 'noche'].map(prop => ({ ...cell(prop, 1, 3), dataset: { boundEmp: '27', boundProp: prop } }));
    const grid = buildGridMap([row(...fields)]);
    fields.forEach(field => assert.equal(employeeBindingAnchor(grid, field), fields[0]));
});

test('editing a repeated employee group stays in the clicked group', () => {
    const fields = Array.from({ length: 2 }, () => ['nombre', 'manana', 'noche'].map(prop => ({ ...cell(prop, 1, 3), dataset: { boundEmp: '27', boundProp: prop } }))).flat();
    const grid = buildGridMap([row(...fields)]);
    assert.equal(employeeBindingAnchor(grid, fields[5]), fields[3]);
});

test('incomplete or differently bound neighbouring fields cannot redirect employee editing', () => {
    const name = { ...cell('name', 1, 3), dataset: { boundEmp: '28', boundProp: 'nombre' } };
    const morning = { ...cell('morning', 1, 3), dataset: { boundEmp: '27', boundProp: 'manana' } };
    const night = { ...cell('night', 1, 3), dataset: { boundEmp: '27', boundProp: 'noche' } };
    const grid = buildGridMap([row(name, morning, night)]);
    assert.equal(employeeBindingAnchor(grid, morning), morning);
    assert.equal(employeeBindingAnchor(grid, night), night);
});

test('history restores changes and drops redo when editing an older version', () => {
    const history = new ArqueoHistory('initial');
    assert.equal(history.dirty, false);
    history.commit('first'); history.commit('second');
    assert.equal(history.undo(), 'first');
    assert.equal(history.redo(), 'second');
    history.undo(); history.commit('replacement');
    assert.equal(history.redo(), null);
    assert.equal(history.undo(), 'first');
    assert.equal(history.undo(), 'initial');
    assert.equal(history.dirty, false);
});

test('a saved older request never marks newer edits as saved', () => {
    const history = new ArqueoHistory('initial');
    history.commit('request'); history.commit('later edit'); history.markSaved('request');
    assert.equal(history.dirty, true);
    history.undo();
    assert.equal(history.dirty, false);
});

test('history ignores identical commits and bounds retained snapshots', () => {
    const history = new ArqueoHistory('initial', 3);
    assert.equal(history.commit('initial'), false);
    history.commit('one'); history.commit('two'); history.commit('three');
    assert.deepEqual(history.entries, ['one', 'two', 'three']);
    assert.equal(history.undo(), 'two');
    assert.equal(history.undo(), 'one');
    assert.equal(history.undo(), null);
});

test('saving posts existing template contract with JSON accept and CSRF', async () => {
    let request;
    await postTemplate({ saveUrl: '/template', csrfToken: 'token' }, '<tr></tr>', async (url, options) => {
        request = { url, options };
        return { ok: true, status: 200, json: async () => ({ success: true }) };
    });
    assert.equal(request.url, '/template');
    assert.equal(request.options.headers.Accept, 'application/json');
    assert.equal(request.options.headers['X-CSRF-TOKEN'], 'token');
    assert.deepEqual(JSON.parse(request.options.body), { template: { html: '<tr></tr>' } });
});

test('validation, server, expired-session and malformed responses are failures', async () => {
    for (const status of [422, 500, 419]) {
        await assert.rejects(postTemplate({ saveUrl: '/', csrfToken: 'token' }, 'work', async () => ({ ok: false, status, json: async () => ({ success: true }) })));
    }
    await assert.rejects(postTemplate({ saveUrl: '/' }, 'work', async () => ({ ok: true, status: 200, json: async () => { throw new Error('html response'); } })), /Tu trabajo sigue/);
    await assert.rejects(postTemplate({ saveUrl: '/' }, 'work', async () => ({ ok: true, status: 200, json: async () => ({ success: false }) })), /Tu trabajo sigue/);
});

test('network failures propagate without a false save success', async () => {
    await assert.rejects(postTemplate({ saveUrl: '/' }, 'work', async () => { throw new TypeError('offline'); }), /offline/);
});
