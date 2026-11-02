import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { randomUUID } from 'node:crypto';

const employees = () => [1, 2, 3, 4].map(id => ({id, nombre: `Auxiliar ${id}`, rol: 'auxiliar',
    horas_mensuales: id === 4 ? 80 : 160, activo: true, en_baja: false}));
const tick = () => new Promise(resolve => setImmediate(resolve));

function designerFor(roster, live = roster) {
    let factory;
    const requests = [];
    const alerts = [];
    const context = vm.createContext({
        console: {log() {}, error() {}}, crypto: {randomUUID},
        localStorage: {getItem: () => null}, window: {location: {href: ''}},
        document: {addEventListener: (event, callback) => callback(),
            querySelector: () => ({getAttribute: () => 'csrf'})},
        Alpine: {data: (name, value) => { factory = value; }},
        setInterval: () => 1, clearInterval() {}, clearTimeout() {},
        alert: message => alerts.push(message),
        fetch: async (url, options) => {
            const body = JSON.parse(options.body);
            requests.push(body);
            const available = live.reduce((sum, emp) => sum + (body.ajustes_manuales[emp.id] ?? emp.horas_mensuales / 4), 0);
            const data = available === 48 ? {success:true, redirect_url:'/result'} : {
                requires_confirmation:true, requeridas:{auxiliar:48}, disponibles:{auxiliar:available},
                deficit:{auxiliar:48-available}, exceso:{}, empleados_disponibles:live,
            };
            return {ok:true, json:async () => data};
        },
    });
    vm.runInContext(readFileSync(new URL('../../public/js/horarios-designer.js', import.meta.url), 'utf8'), context);
    const designer = factory(roster);
    designer.semana = '2026-W43';
    designer.isLoadingDraft = false;
    designer.draftSession = {id:10, version:1, save:async () => {}};
    designer.fechasISO = {lunes:'2026-10-19', domingo:'2026-10-25'};
    designer.turnos.lunes = [{id:'shift', rol:'auxiliar', hora_inicio:'09:00', hora_fin:'17:00'}];
    return {designer, requests, alerts, context};
}

test('balancing and generation use the same current employees despite a stale draft roster', async () => {
    const roster = employees(); // The old draft still says all four are active.
    const live = roster.slice(0, 3);
    const {designer, requests, alerts, context} = designerFor(roster, live);
    designer.ajustesManuales = {1:12, 2:12, 3:12, 4:12};
    await designer.generarHorario();
    await tick();
    assert.equal(designer.confirmData.deficit.auxiliar, 12);
    designer.autoBalancearRol('auxiliar');
    assert.equal(designer.confirmData.disponibles.auxiliar, 48);
    assert.equal(designer.ajustesManuales[4], 12); // Never redistribute to the excluded employee.
    await designer.generarHorario();
    await tick();
    assert.equal(requests.length, 2);
    assert.equal(live.reduce((sum, emp) => sum + requests[1].ajustes_manuales[emp.id], 0), 48);
    assert.equal(context.window.location.href, '/result');
    assert.deepEqual(alerts, []);
});

test('the manual editor omits inactive employees before any generation request', () => {
    const roster = employees();
    roster[3].activo = false;
    const {designer} = designerFor(roster);
    assert.deepEqual(Array.from(designer.getEmpleadosDisponibles('auxiliar'), e => e.id), [1,2,3]);
    assert.deepEqual(Array.from(designer.getEmpleadosAjuste('auxiliar'), e => e.id), [1,2,3]);
});

test('weekly hours stay empty until explicitly entered or autocompleted, including workers without contracts', () => {
    const roster = employees().slice(0, 3);
    roster[2].horas_mensuales = null;
    const {designer} = designerFor(roster);
    designer.abrirAjusteManual();
    assert.deepEqual(Object.keys(designer.ajustesManuales), []);
    assert.deepEqual(roster.map(e => designer.getHorasSemanalesActuales(e)), [0, 0, 0]);
    designer.confirmData.requeridas.auxiliar = 48;
    designer.autoBalancearRol('auxiliar');
    assert.deepEqual(roster.map(e => designer.ajustesManuales[e.id]), [24, 24, 0]);
    designer.setHorasSemanales(roster[0], '0');
    assert.equal(designer.ajustesManuales[1], 0);
    designer.setHorasSemanales(roster[0], '');
    assert.equal(Object.hasOwn(designer.ajustesManuales, 1), false);
    designer.sumarHorasSemanales(roster[0]);
    assert.equal(designer.ajustesManuales[1], 0.5);
});

test('missing weekly hours open the manual editor without copying contract values', async () => {
    const {designer, context} = designerFor(employees());
    context.fetch = async () => ({ok: true, json: async () => ({
        requires_confirmation: true, requires_hour_input: true, roles_sin_horas: ['auxiliar'],
        requeridas: {auxiliar: 48}, disponibles: {auxiliar: 0}, empleados_disponibles: employees(),
    })});
    await designer.generarHorario();
    await tick();
    assert.equal(designer.isAjusteManualOpen, true);
    assert.equal(designer.showConfirmModal, false);
    assert.equal(designer.ajusteRole, 'auxiliar');
    assert.deepEqual(Object.keys(designer.ajustesManuales), []);
});

