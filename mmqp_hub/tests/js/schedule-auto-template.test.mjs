import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {randomUUID} from 'node:crypto';

function setup(reply, {ok = true, stored = null, pending = null} = {}) {
    let factory;
    const requests = [];
    const dialogs = Object.fromEntries(['autoTemplateDialog', 'autoTemplateLoadingDialog', 'autoTemplateSuccessDialog']
        .map(name => [name, {open: false, showModal() {this.open = true;}, close() {this.open = false;}}]));
    const context = vm.createContext({
        document: {addEventListener: (_, callback) => callback(), querySelector: () => ({getAttribute: () => 'csrf'})},
        Alpine: {data: (_, value) => {factory = value;}}, localStorage: {getItem: () => stored, setItem() {}},
        fetch: async (url, options) => {
            requests.push({url, ...options, body: JSON.parse(options.body)});
            if (pending) await pending;
            return {ok, json: async () => url === '/api/generar-horario'
                ? {success: true, redirect_url: '/horario-generado'} : reply};
        },
        console: {log() {}, error() {}}, setTimeout, clearTimeout,
        crypto: {randomUUID}, window: {location: {href: ''}},
        setInterval: () => 1, clearInterval() {}, alert: message => {throw new Error(message);},
    });
    vm.runInContext(readFileSync(new URL('../../public/js/horarios-designer.js', import.meta.url), 'utf8'), context);
    const designer = factory([]);
    Object.assign(designer, {$refs: dialogs, semana: '2026-W43', draftSession: {id: 1}, isLoadingDraft: false});
    designer.turnos.lunes = [{id: 'old', rol: 'auxiliar', hora_inicio: '09:00', hora_fin: '13:00'}];
    return {designer, dialogs, requests, context};
}

const reply = () => ({success: true, plantilla: {lunes: [{rol: 'auxiliar', hora_inicio: '20:00', hora_fin: '00:30'}]},
    resumen: [{id: 1, minutos_objetivo: 270, minutos_generados: 270}], avisos: ['Revisar huecos'],
    modelo_historico: {estado: 'entrenado', turnos_utilizados: 27000, anios: [2023, 2024, 2025, 2026], fecha_hasta: '2026-10-15', referencias: [{fecha: '2026-10-12', dia: 'lunes', rol: 'auxiliar'}]},
    huecos: [{dia: 'martes', rol: 'auxiliar', inicio: '12:00', fin: '16:30'}]});

test('sends edited hours and closed days, applies unassigned shifts and displays coverage warnings', async () => {
    const {designer, dialogs, requests} = setup(reply());
    designer.abrirAutoPlantilla();
    designer.autoTemplateHorario.martes.abierto = false;
    designer.autoTemplateHorario.lunes.franjas[0].inicio = '11:30';
    await designer.generarPlantillaIA();
    assert.equal(requests[0].method, 'POST');
    assert.deepEqual(requests[0].body.horario.martes, []);
    assert.equal(requests[0].body.horario.lunes[0].inicio, '11:30');
    assert.equal(designer.turnos.lunes.length, 1);
    assert.equal(designer.turnos.lunes[0].hora_fin, '00:30');
    assert.equal(designer.turnos.lunes[0].empleado_id, null);
    assert.equal(designer.autoTemplateAvisos[0], 'Revisar huecos');
    assert.equal(designer.autoTemplateHuecos.length, 1);
    assert.equal(designer.autoTemplateModelo.estado, 'entrenado');
    assert.equal(designer.autoTemplateModelo.referencias[0].fecha, '2026-10-12');
    assert.equal(dialogs.autoTemplateSuccessDialog.open, true);
    assert.equal(dialogs.autoTemplateLoadingDialog.open, false);
});

