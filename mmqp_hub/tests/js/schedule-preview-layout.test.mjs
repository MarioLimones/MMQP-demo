import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function designer(turnos) {
    let create;
    const context = vm.createContext({
        document: { addEventListener: (_, callback) => callback() },
        Alpine: { data: (_, factory) => { create = factory; } },
        localStorage: { getItem: () => null },
    });
    vm.runInContext(readFileSync(new URL('../../public/js/horarios-designer.js', import.meta.url), 'utf8'), context);
    const instance = create([]);
    instance.turnos.lunes = turnos;
    instance.previewRol = 'repartidor';
    return instance;
}

const shift = (id, puesto, start, end, rol = 'repartidor') => ({
    id, puesto_num: puesto, hora_inicio: start, hora_fin: end, rol,
});

test('a single shift fills its day regardless of the original position number', () => {
    const turno = shift('cleaner', 11, '10:00', '14:00', 'limpiadora');
    const instance = designer([turno]);
    const [entry] = instance.getPreviewLayout('lunes', 'limpiadora');
    assert.equal(entry.columns, 1);
    assert.equal(entry.column, 0);
    assert.equal(instance.getColumnForTurno(turno, 'lunes'), 10);
    assert.equal(turno.puesto_num, 11);
});

test('separate morning and evening groups reuse all available width', () => {
    const turnos = [
        shift('evening-1', 6, '18:00', '22:00'),
        shift('morning', 9, '10:00', '14:00'),
        shift('evening-2', 10, '18:00', '22:00'),
    ];
    const before = JSON.stringify(turnos);
    const instance = designer(turnos);
    const layout = instance.getPreviewLayout('lunes', 'repartidor');
    const morning = layout.find(entry => entry.turno.id === 'morning');
    assert.equal(morning.columns, 1);
    const evening = layout.filter(entry => entry.turno.id.startsWith('evening'));
    assert.ok(evening.every(entry => entry.columns === 2));
    assert.equal(new Set(evening.map(entry => entry.column)).size, 2);
    assert.equal(JSON.stringify(turnos), before);
});

test('columns are reused safely across chained overlaps and touching shifts', () => {
    const instance = designer([
        shift('first', 6, '10:00', '12:00'),
        shift('bridge', 9, '11:00', '14:00'),
        shift('next', 10, '12:00', '13:00'),
        shift('later', 11, '14:00', '16:00'),
    ]);
    const layout = instance.getPreviewLayout('lunes', 'repartidor');
    assert.equal(layout.find(entry => entry.turno.id === 'later').columns, 1);
    assert.equal(Math.max(...layout.map(entry => entry.columns)), 2);
    for (const a of layout) {
        for (const b of layout) {
            if (a !== b && a.start < b.end && b.start < a.end) {
                assert.notEqual(a.column, b.column);
            }
        }
    }
});

test('overnight overlaps use the same time scale as the calendar', () => {
    const instance = designer([
        shift('late', 20, '22:00', '02:00'),
        shift('night', 25, '00:00', '03:00'),
        shift('morning', 30, '10:00', '14:00'),
    ]);
    const layout = instance.getPreviewLayout('lunes', 'repartidor');
    assert.ok(layout.filter(entry => entry.turno.id !== 'morning').every(entry => entry.columns === 2));
    assert.equal(layout.find(entry => entry.turno.id === 'morning').columns, 1);
});

test('minimum grid width follows actual concurrency, ignores other roles and supports empty days', () => {
    const instance = designer([
        shift('one', 10, '18:00', '22:00'),
        shift('two', 20, '18:00', '22:00'),
        shift('three', 30, '18:00', '22:00'),
        shift('other-role', 99, '18:00', '22:00', 'auxiliar'),
    ]);
    const layout = instance.getPreviewLayout('lunes', 'repartidor');
    assert.equal(layout.length, 3);
    assert.ok(layout.every(entry => entry.columns === 3));
    assert.equal(instance.getPreviewLayout('martes', 'repartidor').length, 0);
    const widths = instance.getPreviewDayWidths();
    assert.ok(widths[0] / 3 - 6 >= 58, 'stacked time labels must remain readable');
    assert.equal(widths[1], 112, 'one busy day must not widen the other six days');
    assert.equal(instance.getPreviewGridMinWidth(), 864);
    instance.previewRol = 'limpiadora';
    assert.equal(instance.getPreviewGridMinWidth(), 784);
});

test('preview positions match hour lines without changing the daily editor scale', () => {
    const turno = shift('morning', 1, '10:00', '14:00');
    const instance = designer([turno]);
    const original = instance.getTurnoStyle(turno);
    const style = instance.getPreviewTurnoStyle(turno, 'lunes');
    assert.match(style, /top: calc\(var\(--preview-hour-height\) \* 1 \+ 1px\)/);
    assert.match(style, /height: calc\(var\(--preview-hour-height\) \* 4 - 2px\)/);
    assert.equal(instance.getTurnoStyle(turno), original);
});

test('late shifts align with midnight and end at the final 03:00 line', () => {
    const turno = shift('night', 7, '23:30', '03:00');
    const instance = designer([turno]);
    const style = instance.getPreviewTurnoStyle(turno, 'lunes');
    assert.match(style, /\* 14\.5 \+ 1px/);
    assert.match(style, /\* 3\.5 - 2px/);
});

test('short simultaneous shifts retain space for both times on a single line', () => {
    const instance = designer([
        shift('short', 1, '10:00', '10:30'),
        shift('long', 2, '10:00', '14:00'),
    ]);
    assert.equal(instance.getPreviewDayWidths()[0], 256);
    assert.equal(instance.getPreviewDayWidths()[1], 112);
    assert.match(instance.getPreviewGridStyle(), /minmax\(256px, 256fr\)/);
    assert.match(instance.getPreviewTurnoStyle(instance.turnos.lunes[0], 'lunes'), /\* 0\.5 - 2px/);
});
