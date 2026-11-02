import sys
import unittest
from pathlib import Path

from ortools.sat.python import cp_model

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from generador_horarios import (
    _agregar_restricciones_descansos, _configurar_dias_libres_y_penalizaciones,
    _crear_variables_asignacion, _preparar_turnos, app,
)


class GeneradorMenoresTest(unittest.TestCase):
    def empleado(self, **changes):
        return {'id': 1, 'nombre': 'Menor QA', 'rol': 'auxiliar',
                'tipo_disponibilidad': 'completa', 'es_menor': True,
                'horas_mensuales': 24, **changes}

    def plantilla(self, rows):
        return [{'dia': day, 'fecha': date, 'turnos_requeridos': [{
            'turno_id': shift_id, 'rol_necesario': 'auxiliar',
            'hora_inicio': start, 'hora_fin': end, 'duracion_horas': duration,
            **extra,
        }]} for day, date, shift_id, start, end, duration, extra in rows]

    def test_solo_menores_excluyen_trabajo_fuera_de_06_a_22(self):
        rows = [('lunes', '2026-10-19', str(i), start, end, 3, {})
                for i, (start, end) in enumerate([
                    ('06:00', '09:00'), ('19:00', '22:00'), ('19:00', '22:01'),
                    ('05:59', '08:00'), ('22:00', '06:00'), ('00:00', '03:00'),
                ])]
        employees = [self.empleado(), self.empleado(id=2, es_menor=False)]
        variables, _ = _crear_variables_asignacion(
            cp_model.CpModel(), employees, _preparar_turnos(self.plantilla(rows)))
        self.assertEqual({turno for emp, turno in variables if emp == 1}, {'0', '1'})
        self.assertEqual({turno for emp, turno in variables if emp == 2}, set(map(str, range(6))))

    def test_preasignacion_no_permite_un_turno_nocturno_y_da_error_claro(self):
        payload = {'empleados': [self.empleado()], 'plantilla_diseno': self.plantilla([
            ('lunes', '2026-10-19', 'reservado', '19:00', '23:00', 4, {'empleado_id': 1}),
        ]), 'ampliar_limites': True, 'tiempo_maximo': 0.05}
        response = app.test_client().post('/generar', json=payload)
        self.assertEqual(response.status_code, 422, response.json)
        self.assertFalse(response.json['success'])
        self.assertIn('22:00', response.json['message'])
        self.assertNotIn('traceback', response.json)

    def test_reserva_sin_empleado_disponible_no_se_pierde_silenciosamente(self):
        payload = {'empleados': [self.empleado(id=2, es_menor=False)],
                   'plantilla_diseno': self.plantilla([
                       ('lunes', '2026-10-19', 'reservado', '19:00', '22:00', 3, {'empleado_id': 1, 'reserva_menor': True}),
                   ]), 'ampliar_limites': True, 'tiempo_maximo': 0.05}
        response = app.test_client().post('/generar', json=payload)
        self.assertEqual(response.status_code, 422, response.json)
        self.assertIn('reservado', response.json['message'])

    def test_generacion_conserva_reserva_y_asigna_cierre_a_adulto(self):
        payload = {'empleados': [self.empleado(horas_mensuales=12),
                                self.empleado(id=2, nombre='Adulto QA', es_menor=False, horas_mensuales=12)],
                   'plantilla_diseno': self.plantilla([
                       ('lunes', '2026-10-19', 'reservado', '19:00', '22:00', 3, {'empleado_id': 1}),
                       ('lunes', '2026-10-19', 'cierre', '21:00', '00:00', 3, {}),
                   ]), 'tiempo_maximo': 0.05}
        response = app.test_client().post('/generar', json=payload)
        self.assertEqual(response.status_code, 200, response.json)
        self.assertTrue(response.json['success'], response.json)
        self.assertEqual({row['turno_id']: row['empleado_id'] for row in response.json['resultados']},
                         {'reservado': 1, 'cierre': 2})

    def test_menor_con_reservas_no_recibe_huecos_adicionales_aunque_queden_sin_cubrir(self):
        payload = {'empleados': [self.empleado(horas_mensuales=12, horas_semanales_override=3),
                                self.empleado(id=2, nombre='Adulto QA', es_menor=False,
                                              horas_mensuales=12, horas_semanales_override=3,
                                              tipo_disponibilidad='personalizada', dias_disponibles=['lunes'])],
                   'plantilla_diseno': self.plantilla([
                       ('lunes', '2026-10-19', 'reservado', '12:00', '15:00', 3,
                        {'empleado_id': 1, 'reserva_menor': True}),
                       ('martes', '2026-10-20', 'vacio', '12:00', '15:00', 3, {}),
                   ]), 'tiempo_maximo': 0.05}
        response = app.test_client().post('/generar', json=payload)
        self.assertEqual(response.status_code, 200, response.json)
        self.assertTrue(response.json['success'], response.json)
        self.assertEqual(response.json['resultados'], [
            {'turno_id': 'reservado', 'empleado_id': 1, 'nombre': 'Menor QA'},
        ])

    def test_reserva_manual_parcial_de_menor_sigue_permitiendo_asignar_otros_turnos(self):
        payload = {'empleados': [self.empleado(horas_mensuales=24, horas_semanales_override=6)],
                   'plantilla_diseno': self.plantilla([
                       ('lunes', '2026-10-19', 'manual', '12:00', '15:00', 3, {'empleado_id': 1}),
                       ('martes', '2026-10-20', 'vacio', '12:00', '15:00', 3, {}),
                   ]), 'tiempo_maximo': 0.05}
        response = app.test_client().post('/generar', json=payload)
        self.assertEqual(response.status_code, 200, response.json)
        self.assertTrue(response.json['success'], response.json)
        self.assertEqual({row['turno_id']: row['empleado_id'] for row in response.json['resultados']},
                         {'manual': 1, 'vacio': 1})

    def test_plantilla_manual_sin_reservas_sigue_asignando_turnos_diurnos_a_menores(self):
        payload = {'empleados': [self.empleado(horas_mensuales=12, horas_semanales_override=3)],
                   'plantilla_diseno': self.plantilla([
                       ('lunes', '2026-10-19', 'diurno', '12:00', '15:00', 3, {}),
                   ]), 'tiempo_maximo': 0.05}
        response = app.test_client().post('/generar', json=payload)
        self.assertEqual(response.status_code, 200, response.json)
        self.assertTrue(response.json['success'], response.json)
        self.assertEqual(response.json['resultados'], [
            {'turno_id': 'diurno', 'empleado_id': 1, 'nombre': 'Menor QA'},
        ])

    def estado_con_turnos_forzados(self, rows, *, menor=True):
        model = cp_model.CpModel()
        employees = [self.empleado(es_menor=menor)]
        variables, eligible = _crear_variables_asignacion(model, employees, _preparar_turnos(self.plantilla(rows)))
        _agregar_restricciones_descansos(model, variables, employees, eligible)
        _configurar_dias_libres_y_penalizaciones(model, variables, employees, eligible, True)
        for variable in variables.values():
            model.Add(variable == 1)
        return cp_model.CpSolver().Solve(model)

    def test_menor_no_supera_ocho_horas_diarias_incluso_ampliando_limites(self):
        rows = [('lunes', '2026-10-19', 'manana', '06:00', '11:00', 5, {}),
                ('lunes', '2026-10-19', 'tarde', '14:00', '18:00', 4, {})]
        self.assertEqual(self.estado_con_turnos_forzados(rows), cp_model.INFEASIBLE)
        self.assertIn(self.estado_con_turnos_forzados(rows, menor=False), (cp_model.OPTIMAL, cp_model.FEASIBLE))

    def test_limite_diario_usa_horas_reales_del_turno(self):
        rows = [('lunes', '2026-10-19', 'manana', '06:00', '11:00', 0, {}),
                ('lunes', '2026-10-19', 'tarde', '14:00', '18:00', 0, {})]
        self.assertEqual(self.estado_con_turnos_forzados(rows), cp_model.INFEASIBLE)

    def test_menor_respeta_doce_horas_entre_jornadas_sin_cambiar_adultos(self):
        rows = [('lunes', '2026-10-19', 'lunes', '19:00', '22:00', 3, {}),
                ('martes', '2026-10-20', 'martes', '08:00', '11:00', 3, {})]
        self.assertEqual(self.estado_con_turnos_forzados(rows), cp_model.INFEASIBLE)
        self.assertIn(self.estado_con_turnos_forzados(rows, menor=False), (cp_model.OPTIMAL, cp_model.FEASIBLE))
        rows[1] = ('martes', '2026-10-20', 'martes', '10:00', '13:00', 3, {})
        self.assertIn(self.estado_con_turnos_forzados(rows), (cp_model.OPTIMAL, cp_model.FEASIBLE))

    def test_preasignaciones_menor_que_superan_ocho_horas_se_rechazan_con_mensaje(self):
        payload = {'empleados': [self.empleado()], 'plantilla_diseno': self.plantilla([
            ('lunes', '2026-10-19', 'manana', '06:00', '11:00', 5, {'empleado_id': 1}),
            ('lunes', '2026-10-19', 'tarde', '14:00', '18:00', 4, {'empleado_id': 1}),
        ]), 'ampliar_limites': True, 'tiempo_maximo': 0.05}
        response = app.test_client().post('/generar', json=payload)
        self.assertEqual(response.status_code, 422, response.json)
        self.assertIn('8 horas', response.json['message'])


if __name__ == '__main__':
    unittest.main()
