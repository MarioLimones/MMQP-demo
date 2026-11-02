import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

function contextWithFactory() {
    let factory;
    const alerts = [];
    const context = vm.createContext({
        document: {addEventListener: (_, callback) => callback(), querySelector: () => ({content: 'csrf'})},
        Alpine: {data: (_, value) => {factory = value;}},
        localStorage: {getItem: () => null}, console, setTimeout, clearTimeout,
        window: {addEventListener() {}}, alert: message => alerts.push(message),
        confirm: () => {throw new Error('Holiday opt-out must not offer an override');},
    });
    return {context, alerts, factory: () => factory};
}

test('designer counts and manual assignment distinguish configured holidays from normal days', () => {
    const harness = contextWithFactory();
    vm.runInContext(readFileSync(new URL('../../public/js/horarios-designer.js', import.meta.url), 'utf8'), harness.context);
    const employee = {id: 1, rol: 'auxiliar', disponibilidad_festivos: 'no_disponible', activo: true};
    const designer = harness.factory()([employee], [], ['2026-10-19']);
    designer.fechasISO = {lunes: '2026-10-19', martes: '2026-10-20'};
    designer.diaSeleccionado = 'lunes';
    assert.equal(designer.getEmpleadosTotalCount('lunes', 'auxiliar'), 0);
    assert.equal(designer.getEmpleadosTotalCount('martes', 'auxiliar'), 1);
    const shift = {rol: 'auxiliar'};
    designer.asignarEmpleadoDrop({preventDefault() {}, dataTransfer: {getData: () => JSON.stringify(employee)}}, shift);
    assert.equal(shift.empleado_id, undefined);
    assert.match(harness.alerts[0], /festivos/);
    designer.diaSeleccionado = 'martes';
    designer.asignarEmpleadoDrop({preventDefault() {}, dataTransfer: {getData: () => JSON.stringify(employee)}}, shift);
    assert.equal(shift.empleado_id, 1);
});

test('result editor blocks new holiday shifts and labels short dates by the selected ISO week', () => {
    const harness = contextWithFactory();
    vm.runInContext(readFileSync(new URL('../../public/js/absence-dates.js', import.meta.url), 'utf8'), harness.context);
    let source = readFileSync(new URL('../../resources/views/admin/horarios-resultado/scripts.blade.php', import.meta.url), 'utf8');
    source = source.match(/<script>([\s\S]*)<\/script>/)[1].replace(/\{\{ auth\(\)->user\(\)->es_admin \? 'true' : 'false' \}\}/g, 'true');
    vm.runInContext(source, harness.context);
    const employee = {id: 1, rol: 'auxiliar', disponibilidad_festivos: 'no_disponible'};
    const editor = harness.factory()({empleados: [employee], festivos: ['2026-10-19'],
        metadatos: {semana_id: '2026-W43'}, plantilla: [], resultados: []}, true);
    assert.equal(editor.isFestivoNoDisponible(employee, '19 oct', 'lunes'), true);
    assert.equal(editor.isFestivoNoDisponible(employee, '20 oct', 'martes'), false);
    editor.turnoForm = {empleado_id: 1, dia: 'lunes', hora_inicio: '12:00', hora_fin: '16:00'};
    editor.guardarTurnoModal();
    assert.match(editor.turnoFormError, /festivos/);
    assert.equal(editor.resultados.length, 0);
});