test('minor reservations survive template application, draft saving, reload and final generation while other shifts stay empty', async () => {
    const data = reply();
    data.plantilla.lunes = [
        {rol: 'auxiliar', hora_inicio: '12:00', hora_fin: '16:00', empleado_id: 1, reserva_menor: true},
        {rol: 'auxiliar', hora_inicio: '19:00', hora_fin: '22:00', empleado_id: 1, reserva_menor: true},
        {rol: 'auxiliar', hora_inicio: '13:00', hora_fin: '17:00', empleado_id: 2, reserva_menor: true},
        {rol: 'auxiliar', hora_inicio: '19:00', hora_fin: '22:00', empleado_id: 2, reserva_menor: true},
        {rol: 'auxiliar', hora_inicio: '12:00', hora_fin: '16:00'},
        {rol: 'auxiliar', hora_inicio: '19:00', hora_fin: '00:30'},
    ];
    const {designer, requests, context} = setup(data);
    designer.fechasISO = {lunes: '2026-10-19', domingo: '2026-10-25'};
    let savedDraft;
    designer.draftSession.save = async draft => {savedDraft = draft;};
    await designer.generarPlantillaIA();

    const shifts = designer.turnos.lunes;
    assert.equal(shifts.filter(t => t.empleado_id === 1).length, 2);
    assert.equal(shifts.filter(t => t.empleado_id === 2).length, 2);
    assert.equal(shifts.filter(t => t.empleado_id === null).length, 2);
    assert.ok(shifts.filter(t => t.empleado_id).every(t => t.reserva_menor === true));
    assert.ok(shifts.filter(t => !t.empleado_id).every(t => t.reserva_menor === false));
    const reservedPuestos = new Set(shifts.filter(t => t.empleado_id).map(t => t.puesto_num));
    assert.equal(reservedPuestos.size, 2);
    assert.ok(shifts.filter(t => !t.empleado_id).every(t => !reservedPuestos.has(t.puesto_num)));

    const stored = JSON.parse(JSON.stringify(designer.draftContent()));
    designer.turnos.lunes = [];
    designer.turnos.lunes = designer.extraerTurnosDeSemana(stored, 'lunes');
    assert.deepEqual(Array.from(designer.turnos.lunes, t => t.reserva_menor),
        Array.from(shifts, t => t.reserva_menor));
    await designer.generarHorario();
    await new Promise(resolve => setImmediate(resolve));
    const finalShifts = requests[1].body.plantilla_diseno[0].turnos_requeridos;
    assert.deepEqual(finalShifts.map(t => t.empleado_id), Array.from(shifts, t => t.empleado_id));
    assert.deepEqual(Array.from(savedDraft.plantilla_diseno[0].turnos_requeridos, t => t.empleado_id),
        finalShifts.map(t => t.empleado_id));
    assert.deepEqual(finalShifts.map(t => t.reserva_menor), Array.from(shifts, t => t.reserva_menor));
    assert.deepEqual(Array.from(savedDraft.plantilla_diseno[0].turnos_requeridos, t => t.reserva_menor),
        finalShifts.map(t => t.reserva_menor));
    assert.equal(context.window.location.href, '/horario-generado');
});

test('manual assignments stay ordinary and loading a template without employees clears minor reservations', async () => {
    const {designer, requests} = setup(reply());
    designer.turnos.lunes = [{id: 'manual', rol: 'auxiliar', hora_inicio: '19:00', hora_fin: '22:00', empleado_id: 1}];
    designer.draftSession.save = async () => {};
    const stored = JSON.parse(JSON.stringify(designer.draftContent()));
    designer.turnos.lunes = designer.extraerTurnosDeSemana(stored, 'lunes');
    assert.equal(designer.turnos.lunes[0].empleado_id, 1);
    assert.equal(designer.turnos.lunes[0].reserva_menor, false);
    await designer.generarHorario();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(requests[0].body.plantilla_diseno[0].turnos_requeridos[0].reserva_menor, false);

    designer.diaSeleccionado = 'lunes';
    designer.applyPlantilla([{id: 'reserved', empleado_id: 1, reserva_menor: true}]);
    designer.confirmarCargaPlantilla(false);
    assert.equal(designer.turnos.lunes[0].empleado_id, null);
    assert.equal(designer.turnos.lunes[0].reserva_menor, false);
});

test('empty availability and API failures preserve the previous shifts', async () => {
    for (const [data, ok] of [[{...reply(), plantilla: {lunes: []}}, true], [{success: false, message: 'Franjas solapadas'}, false]]) {
        const {designer, dialogs} = setup(data, {ok});
        await designer.generarPlantillaIA();
        assert.equal(designer.turnos.lunes[0].id, 'old');
        assert.equal(designer.isGeneratingTemplate, false);
        assert.equal(dialogs.autoTemplateLoadingDialog.open, false);
        if (!ok) {
            assert.equal(designer.autoTemplateError, 'Franjas solapadas');
            assert.equal(dialogs.autoTemplateDialog.open, true);
        }
    }
});

test('a delayed response cannot replace a different week or draft', async () => {
    let release;
    const pending = new Promise(resolve => {release = resolve;});
    const {designer} = setup(reply(), {pending});
    const request = designer.generarPlantillaIA();
    designer.semana = '2026-W44';
    release();
    await request;
    assert.equal(designer.turnos.lunes[0].id, 'old');
});

test('invalid stored settings do not prevent opening the form', () => {
    const {designer, dialogs} = setup(reply(), {stored: '{bad json'});
    designer.abrirAutoPlantilla();
    assert.equal(designer.autoTemplateHorario.lunes.franjas.length, 2);
    assert.equal(dialogs.autoTemplateDialog.open, true);
});
