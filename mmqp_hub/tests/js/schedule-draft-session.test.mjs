import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { randomUUID } from 'node:crypto';

function harness(handler) {
    const calls = [];
    const context = vm.createContext({
        console, crypto: { randomUUID }, setTimeout, clearTimeout,
        document: { querySelector: () => ({ content: 'csrf' }), addEventListener: () => {} },
        fetch: async (url, options) => {
            const body = JSON.parse(options.body);
            calls.push({ url, body });
            return handler(url, body, calls.length);
        },
    });
    vm.runInContext(readFileSync(new URL('../../public/js/schedule-draft-session.js', import.meta.url), 'utf8'), context);
    return { Session: context.ScheduleDraftSession, calls, context };
}
const response = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });
const draft = (id = 10, week = '2026-W41') => ({ id, semana_id: week, version: 1, estado: 'borrador' });
const content = value => ({ plantilla_diseno: [value], empleados_snapshot: [], fecha_inicio: '2026-10-05', fecha_fin: '2026-10-11' });
const tick = () => new Promise(resolve => setImmediate(resolve));

test('loading a draft never triggers a save', async () => {
    const { Session, calls } = harness(() => response({}));
    const session = new Session(draft());
    session.markLoaded(content('initial'));
    await session.save(content('initial'));
    assert.equal(calls.length, 0);
});

test('requests capture their draft/week and serialize versions despite later edits', async () => {
    let release;
    const { Session, calls } = harness((url, body, index) => index === 1
        ? new Promise(resolve => { release = () => resolve(response({ draft: { version: 2 } })); })
        : response({ draft: { version: 3 } }));
    const session = new Session(draft());
    session.markLoaded(content('initial'));
    const firstContent = content('first');
    const first = session.save(firstContent);
    const second = session.save(content('second'));
    firstContent.plantilla_diseno[0] = 'mutated after request';
    await tick();
    assert.equal(calls.length, 1);
    assert.equal(calls[0].body.plantilla_diseno[0], 'first');
    assert.equal(calls[0].body.draft_id, 10);
    assert.equal(calls[0].body.semana_id, '2026-W41');
    release();
    await Promise.all([first, second]);
    assert.equal(calls[1].body.version, 2);
    assert.equal(session.version, 3);
    assert.equal(session.dirty(content('second')), false);
});

test('an older save response cannot mark later editor changes saved', async () => {
    let release;
    const { Session } = harness(() => new Promise(resolve => { release = () => resolve(response({ draft: { version: 2 } })); }));
    const session = new Session(draft());
    session.markLoaded(content('initial'));
    const save = session.save(content('first'));
    await tick();
    release();
    await save;
    assert.equal(session.dirty(content('newer unsaved')), true);
});

test('a lost save response retries the same version and key before newer changes', async () => {
    const { Session, calls } = harness((url, body, index) => {
        if (index === 1) throw new Error('Network response lost');
        return response({ draft: { version: index } });
    });
    const session = new Session(draft());
    session.markLoaded(content('initial'));
    await assert.rejects(session.save(content('first')));
    await session.save(content('second'));
    assert.deepEqual(calls[0].body, calls[1].body);
    assert.equal(calls[2].body.version, 2);
    assert.notEqual(calls[2].body.request_id, calls[0].body.request_id);
});

test('publication waits for pending saves, flushes latest content and consumes the ID', async () => {
    let release;
    const { Session, calls } = harness((url, body, index) => {
        if (index === 1) return new Promise(resolve => { release = () => resolve(response({ draft: { version: 2 } })); });
        if (url.endsWith('/publicar')) return response({ redirect_url: '/published' });
        return response({ draft: { version: 3 } });
    });
    const session = new Session(draft());
    session.markLoaded(content('initial'));
    const save = session.save(content('first'));
    const publish = session.publish(content('latest'), ['encargado']);
    await assert.rejects(session.save(content('too late')));
    await tick();
    assert.equal(calls.length, 1);
    release();
    await save;
    await publish;
    assert.equal(calls[1].body.plantilla_diseno[0], 'latest');
    assert.equal(calls[2].body.version, 3);
    assert.equal(calls[2].url, '/admin/horarios/resultado/10/publicar');
    assert.equal(session.closed, true);
    await assert.rejects(session.save(content('after publishing')));
    assert.equal(calls.length, 3);
});

