import datetime as dt
import sys
import unittest
from collections import defaultdict
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from generar_plantilla_ml import (
    DIAS, generar_plantilla, inicio_semana, minutos, planificar_empleado,
    preparar_franjas, ventanas_empleado, fecha_equivalente, perfil_priorizado,
)


class AutoPlantillaTest(unittest.TestCase):
    def empleado(self, **changes):
        return {"id": 1, "nombre": "Auxiliar QA", "rol": "auxiliar", "horas_mensuales": 80,
                "tipo_disponibilidad": "completa", **changes}

    def payload(self, empleados=None):
        return {"semana": "2026-W43", "empleados": empleados or [self.empleado()],
                "horario": {d: [{"inicio": "12:00", "fin": "16:30"},
                                {"inicio": "19:00", "fin": "00:30"}] for d in DIAS}}

    def total(self, result):
        return sum(minutos(s["hora_fin"]) - minutos(s["hora_inicio"])
                   for day in result["plantilla"].values() for s in day)

    def test_modelo_ausente_conserva_contratos_y_avisa_sin_inventar_roles(self):
        with patch("generar_plantilla_ml.cargar_modelo", side_effect=FileNotFoundError):
            short = generar_plantilla(self.payload())
            full = generar_plantilla(self.payload([self.empleado(horas_mensuales=160)]))
        self.assertEqual(self.total(short), 20 * 60)
        self.assertEqual(self.total(full), 40 * 60)
        self.assertEqual({s["rol"] for day in short["plantilla"].values() for s in day}, {"auxiliar"})
        self.assertEqual(short["modelo_historico"]["estado"], "no_disponible")
        self.assertTrue(any("modelo histórico" in aviso for aviso in short["avisos"]))

    def test_semana_anterior_cambia_distribucion_sin_aumentar_horas_ni_personal(self):
        p = self.payload([self.empleado(horas_mensuales=16)])
        p["horario"] = {d: [{"inicio": "12:00", "fin": "16:00"}] for d in DIAS}
        p["referencias_recientes"] = {f"2026-10-{12 + i}": {"auxiliar": []} for i in range(7)}
        p["referencias_recientes"]["2026-10-13"]["auxiliar"] = [{"hora_inicio": "12:00", "hora_fin": "16:00"}]
        result = generar_plantilla(p, ruta_modelo=None)
        self.assertEqual(self.total(result), 240)
        self.assertEqual([d for d in DIAS if result["plantilla"][d]], ["martes"])
        self.assertEqual(len(result["plantilla"]["martes"]), 1)
        p["empleados"][0]["vacaciones"] = ["2026-10-20"]
        result = generar_plantilla(p, ruta_modelo=None)
        self.assertFalse(result["plantilla"]["martes"])
        self.assertEqual(self.total(result), 240)

    def test_prioridad_del_audio_semana_anterior_y_anios_sin_copiar_personas(self):
        fecha = dt.date(2026, 10, 20)
        modelo = {"metadata": {"anios": [2023, 2024, 2025, 2026], "fecha_hasta": "2026-10-15"}}
        recientes = {"2026-10-13": {"auxiliar": [{"hora_inicio": "12:00", "hora_fin": "16:00"}]}}
        def observado(_, date, rol, puntos):
            return {p: 0 if p == 720 else 1 for p in puntos}
        with patch("generar_plantilla_ml.perfil_observado", side_effect=observado), \
                patch("generar_plantilla_ml.perfil_cobertura", return_value={720: 0, 1200: 0}):
            perfil, refs = perfil_priorizado(modelo, fecha, "auxiliar", [720, 1200], recientes)
        self.assertGreater(perfil[720], perfil[1200])
        self.assertEqual([r["tipo"] for r in refs], ["semana_anterior", "hace_1_anios", "hace_2_anios", "hace_3_anios"])
        self.assertTrue(all(a["peso"] > b["peso"] for a, b in zip(refs, refs[1:])))
        self.assertEqual([r["fecha"] for r in refs], ["2026-10-13", "2025-10-21", "2024-10-22", "2023-10-17"])

    def test_cero_publicado_prevalece_sobre_historico_y_no_se_consulta_modelo_futuro(self):
        fecha = dt.date(2026, 10, 20)
        modelo = {"metadata": {"anios": [2026], "fecha_hasta": "2026-10-21"}}
        with patch("generar_plantilla_ml.perfil_observado", side_effect=AssertionError("El cero está publicado")), \
                patch("generar_plantilla_ml.perfil_cobertura", side_effect=AssertionError("Evitar futuro")):
            perfil, refs = perfil_priorizado(modelo, fecha, "auxiliar", [720], {"2026-10-13": {"auxiliar": []}})
        self.assertEqual(perfil, {720: 0})
        self.assertEqual(len(refs), 1)

    def test_equivalencia_anual_conserva_weekday_en_bisiestos_y_cambio_de_anio(self):
        for fecha in [dt.date(2024, 2, 29), dt.date(2026, 1, 1), dt.date(2026, 12, 31)]:
            for years in range(1, 4):
                result = fecha_equivalente(fecha, years)
                self.assertEqual(result.weekday(), fecha.weekday())
                anniversary = fecha.replace(year=fecha.year - years, day=28 if fecha.month == 2 and fecha.day == 29 else fecha.day)
                self.assertLessEqual(abs((result - anniversary).days), 3)

    def test_disponibilidad_con_tildes_bloqueos_y_ausencias_sin_inventar_horas(self):
        emp = self.empleado(tipo_disponibilidad="personalizada",
                            dias_disponibles={"lunes": ["mañana"], "miércoles": ["tarde"], "viernes": ["tarde"]},
                            dias_bloqueados_recurrentes=["MIÉRCOLES"], vacaciones=["2026-10-23"])
        result = generar_plantilla(self.payload([emp]))
        self.assertEqual([d for d in DIAS if result["plantilla"][d]], ["lunes"])
        self.assertEqual(self.total(result), 270)
        self.assertLess(minutos(result["plantilla"]["lunes"][0]["hora_inicio"]), 14 * 60)
        self.assertEqual(result["resumen"][0]["minutos_objetivo"], 1200)
        self.assertTrue(any("sin encajar" in a for a in result["avisos"]))

    def test_excluye_bajas_inactivos_oficina_y_no_asume_horas_sin_contrato(self):
        emps = [self.empleado(id=1, en_baja=True), self.empleado(id=2, activo=False),
                self.empleado(id=3, rol="oficina"), self.empleado(id=4, horas_mensuales=None),
                self.empleado(id=5, vacaciones=[f"2026-10-{i}" for i in range(19, 26)])]
        result = generar_plantilla(self.payload(emps))
        self.assertFalse(any(result["plantilla"].values()))
        self.assertEqual([r["id"] for r in result["resumen"]], [5])
        self.assertTrue(any("horas mensuales" in a for a in result["avisos"]))

    def test_cierre_no_invade_ausencia_del_dia_siguiente_y_respeta_alta(self):
        emp = self.empleado(tipo_disponibilidad="personalizada", dias_disponibles=["lunes", "martes"],
                            fechas_libres_solicitadas=["2026-10-20"])
        result = generar_plantilla(self.payload([emp]))
        self.assertTrue(result["plantilla"]["lunes"])
        self.assertTrue(all(minutos(s["hora_fin"]) <= 1440 for s in result["plantilla"]["lunes"]))
        self.assertFalse(result["plantilla"]["martes"])
        emp["fecha_inicio"] = "2026-10-21"
        self.assertFalse(any(generar_plantilla(self.payload([emp]))["plantilla"].values()))

    def test_testigo_respeta_descansos_limites_y_precision_del_contrato(self):
        emp = self.empleado(horas_mensuales=160.5)
        p = self.payload([emp])
        ventanas = list(ventanas_empleado(emp, inicio_semana(p["semana"]), preparar_franjas(p["horario"])))
        target = round(160.5 * 60 / 4)
        shifts = planificar_empleado(emp, target, ventanas, defaultdict(int))
        self.assertEqual(sum(end - start for _, _, start, end in shifts), target)
        per_day = defaultdict(int)
        for index, _, start, end in shifts:
            per_day[index] += end - start
        self.assertLessEqual(len(per_day), 5)
        self.assertTrue(all(value <= 600 for value in per_day.values()))
        for first, second in zip(shifts, shifts[1:]):
            rest = second[0] * 1440 + second[2] - first[0] * 1440 - first[3]
            self.assertGreaterEqual(rest, 150 if first[0] == second[0] else 600)

    def test_semana_iso_y_franjas_editadas_sin_historicos(self):
        self.assertEqual(inicio_semana("2020-W53").isoformat(), "2020-12-28")
        with self.assertRaises(ValueError):
            inicio_semana("2021-W53")
        p = self.payload()
        p["horario"] = {d: [] for d in DIAS}
        p["horario"]["sabado"] = [{"inicio": "09:00", "fin": "14:00"}]
        result = generar_plantilla(p)
        self.assertEqual(self.total(result), 300)
        self.assertEqual([d for d in DIAS if result["plantilla"][d]], ["sabado"])

    def test_solapamientos_y_franjas_invalidas_se_rechazan(self):
        p = self.payload()
        p["horario"]["lunes"] = [{"inicio": "12:00", "fin": "18:00"}, {"inicio": "17:00", "fin": "20:00"}]
        with self.assertRaisesRegex(ValueError, "solaparse"):
            generar_plantilla(p)
        p["horario"]["lunes"] = [{"inicio": "12:00", "fin": "12:00"}]
        with self.assertRaises(ValueError):
            generar_plantilla(p)

    def test_franjas_cortas_tambien_pueden_cubrirse(self):
        p = self.payload()
        p['horario'] = {dia: [{'inicio': '12:00', 'fin': '13:00'}] for dia in DIAS}
        result = generar_plantilla(p)
        self.assertEqual(self.total(result), 5 * 60)

    def test_cubre_todas_las_franjas_si_hay_contratos_y_disponibilidad_suficientes(self):
        p = self.payload([self.empleado(id=i, horas_mensuales=160) for i in range(1, 4)])
        result = generar_plantilla(p)
        self.assertEqual(self.total(result), 120 * 60)
        self.assertEqual(result["huecos"], [])

    def test_plantilla_sigue_siendo_asignable_por_el_generador_real(self):
        from generador_horarios import app
        emp = self.empleado(tipo_disponibilidad="personalizada", dias_disponibles={d: ["mañana", "tarde"] for d in DIAS})
        p = self.payload([emp])
        result = generar_plantilla(p)
        layout = [{"dia": dia, "fecha": f"2026-10-{19 + index}", "turnos_requeridos": [
            {"turno_id": f"{dia}-{i}", "rol_necesario": s["rol"], "hora_inicio": s["hora_inicio"],
             "hora_fin": s["hora_fin"], "duracion_horas": (minutos(s["hora_fin"]) - minutos(s["hora_inicio"])) / 60}
            for i, s in enumerate(result["plantilla"][dia])]} for index, dia in enumerate(DIAS)]
        response = app.test_client().post("/generar", json={"empleados": [emp], "plantilla_diseno": layout,
                                                          "tiempo_maximo": 0.05})
        self.assertEqual(response.status_code, 200, response.json)
        self.assertTrue(response.json["success"])
        self.assertEqual(len(response.json["resultados"]), sum(len(d["turnos_requeridos"]) for d in layout))


if __name__ == "__main__":
    unittest.main()
