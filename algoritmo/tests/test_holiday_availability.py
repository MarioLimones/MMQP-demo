import datetime as dt
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from generador_horarios import _crear_variables_asignacion, _preparar_turnos, app
from generar_plantilla_ml import DIAS, ventanas_empleado, generar_plantilla
from ortools.sat.python import cp_model


class HolidayAvailabilityTest(unittest.TestCase):
    def employee(self, mode='semanal', **extra):
        return {'id': 1, 'nombre': 'Prueba', 'rol': 'auxiliar', 'horas_mensuales': 16,
                'horas_semanales_override': 4, 'tipo_disponibilidad': 'personalizada',
                'dias_disponibles': {'martes': ['tarde']}, 'disponibilidad_festivos': mode, **extra}

    def layout(self, preassigned=False):
        return [{'dia': day, 'fecha': date, 'turnos_requeridos': [
            {'turno_id': day, 'hora_inicio': '17:00', 'hora_fin': '21:00',
             'rol_necesario': 'auxiliar', 'duracion_horas': 4,
             **({'empleado_id': 1} if preassigned else {})}]
        } for day, date in [('lunes', '2026-10-19'), ('martes', '2026-10-20')]]

    def assignments(self, employee, holidays=('2026-10-19',)):
        shifts = _preparar_turnos(self.layout(), {'semana_id': '2026-W43', 'festivos': list(holidays)})
        variables, _ = _crear_variables_asignacion(cp_model.CpModel(), [employee], shifts)
        return set(variables)

    def test_explicit_holiday_availability_overrides_only_the_weekly_pattern_on_configured_dates(self):
        self.assertEqual(self.assignments(self.employee('disponible')), {(1, 'lunes'), (1, 'martes')})
        self.assertEqual(self.assignments(self.employee('disponible'), holidays=[]), {(1, 'martes')})
        self.assertEqual(self.assignments(self.employee('no_disponible')), {(1, 'martes')})
        self.assertEqual(self.assignments(self.employee()), {(1, 'martes')})
        self.assertEqual(self.assignments(self.employee('no_disponible'), holidays=['2026-10-20']), set())

    def test_holiday_opt_in_does_not_cancel_dated_absences(self):
        for blocked in [{'vacaciones': ['2026-10-19']}, {'fechas_libres_solicitadas': ['2026-10-19']}, {'en_baja': True}]:
            self.assertNotIn((1, 'lunes'), self.assignments(self.employee('disponible', **blocked)))

    def test_holiday_opt_in_overrides_weekly_blocked_day_and_keeps_other_days_blocked(self):
        employee = self.employee('disponible', dias_bloqueados_recurrentes=['lunes', 'martes'])
        self.assertEqual(self.assignments(employee), {(1, 'lunes')})

    def test_manual_assignment_cannot_force_holiday_opt_out(self):
        shifts = _preparar_turnos(self.layout(preassigned=True), {'semana_id': '2026-W43', 'festivos': ['2026-10-19']})
        with self.assertRaisesRegex(ValueError, 'festivos'):
            _crear_variables_asignacion(cp_model.CpModel(), [self.employee('no_disponible')], shifts)

    def test_auto_template_respects_independent_holidays_and_absences(self):
        franjas = {day: [(17*60, 21*60)] for day in DIAS}
        monday = dt.date(2026, 10, 19)
        windows = lambda employee: list(ventanas_empleado(employee, monday, franjas, {'2026-10-19'}))
        self.assertEqual([w[0] for w in windows(self.employee('disponible'))], [0, 1])
        self.assertEqual([w[0] for w in windows(self.employee('no_disponible'))], [1])
        self.assertEqual([w[0] for w in windows(self.employee('disponible', vacaciones=['2026-10-19']))], [1])

    def test_auto_template_generates_no_reserved_minor_shift_on_holiday_opt_out(self):
        employee = self.employee('no_disponible', es_menor=True, tipo_disponibilidad='completa')
        result = generar_plantilla({'semana': '2026-W43', 'empleados': [employee], 'festivos': ['2026-10-19'],
            'horario': {day: ([{'inicio': '17:00', 'fin': '21:00'}] if day == 'lunes' else []) for day in DIAS}}, ruta_modelo=None)
        self.assertEqual(result['plantilla']['lunes'], [])
        self.assertEqual(result['resumen'][0]['minutos_generados'], 0)

    def test_solver_api_covers_holiday_with_opted_in_employee_only(self):
        payload = {'empleados': [self.employee('disponible')], 'plantilla_diseno': self.layout()[:1],
                   'metadatos': {'semana_id': '2026-W43', 'festivos': ['2026-10-19']},
                   'ampliar_limites': True, 'tiempo_maximo': 0.05}
        response = app.test_client().post('/generar', json=payload)
        self.assertEqual(response.status_code, 200)
        self.assertTrue(response.json['success'])
        self.assertEqual([r['turno_id'] for r in response.json['resultados']], ['lunes'])
        payload['empleados'][0]['disponibilidad_festivos'] = 'no_disponible'
        response = app.test_client().post('/generar', json=payload)
        # The established API returns 404 when no employee can cover any shift.
        self.assertEqual(response.status_code, 404)
        self.assertFalse(response.json['success'])


if __name__ == '__main__':
    unittest.main()