test('a two-tab conflict stops publication and preserves unsaved local content', async () => {
    const { Session, calls } = harness(() => response({ message: 'Conflicto: revisa otra pestaña.' }, 409));
    const session = new Session(draft());
    session.markLoaded(content('initial'));
    await assert.rejects(session.publish(content('local changes'), ['encargado']), /Conflicto/);
    assert.equal(session.blocked, true);
    assert.equal(session.dirty(content('local changes')), true);
    assert.equal(calls.length, 1);
    assert.ok(!calls.some(call => call.url.endsWith('/publicar')));
});

test('publishing an unchanged editor still saves and validates its exact snapshot first', async () => {
    const { Session, calls } = harness((url) => url.endsWith('/publicar')
        ? response({ redirect_url: '/published' }) : response({ draft: { version: 2 } }));
    const session = new Session(draft());
    session.markLoaded(content('loaded'));
    await session.publish(content('loaded'), ['encargado']);
    assert.equal(calls.length, 2);
    assert.equal(calls[0].url, '/api/horarios-semanales/borrador');
    assert.equal(calls[1].body.version, 2);
});

test('a corrected validation error can be saved without retrying the rejected body', async () => {
    const { Session, calls } = harness((url, body, index) => index === 1
        ? response({ message: 'Hora inválida' }, 422) : response({ draft: { version: 2 } }));
    const session = new Session(draft());
    session.markLoaded(content('loaded'));
    await assert.rejects(session.save(content('invalid')));
    await session.save(content('corrected'));
    assert.equal(calls.length, 2);
    assert.equal(calls[1].body.plantilla_diseno[0], 'corrected');
    assert.equal(calls[1].body.version, 1);
});

test('sessions from different weeks remain independent with out-of-order responses', async () => {
    const releases = [];
    const { Session, calls } = harness((url, body) => new Promise(resolve => {
        releases.push(() => resolve(response({ draft: { version: body.version + 1 } })));
    }));
    const first = new Session(draft(10));
    const second = new Session(draft(20, '2026-W42'));
    first.markLoaded(content('initial')); second.markLoaded(content('initial'));
    const a = first.save(content('week 41'));
    const b = second.save(content('week 42'));
    await tick();
    releases[1](); await b;
    releases[0](); await a;
    assert.equal(calls[0].body.draft_id, 10);
    assert.equal(calls[1].body.draft_id, 20);
    assert.equal(first.dirty(content('week 41')), false);
    assert.equal(second.dirty(content('week 42')), false);
});

test('designer hydration preserves result assignments even with a stored manual position', async () => {
    const { context } = harness(() => response({}));
    let factory;
    context.Alpine = { data: (name, value) => { factory = value; } };
    context.localStorage = { getItem: () => null };
    context.document.addEventListener = (name, callback) => callback();
    vm.runInContext(readFileSync(new URL('../../public/js/horarios-designer.js', import.meta.url), 'utf8'), context);
    const designer = factory([]);
    const stored = { plantilla_diseno: [{ dia: 'lunes', turnos_requeridos: [{ turno_id: 'stable', puesto_num: 3,
        rol_necesario: 'encargado', hora_inicio: '09:00', hora_fin: '17:00' }] }],
        resultados: [{ turno_id: 'stable', empleado_id: 9, nombre: 'Manual', hora_inicio: '11:30', hora_fin: '19:30' }] };
    const turns = designer.extraerTurnosDeSemana(stored, 'lunes');
    assert.equal(turns[0].puesto_num, 3);
    assert.equal(turns[0].empleado_id, 9);
    assert.equal(turns[0].hora_inicio, '11:30');
    assert.equal(turns[0].id, 'stable');
    assert.equal(turns[0].turno_id, 'stable');
});

function resultEditor(data) {
    const { context } = harness(() => response({}));
    let factory;
    context.Alpine = { data: (name, value) => { factory = value; } };
    context.window = { addEventListener: () => {} };
    context.document.addEventListener = (name, callback) => { if (name === 'alpine:init') callback(); };
    let source = readFileSync(new URL('../../resources/views/admin/horarios-resultado/scripts.blade.php', import.meta.url), 'utf8');
    source = source.match(/<script>([\s\S]*)<\/script>/)[1].replace(/\{\{ auth\(\)->user\(\)->es_admin \? 'true' : 'false' \}\}/g, 'true');
    vm.runInContext(source, context);
    const editor = factory(data, true);
    editor.setupAutoScroll = () => {};
    editor.$watch = () => {};
    editor.init();
    return editor;
}

