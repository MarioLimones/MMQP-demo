import sys
import unittest
from pathlib import Path

from ortools.sat.python import cp_model

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from generador_horarios import _crear_variables_asignacion, _preparar_turnos


class AbsenceAvailabilityTest(unittest.TestCase):
    def variables(self, date, blocked=None, vacations=None, assigned=None, recurring=None):
        employee = {
            "id": 1, "rol": "repartidor", "tipo_disponibilidad": "completa",
            "fechas_libres_solicitadas": blocked or [], "vacaciones": vacations or [],
            "dias_bloqueados_recurrentes": recurring or [],
        }
        shifts = _preparar_turnos([{
            "dia": "lunes", "fecha": date, "turnos_requeridos": [{
                "turno_id": "test", "rol_necesario": "repartidor", "hora_inicio": "09:00",
                "hora_fin": "12:00", "empleado_id": assigned,
            }],
        }])
        return _crear_variables_asignacion(cp_model.CpModel(), [employee], shifts)[0]

    def test_every_day_of_an_approved_range_is_blocked(self):
        dates = ["2026-11-02", "2026-11-03", "2026-11-04"]
        for date in dates:
            with self.subTest(date=date):
                self.assertEqual({}, self.variables(date, blocked=dates))

    def test_same_month_and_day_in_a_different_year_are_available(self):
        self.assertIn((1, "test"), self.variables("2026-11-02", blocked=["2025-11-02"]))

    def test_removing_one_cause_preserves_another(self):
        self.assertEqual({}, self.variables("2026-11-03", blocked=["2026-11-03", "2026-11-04"]))
        self.assertIn((1, "test"), self.variables("2026-11-02", blocked=["2026-11-03", "2026-11-04"]))

    def test_legacy_vacation_payload_is_still_supported(self):
        self.assertEqual({}, self.variables("2026-11-02", vacations=["2026-11-02"]))

    def test_independent_recurring_blocks_are_preserved(self):
        self.assertEqual({}, self.variables("2026-11-02", recurring=["lunes"]))

    def test_existing_explicit_manual_assignment_override_is_preserved(self):
        self.assertIn((1, "test"), self.variables("2026-11-02", blocked=["2026-11-02"], assigned=1))


if __name__ == "__main__":
    unittest.main()
