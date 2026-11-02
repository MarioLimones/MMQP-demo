import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from ortools.sat.python import cp_model
from generador_horarios import (
    _agregar_restricciones_horas, _crear_variables_asignacion, _preparar_turnos, app,
)


class IntegracionGeneradorTest(unittest.TestCase):
    def test_menor_respeta_dias_especificos_y_vacaciones_con_fechas_iso(self):
        empleado = {'id': 1, 'nombre': 'Menor QA', 'rol': 'auxiliar', 'es_menor': True,
                    'tipo_disponibilidad': 'dias_especificos', 'dias_disponibles': ['lunes', 'martes-tarde'],
                    'vacaciones': [{'inicio': '2026-10-26', 'fin': '2026-10-26'}]}
        plantilla = [{'dia': dia, 'fecha': fecha, 'turnos_requeridos': [
            {'turno_id': f'{dia}-{inicio}', 'rol_necesario': 'auxiliar', 'hora_inicio': inicio,
             'hora_fin': fin, 'duracion_horas': 2}
            for inicio, fin in [('12:00', '14:00'), ('19:00', '21:00'), ('22:00', '00:00')]]}
            for dia, fecha in [('lunes', '26 oct'), ('martes', '27 oct')]]
        turnos = _preparar_turnos(plantilla, {'semana_id': '2026-W44'})
        variables, _ = _crear_variables_asignacion(cp_model.CpModel(), [empleado], turnos)
        self.assertEqual(set(variables), {(1, 'martes-19:00')})

    def test_ampliar_limites_no_anade_otra_holgura_al_objetivo_ya_calculado(self):
        empleado = {'id': 1, 'rol': 'auxiliar', 'tipo_disponibilidad': 'completa'}
        turnos = [{'turno_id': str(i), 'dia': 'lunes', 'rol_necesario': 'auxiliar', 'duracion_horas': 3}
                  for i in range(2)]
        model = cp_model.CpModel()
        variables = {(1, t['turno_id']): model.NewBoolVar(t['turno_id']) for t in turnos}
        _agregar_restricciones_horas(model, variables, [empleado], {1: turnos}, {1: 3}, {1: 3}, True)
        for variable in variables.values():
            model.Add(variable == 1)
        self.assertEqual(cp_model.CpSolver().Solve(model), cp_model.INFEASIBLE)

    def test_preasignacion_manual_de_empleado_inactivo_se_libera_sin_perder_reservas_de_autoplantilla(self):
        payload = {'empleados': [{'id': 2, 'nombre': 'Activo QA', 'rol': 'auxiliar',
                                 'tipo_disponibilidad': 'completa', 'horas_mensuales': 12}],
                   'plantilla_diseno': [{'dia': 'lunes', 'fecha': '26 oct', 'turnos_requeridos': [
                       {'turno_id': 'manual-antiguo', 'rol_necesario': 'auxiliar', 'hora_inicio': '12:00',
                        'hora_fin': '15:00', 'duracion_horas': 3, 'empleado_id': 1}]}],
                   'metadatos': {'semana_id': '2026-W44'}, 'tiempo_maximo': 0.05}
        response = app.test_client().post('/generar', json=payload)
        self.assertEqual(response.status_code, 200, response.json)
        self.assertEqual(response.json['resultados'][0]['empleado_id'], 2)
        payload['plantilla_diseno'][0]['turnos_requeridos'][0]['reserva_menor'] = True
        response = app.test_client().post('/generar', json=payload)
        self.assertEqual(response.status_code, 422, response.json)
        self.assertIn('reservado', response.json['message'])