test('result editor recovers assignments from old nested autosave data', () => {
    const shift = { turno_id: 'old-manual', rol_necesario: 'encargado', hora_inicio: '11:00', hora_fin: '17:00',
        empleado_id: 9, empleado_nombre: 'Manual', duracion_horas: 6 };
    const layout = [{ dia: 'lunes', fecha: '2026-10-05', notas: 'Keep', turnos_requeridos: [shift] }];
    const editor = resultEditor({ estado: 'borrador', horario_id: 10, version: 1,
        metadatos: { semana_id: '2026-W41', fecha_inicio: '2026-10-05', fecha_fin: '2026-10-11' },
        plantilla: layout, resultados: layout, empleados: [{ id: 9, nombre: 'Manual', rol: 'encargado' }] });
    assert.equal(editor.turnosUnidos.length, 1);
    assert.equal(editor.draftContent().resultados[0].empleado_id, 9);
    assert.equal(editor.draftContent().plantilla_diseno[0].notas, 'Keep');
});

test('an imported layout keeps unassigned shifts visible without crashing initialization', () => {
    const shift = { turno_id: 'unassigned', rol_necesario: 'encargado',
        hora_inicio: '20:00', hora_fin: '02:30', duracion_horas: 6.5, empleado_id: null };
    const editor = resultEditor({ estado: 'historico', plantilla: [{ dia: 'lunes', turnos_requeridos: [shift] }],
        resultados: [], empleados: [] });
    assert.equal(editor.turnosUnidos.length, 0);
    assert.equal(editor.turnosNoCubiertos.length, 1);
    assert.equal(editor.turnosNoCubiertos[0].hora_inicio, '20:00');
    assert.equal(editor.turnosNoCubiertos[0].hora_fin, '02:30');
    assert.equal(editor.esAdmin, false);
});

test('result serialization preserves moves, additions, removals and resized shifts', () => {
    const shifts = ['keep', 'remove'].map(turno_id => ({ turno_id, rol_necesario: 'encargado', hora_inicio: '09:00',
        hora_fin: '17:00', duracion_horas: 8 }));
    const editor = resultEditor({ estado: 'borrador', horario_id: 10, version: 1,
        metadatos: { semana_id: '2026-W41', fecha_inicio: '2026-10-05', fecha_fin: '2026-10-11' },
        plantilla: [{ dia: 'lunes', notas: 'Keep', turnos_requeridos: shifts }],
        resultados: shifts.map(shift => ({ turno_id: shift.turno_id, empleado_id: 9, nombre: 'Manual' })),
        empleados: [{ id: 9, nombre: 'Manual', rol: 'encargado' }] });
    editor.turnosUnidos = [{ ...editor.turnosUnidos[0], dia: 'martes', hora_inicio: '12:00', duracion_horas: 5 },
        { turno_id: 'added', dia: 'jueves', rol: 'encargado', empleado_id: 9, empleado_nombre: 'Manual',
            hora_inicio: '13:00', hora_fin: '18:00', duracion_horas: 5 }];
    const stored = editor.draftContent();
    assert.equal(stored.plantilla_diseno.find(day => day.dia === 'lunes').turnos_requeridos.length, 0);
    assert.equal(stored.plantilla_diseno.find(day => day.dia === 'martes').turnos_requeridos[0].hora_inicio, '12:00');
    assert.equal(stored.plantilla_diseno.find(day => day.dia === 'jueves').turnos_requeridos[0].turno_id, 'added');
    assert.equal(stored.resultados.length, 2);
    assert.ok(!stored.resultados.some(shift => shift.turno_id === 'remove'));
});


test('developer admin role stays out of calendar sections and print options, including old snapshots', () => {
    const editor = resultEditor({estado:'historico',
        empleados:[{id:1,nombre:'Developer',rol:'admin'}, {id:2,nombre:'Encargada',rol:'encargado',es_admin:true}],
        resultados:[{turno_id:'old',empleado_id:1,rol:'admin'}],
        plantilla:[{dia:'lunes',turnos_requeridos:[{turno_id:'old',rol_necesario:'admin',hora_inicio:'12:00',hora_fin:'16:00'}]}]});
    assert.deepEqual(Array.from(editor.rolesDisponibles),['encargado']);
    assert.deepEqual(Array.from(editor.rolesPublicables),['encargado']);
    assert.equal(editor.configImpresion.admin, undefined);
});