test('server roster includes new employees and respects locks while balancing', () => {
    const roster = employees();
    const current = [roster[0], roster[1], {...roster[2], id:9}];
    const {designer} = designerFor(roster);
    designer.empleadosAjuste = current;
    designer.confirmData.requeridas.auxiliar = 48;
    designer.ajustesManuales = {1:12,2:12,3:12,4:12,9:12};
    designer.ajustesBloqueados[1] = true;
    designer.autoBalancearRol('auxiliar');
    assert.equal(designer.ajustesManuales[1], 12);
    assert.equal(designer.ajustesManuales[2], 18);
    assert.equal(designer.ajustesManuales[9], 18);
    assert.equal(designer.ajustesManuales[3], 12);
    assert.equal(designer.confirmData.deficit.auxiliar, 0);
});

test('a generation rejection closes the loading state and keeps the editable draft', async () => {
    const {designer, alerts, context} = designerFor(employees());
    const session = designer.draftSession;
    context.fetch = async () => ({ok:false, status:422, json:async () => ({
        success:false, message:'No se encontró solución factible con los datos proporcionados.',
    })});
    await designer.generarHorario();
    await tick();
    assert.equal(designer.isGenerating, false);
    assert.equal(designer.draftSession, session);
    assert.equal(context.window.location.href, '');
    assert.deepEqual(alerts, ['Error al generar: No se encontró solución factible con los datos proporcionados.']);
});

test('batch balancing preserves the existing tie order when adding or removing half hours', () => {
    for (const [required, expected] of [[62, [20.5, 20.5, 21]], [58, [19.5, 19.5, 19]]]) {
        const {designer} = designerFor(employees().slice(0, 3));
        designer.ajustesManuales = {1:20, 2:20, 3:20, 99:7};
        designer.confirmData.requeridas.auxiliar = required;
        designer.autoBalancearRol('auxiliar');
        assert.deepEqual([1,2,3].map(id => designer.ajustesManuales[id]), expected);
        assert.equal(designer.ajustesManuales[99], 7);
        assert.equal(designer.confirmData.disponibles.auxiliar, required);
    }
});

test('a large redistribution publishes once and reads reactive hours only for the initial and final totals', () => {
    const roster = Array.from({length:60}, (_, i) => ({id:i + 1, rol:'auxiliar', horas_mensuales:80, activo:true}));
    const {designer} = designerFor(roster);
    designer.confirmData.requeridas.auxiliar = 1800;
    let hours = designer.ajustesManuales;
    let writes = 0;
    let reads = 0;
    Object.defineProperty(designer, 'ajustesManuales', {
        get: () => { reads++; return hours; },
        set: value => { writes++; hours = value; },
    });
    designer.autoBalancearRol('auxiliar');
    assert.equal(writes, 1);
    assert.ok(reads <= roster.length * 4 + 1);
    assert.ok(roster.every(emp => hours[emp.id] === 30));
    assert.equal(designer.confirmData.disponibles.auxiliar, 1800);
    assert.equal(designer.confirmData.deficit.auxiliar, 0);
});

test('fully locked and already balanced rosters do not publish hour changes', () => {
    const {designer, alerts} = designerFor(employees().slice(0, 3));
    designer.ajustesManuales = {1:0, 2:1.5, 3:4};
    const previous = designer.ajustesManuales;
    designer.ajustesBloqueados = {1:true, 2:true, 3:true};
    designer.confirmData.requeridas.auxiliar = 0;
    designer.autoBalancearRol('auxiliar');
    assert.equal(designer.ajustesManuales, previous);
    assert.equal(alerts.length, 1);
    designer.confirmData.requeridas.auxiliar = 5.5;
    designer.autoBalancearRol('auxiliar');
    assert.equal(designer.ajustesManuales, previous);
    assert.equal(alerts.length, 1);
    assert.equal(designer.confirmData.disponibles.auxiliar, 5.5);
});


test('autocompletar starts from each contract instead of equalizing 40h and 20h workers', () => {
    const roster = employees();
    const {designer} = designerFor(roster);
    designer.confirmData.requeridas.auxiliar = 140;
    designer.autoBalancearRol('auxiliar');
    assert.deepEqual(roster.map(e => designer.ajustesManuales[e.id]), [40, 40, 40, 20]);
});

test('autocompletar keeps minor reservations fixed while filling the remaining adult hours', () => {
    const roster = employees();
    roster[3].es_menor = true;
    const {designer} = designerFor(roster);
    designer.turnos.lunes = [{id:'minor', rol:'auxiliar', hora_inicio:'12:00', hora_fin:'16:00', empleado_id:4, reserva_menor:true}];
    designer.confirmData.requeridas.auxiliar = 124;
    designer.ajustesManuales = {4:31};
    designer.autoBalancearRol('auxiliar');
    assert.deepEqual(roster.map(e => designer.ajustesManuales[e.id]), [40, 40, 40, 4]);
    assert.equal(designer.confirmData.disponibles.auxiliar, 124);
});
