import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from generador_horarios import (
    _crear_variables_asignacion, _preparar_turnos,
    _configurar_funcion_objetivo, _configurar_dias_libres_y_penalizaciones,
    _detectar_conflicto_preasignados, app,
)
from ortools.sat.python import cp_model


class DisponibilidadTest(unittest.TestCase):
    def test_lista_de_dias_permite_manana_y_tarde_sin_incluir_fin_de_semana(self):
        empleados = [{'id': 1, 'rol': 'limpiadora',
                      'tipo_disponibilidad': 'personalizada',
                      'dias_disponibles': ['lunes']}]
        plantilla = [
            {'dia': dia, 'fecha': fecha, 'turnos_requeridos': [
                {'turno_id': f'{dia}-{hora}', 'rol_necesario': 'limpiadora',
                 'hora_inicio': hora, 'hora_fin': fin, 'duracion_horas': 3}
                for hora, fin in [('09:00', '12:00'), ('17:00', '20:00')]
            ]}
            for dia, fecha in [('lunes', '2026-10-19'), ('sabado', '2026-10-24')]
        ]
        x, _ = _crear_variables_asignacion(cp_model.CpModel(), empleados, _preparar_turnos(plantilla))
        self.assertEqual(set(x), {(1, 'lunes-09:00'), (1, 'lunes-17:00')})

    def test_tipo_disponibilidad_dias_especificos_funciona_igual_que_personalizada(self):
        empleados = [{'id': 1, 'rol': 'cocinero',
                      'tipo_disponibilidad': 'dias_especificos',
                      'dias_disponibles': ['martes-tarde', 'sabado']}]
        plantilla = [
            {'dia': 'martes', 'fecha': '2026-10-20', 'turnos_requeridos': [
                {'turno_id': 'mar-m', 'rol_necesario': 'cocinero', 'hora_inicio': '10:00', 'hora_fin': '14:00', 'duracion_horas': 4},
                {'turno_id': 'mar-t', 'rol_necesario': 'cocinero', 'hora_inicio': '17:00', 'hora_fin': '21:00', 'duracion_horas': 4},
            ]},
            {'dia': 'sabado', 'fecha': '2026-10-24', 'turnos_requeridos': [
                {'turno_id': 'sab-m', 'rol_necesario': 'cocinero', 'hora_inicio': '11:00', 'hora_fin': '15:00', 'duracion_horas': 4},
            ]},
        ]
        x, _ = _crear_variables_asignacion(cp_model.CpModel(), empleados, _preparar_turnos(plantilla))
        self.assertEqual(set(x), {(1, 'mar-t'), (1, 'sab-m')})

    def test_vacaciones_bloquean_turno_aunque_fecha_plantilla_sea_texto_corto(self):
        empleados = [{'id': 1, 'rol': 'camarero',
                      'tipo_disponibilidad': 'completa',
                      'vacaciones': [{'inicio': '2026-10-19', 'fin': '2026-10-20'}]}]
        plantilla = [
            {'dia': 'lunes', 'fecha': '19 oct', 'turnos_requeridos': [
                {'turno_id': 'lun-1', 'rol_necesario': 'camarero', 'hora_inicio': '12:00', 'hora_fin': '16:00', 'duracion_horas': 4},
            ]},
            {'dia': 'miercoles', 'fecha': '21 oct', 'turnos_requeridos': [
                {'turno_id': 'mie-1', 'rol_necesario': 'camarero', 'hora_inicio': '12:00', 'hora_fin': '16:00', 'duracion_horas': 4},
            ]},
        ]
        turnos = _preparar_turnos(plantilla, {'semana_id': '2026-W43'})
        x, _ = _crear_variables_asignacion(cp_model.CpModel(), empleados, turnos)
        self.assertEqual(set(x), {(1, 'mie-1')})

    def test_finde_libre_activa_variable_consecutivo(self):
        empleados = [{'id': 1, 'nombre': 'Ana', 'rol': 'camarero', 'tipo_disponibilidad': 'completa'}]
        model = cp_model.CpModel()
        turno_sab = {'turno_id': 'sab-1', 'dia': 'sabado', 'rol_necesario': 'camarero', 'duracion_horas': 4}
        x_sab = model.NewBoolVar('x_sab')
        model.Add(x_sab == 0)
        _, _, _, penalizaciones_finde_libre = _configurar_dias_libres_y_penalizaciones(
            model, {(1, 'sab-1'): x_sab}, empleados, {1: [turno_sab]}, False
        )
        self.assertEqual(len(penalizaciones_finde_libre), 1)
        solver = cp_model.CpSolver()
        self.assertIn(solver.Solve(model), (cp_model.OPTIMAL, cp_model.FEASIBLE))
        self.assertEqual(solver.Value(penalizaciones_finde_libre[0]), 1)

    def test_detectar_conflicto_preasignados_solapados(self):
        empleados = [{'id': 1, 'nombre': 'Carlos', 'rol': 'cocinero'}]
        turnos = _preparar_turnos([
            {'dia': 'lunes', 'fecha': '2026-10-19', 'turnos_requeridos': [
                {'turno_id': 't1', 'rol_necesario': 'cocinero', 'hora_inicio': '10:00', 'hora_fin': '15:00', 'duracion_horas': 5, 'empleado_id': 1},
                {'turno_id': 't2', 'rol_necesario': 'cocinero', 'hora_inicio': '14:00', 'hora_fin': '18:00', 'duracion_horas': 4, 'empleado_id': 1},
            ]}
        ])
        msg = _detectar_conflicto_preasignados(empleados, turnos)
        self.assertIsNotNone(msg)
        self.assertIn('Carlos', msg)

    def test_objetivo_manual_superior_al_contrato_no_impone_horas_minimas(self):
        empleados = [{'id': 1, 'nombre': 'Prueba', 'rol': 'limpiadora',
                      'tipo_disponibilidad': 'completa', 'horas_mensuales': 80}]
        model = cp_model.CpModel()
        shift = model.NewBoolVar('turno')
        model.Add(shift == 0)  # Ningún turno puede cubrirse en este escenario.
        _configurar_funcion_objetivo(
            model, {(1, 'turno'): shift}, empleados,
            {1: [{'turno_id': 'turno', 'duracion_horas': 3}]}, {1: 28.5}, [], [], [], [],
        )
        solver = cp_model.CpSolver()
        self.assertIn(solver.Solve(model), (cp_model.OPTIMAL, cp_model.FEASIBLE))
        self.assertEqual(solver.Value(shift), 0)

    def test_api_genera_con_horas_ajustadas_y_disponibilidad_insuficiente(self):
        payload = {
            'empleados': [{'id': 1, 'nombre': 'Prueba', 'rol': 'limpiadora',
                           'tipo_disponibilidad': 'personalizada', 'dias_disponibles': ['lunes'],
                           'horas_mensuales': 80, 'horas_semanales_override': 28.5}],
            'plantilla_diseno': [
                {'dia': day, 'fecha': date, 'turnos_requeridos': [
                    {'turno_id': day, 'rol_necesario': 'limpiadora', 'hora_inicio': '09:00',
                     'hora_fin': '12:00', 'duracion_horas': 3}
                ]}
                for day, date in [('lunes', '2026-10-19'), ('sabado', '2026-10-24')]
            ],
            'ampliar_limites': True, 'tiempo_maximo': 0.05,
        }
        response = app.test_client().post('/generar', json=payload)
        self.assertEqual(response.status_code, 200)
        self.assertTrue(response.json['success'])
        self.assertEqual(response.json['resultados'], [
            {'turno_id': 'lunes', 'empleado_id': 1, 'nombre': 'Prueba'}
        ])


if __name__ == '__main__':
    unittest.main()
