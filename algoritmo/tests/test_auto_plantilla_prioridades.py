import datetime as dt
from pathlib import Path
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from generar_plantilla_ml import DIAS, demanda_historica, perfil_priorizado, puntos_cobertura


class AutoPlantillaPrioridadesTest(unittest.TestCase):
    monday = dt.date(2026, 10, 19)

    def employees(self, target=180):
        return [({"rol": "auxiliar"}, target, [])]

    def windows(self):
        return {dia: [(720, 780), (1140, 1200)] for dia in DIAS}

    def recent(self):
        references = {(self.monday + dt.timedelta(days=i - 7)).isoformat(): {"auxiliar": []}
                      for i in range(7)}
        for day, count in [(0, 1), (5, 2)]:
            references[(self.monday + dt.timedelta(days=day - 7)).isoformat()]["auxiliar"] = [
                {"hora_inicio": "12:00", "hora_fin": "13:00"} for _ in range(count)]
        return references

    def integral(self, profile, windows, *, block=None):
        return sum(coverage * (min(15, windows[DIAS[day]][band][1] - point)
                               if (point - windows[DIAS[day]][band][0]) % 15 == 0 else 0)
                   for (day, band, point), coverage in profile.items() if block is None or block == band)

    def test_intensidad_antigua_no_domina_y_cada_fuente_conserva_proporcion_semanal(self):
        model = {"metadata": {"anios": [2025, 2026], "fecha_hasta": "2026-10-15"}}

        def calculate(multiplier):
            def observed(_, date, role, points):
                if date.year != 2025:
                    return None
                count = {0: 1, 5: 2}.get(date.weekday(), 0) * multiplier
                return {point: count if 1140 <= point < 1200 else 0 for point in points}
            with patch("generar_plantilla_ml.perfil_observado", side_effect=observed), \
                    patch("generar_plantilla_ml.perfil_cobertura", return_value={}):
                return demanda_historica(model, self.monday, self.windows(), self.employees(), self.recent())[0]["auxiliar"]

        one = calculate(1)
        thousand = calculate(1000)
        self.assertEqual(one.keys(), thousand.keys())
        for key in one:
            self.assertAlmostEqual(one[key], thousand[key])
        self.assertAlmostEqual(self.integral(one, self.windows()), 180)
        self.assertAlmostEqual(self.integral(one, self.windows(), block=0), 144)
        self.assertAlmostEqual(self.integral(one, self.windows(), block=1), 36)
        for block in [0, 1]:
            point = self.windows()["lunes"][block][0]
            self.assertAlmostEqual(one[(5, block, point)], 2 * one[(0, block, point)])

    def test_diagnostico_del_dia_tampoco_confunde_intensidad_con_prioridad(self):
        model = {"metadata": {"anios": [2025], "fecha_hasta": "2026-10-15"}}
        with patch("generar_plantilla_ml.perfil_observado", return_value={720: 0, 1140: 1000}), \
                patch("generar_plantilla_ml.perfil_cobertura", return_value={}):
            profile, _ = perfil_priorizado(model, self.monday, "auxiliar", [720, 1140], self.recent())
        self.assertGreater(profile[720], profile[1140])

    def test_respaldo_estacional_mantiene_peso_bajo_aunque_su_equipo_fuera_mayor(self):
        model = {"metadata": {"anios": [2026], "fecha_hasta": "2026-10-15"}}

        def calculate(multiplier):
            def seasonal(_, date, role, points):
                return {point: multiplier if date.weekday() == 1 and 1140 <= point < 1200 else 0
                        for point in points}
            with patch("generar_plantilla_ml.perfil_observado", return_value=None), \
                    patch("generar_plantilla_ml.perfil_cobertura", side_effect=seasonal):
                return demanda_historica(model, self.monday, self.windows(), self.employees(), self.recent())[0]["auxiliar"]

        one, thousand = calculate(1), calculate(1000)
        for key in one:
            self.assertAlmostEqual(one[key], thousand[key])
        self.assertAlmostEqual(self.integral(one, self.windows(), block=0), 180 * 64 / 64.25)
        self.assertAlmostEqual(self.integral(one, self.windows(), block=1), 180 * 0.25 / 64.25)

    def test_ceros_publicados_se_conservan_y_dias_desconocidos_no_se_fabrican(self):
        windows = {dia: [(720, 780)] for dia in DIAS}
        references = {"2026-10-12": {"auxiliar": []}, "2026-10-13": {"auxiliar": [
            {"hora_inicio": "12:00", "hora_fin": "13:00"}]}}
        demands, _ = demanda_historica(None, self.monday, windows, self.employees(60), references)
        self.assertEqual(demands["auxiliar"][(0, 0, 720)], 0)
        self.assertEqual(demands["auxiliar"][(1, 0, 720)], 1)
        self.assertNotIn((2, 0, 720), demands["auxiliar"])
        all_zero = {(self.monday + dt.timedelta(days=i - 7)).isoformat(): {"auxiliar": []} for i in range(7)}
        demands, _ = demanda_historica(None, self.monday, windows, self.employees(60), all_zero)
        self.assertTrue(demands["auxiliar"])
        self.assertEqual(set(demands["auxiliar"].values()), {0})

    def test_fin_menos_uno_no_duplica_minutos_con_franja_irregular_y_medianoche(self):
        windows = {dia: [] for dia in DIAS}
        windows["lunes"] = [(1147, 1471)]
        references = {"2026-10-12": {"auxiliar": [{"hora_inicio": "19:07", "hora_fin": "00:31"}]}}
        demands, _ = demanda_historica(None, self.monday, windows, self.employees(324), references)
        self.assertEqual(set(demands["auxiliar"].values()), {1})
        self.assertAlmostEqual(self.integral(demands["auxiliar"], windows), 324)
        self.assertEqual(len(demands["auxiliar"]), len(puntos_cobertura(1147, 1471)))

    def test_rol_ausente_en_modelo_y_corte_de_lunes_no_usan_estacional_futuro(self):
        model = {"metadata": {"anios": [2026], "fecha_hasta": "2026-10-21"}}
        with patch("generar_plantilla_ml.perfil_observado", return_value=None), \
                patch("generar_plantilla_ml.perfil_cobertura", side_effect=AssertionError("Entrenado después del lunes")):
            demands, references = demanda_historica(model, self.monday, self.windows(), self.employees())
        self.assertEqual(demands, {})
        self.assertEqual(references, [])
        model["metadata"]["fecha_hasta"] = "2026-10-15"
        with patch("generar_plantilla_ml.perfil_observado", return_value=None), \
                patch("generar_plantilla_ml.perfil_cobertura", return_value={}):
            demands, _ = demanda_historica(model, self.monday, self.windows(), self.employees())
        self.assertEqual(demands, {})


if __name__ == "__main__":
    unittest.main()
