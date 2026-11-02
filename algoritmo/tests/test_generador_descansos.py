import sys
import unittest
from pathlib import Path

from ortools.sat.python import cp_model

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from generador_horarios import (
    _agregar_restricciones_descansos,
    _crear_variables_asignacion,
    _preparar_turnos,
)


class DescansosNocturnosTest(unittest.TestCase):
    def turnos(self, second_start="09:00", second_end="12:00"):
        return _preparar_turnos([
            {"dia": day, "fecha": date, "turnos_requeridos": [{
                "turno_id": shift_id, "rol_necesario": "repartidor",
                "hora_inicio": start, "hora_fin": end,
            }]}
            for day, date, shift_id, start, end in [
                ("lunes", "2026-10-19", "noche", "22:00", "06:00"),
                ("martes", "2026-10-20", "siguiente", second_start, second_end),
            ]
        ])

    def estado_con_ambos_turnos(self, shifts):
        employees = [{"id": 1, "rol": "repartidor", "tipo_disponibilidad": "completa"}]
        model = cp_model.CpModel()
        variables, eligible = _crear_variables_asignacion(model, employees, shifts)
        _agregar_restricciones_descansos(model, variables, employees, eligible)
        for variable in variables.values():
            model.Add(variable == 1)
        return cp_model.CpSolver().Solve(model)

    def test_fin_posterior_a_las_cinco_se_conserva_en_el_dia_siguiente(self):
        night = self.turnos()[0]
        self.assertEqual(8 * 60, night["abs_min_fin"] - night["abs_min_inicio"])
        self.assertEqual(30 * 60, night["min_fin"])

    def test_noche_hasta_las_seis_impide_turno_con_solo_tres_horas_de_descanso(self):
        self.assertEqual(cp_model.INFEASIBLE, self.estado_con_ambos_turnos(self.turnos()))

    def test_noche_hasta_las_seis_impide_turno_que_solapa_al_dia_siguiente(self):
        self.assertEqual(cp_model.INFEASIBLE, self.estado_con_ambos_turnos(self.turnos("05:00", "08:00")))

    def test_noche_hasta_las_seis_permite_diez_horas_completas_de_descanso(self):
        self.assertIn(self.estado_con_ambos_turnos(self.turnos("16:00", "19:00")),
                      (cp_model.OPTIMAL, cp_model.FEASIBLE))


if __name__ == "__main__":
    unittest.main()
