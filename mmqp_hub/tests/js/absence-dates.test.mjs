import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const scope = {};
runInNewContext(readFileSync(new URL('../../public/js/absence-dates.js', import.meta.url), 'utf8'), scope);
const dates = scope.AbsenceDates;

test('matches full ISO dates and never an identically numbered day in another year', () => {
    assert.equal(dates.contains(['2026-11-02'], '2026-11-02'), true);
    assert.equal(dates.contains(['2025-11-02'], '2026-11-02'), false);
    assert.equal(dates.contains(['2025-11-02'], '2 nov', '2026-W45', 'lunes'), false);
    assert.equal(dates.contains(['2026-11-02'], '2 nov', '2026-W45', 'lunes'), true);
});

test('reconstructs civil dates from ISO weeks across years and DST', () => {
    assert.equal(dates.isoDate('3 ene', '2026-W53', 'domingo'), '2027-01-03');
    assert.equal(dates.isoDate('25 oct', '2026-W43', 'domingo'), '2026-10-25');
    assert.equal(dates.isoDate('4 nov', '2026-W45', 'Miércoles'), '2026-11-04');
    assert.equal(dates.contains([], '2 nov', '2026-W45', 'lunes'), false);
    assert.equal(dates.isoDate('2 nov', null, 'lunes'), null);
});

const designerScope = { document: { addEventListener: (_, callback) => callback() },
    Alpine: { data: (_, factory) => { designerScope.factory = factory; } }, localStorage: { getItem: () => null } };
runInNewContext(readFileSync(new URL('../../public/js/horarios-designer.js', import.meta.url), 'utf8'), designerScope);

test('designer blocks the full approved interval with inclusive endpoints and string IDs', () => {
    const designer = designerScope.factory([{ id: 2, fechas_libres_solicitadas: [] }], [
        { user_id: '2', fecha: '2026-11-02', fecha_fin: '2026-11-04', estado: 'aprobada' },
        { user_id: 2, fecha: '2026-11-05', estado: 'pendiente' },
        { user_id: 2, fecha: '2026-11-06', estado: 'rechazada' },
    ]);
    for (const iso of ['2026-11-02', '2026-11-03', '2026-11-04']) {
        designer.fechasISO.lunes = iso;
        assert.equal(designer.isEmpleadoAusente('2', 'lunes'), true);
    }
    for (const iso of ['2026-11-01', '2026-11-05', '2026-11-06']) {
        designer.fechasISO.lunes = iso;
        assert.equal(designer.isEmpleadoAusente(2, 'lunes'), false);
    }
});

test('designer respects independent historical blocks and ongoing sick leave', () => {
    const designer = designerScope.factory([{ id: 2, fechas_libres_solicitadas: ['2026-11-07'] }, { id: 3, en_baja: true }]);
    designer.fechasISO.lunes = '2026-11-07';
    assert.equal(designer.isEmpleadoAusente(2, 'lunes'), true);
    assert.equal(designer.isEmpleadoAusente(3, 'lunes'), true);
});

test('generator sends complete dates even when display labels omit the year', async () => {
    let payload;
    Object.assign(designerScope, {
        clearTimeout() {}, clearInterval() {}, setInterval: () => 1,
        crypto: { randomUUID: () => '00000000-0000-4000-8000-000000000001' },
        console: { log() {}, error() {} },
        alert: message => { throw new Error(message); },
        fetch: async (_, options) => {
            payload = JSON.parse(options.body);
            return { ok: true, json: async () => ({ requires_confirmation: true }) };
        },
    });
    designerScope.document.querySelector = () => null;
    const designer = designerScope.factory([]);
    designer.semana = '2026-W45';
    designer.fechasISO = { lunes: '2026-11-02', domingo: '2026-11-08' };
    designer.fechasDias = { lunes: '2 nov', domingo: '8 nov' };
    designer.draftSession = { id: 1, version: 1, save: async () => {} };
    designer.isLoadingDraft = false;
    designer.turnos.lunes = [{ id: 'test', rol: 'repartidor', hora_inicio: '09:00', hora_fin: '12:00' }];
    await designer.generarHorario();
    assert.equal(payload.plantilla_diseno[0].fecha, '2026-11-02');
    assert.equal(payload.plantilla_diseno[0].turnos_requeridos[0].duracion_horas, 3);
});
