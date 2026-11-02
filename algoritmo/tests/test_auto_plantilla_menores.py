import datetime as dt
import sys
import unittest
from collections import defaultdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from generar_plantilla_ml import DIAS, generar_plantilla, minutos


class AutoPlantillaMenoresTest(unittest.TestCase):
    def empleado(self, emp_id=1, **changes):
        return {"id": emp_id, "nombre": f"Empleado QA {emp_id}", "rol": "auxiliar",
                "horas_mensuales": 12, "tipo_disponibilidad": "completa", **changes}

    def payload(self, empleados, franjas=None):
        return {"semana": "2026-W44", "empleados": empleados,
                "horario": {dia: list(franjas or []) for dia in DIAS}}

    def test_dos_menores_reservados_y_adulto_sin_asignar_hasta_cierre(self):
        empleados = [self.empleado(1, horas_mensuales=10),
                     self.empleado(2, es_menor=True), self.empleado(3, es_menor=True)]
        payload = self.payload(empleados)
        payload["horario"]["lunes"] = [{"inicio": "19:00", "fin": "00:30"}]
        result = generar_plantilla(payload, ruta_modelo=None)
        shifts = result["plantilla"]["lunes"]
        reserved = [shift for shift in shifts if shift.get("empleado_id")]
        self.assertEqual({shift["empleado_id"] for shift in reserved}, {2, 3})
        self.assertTrue(all(shift["reserva_menor"] for shift in reserved))
        self.assertTrue(all(shift["hora_fin"] == "22:00" for shift in reserved))
        adults = [shift for shift in shifts if not shift.get("empleado_id")]
        self.assertEqual(len(adults), 1)
        self.assertEqual(adults[0]["hora_fin"], "00:30")
        self.assertTrue(all(row["minutos_generados"] == row["minutos_objetivo"]
                            for row in result["resumen"]))

    def test_las_22_son_inclusivas_y_no_encaja_madrugada_ni_inicio_a_las_22(self):
        for start, end, expected in [("21:00", "00:00", 60), ("22:00", "00:30", 0),
                                     ("00:00", "02:00", 0), ("05:00", "07:00", 60)]:
            with self.subTest(start=start, end=end):
                payload = self.payload([self.empleado(es_menor=True)])
                payload["horario"]["lunes"] = [{"inicio": start, "fin": end}]
                result = generar_plantilla(payload, ruta_modelo=None)
                self.assertEqual(result["resumen"][0]["minutos_generados"], expected)
                self.assertTrue(any("sin encajar" in aviso for aviso in result["avisos"]))
                for shift in result["plantilla"]["lunes"]:
                    self.assertGreaterEqual(minutos(shift["hora_inicio"]), 360)
                    self.assertLessEqual(minutos(shift["hora_fin"]), 1320)
                    self.assertEqual(shift["empleado_id"], 1)

    def test_reserva_respeta_disponibilidad_bloqueos_y_ausencias(self):
        menor = self.empleado(es_menor=True, horas_mensuales=80,
                             tipo_disponibilidad="personalizada",
                             dias_disponibles={dia: ["tarde"] for dia in DIAS},
                             dias_bloqueados_recurrentes=["martes", "miercoles", "jueves", "viernes"],
                             vacaciones=["2026-10-31"], fechas_libres_solicitadas=["2026-11-01"])
        payload = self.payload([menor], [{"inicio": "12:00", "fin": "16:30"},
                                         {"inicio": "19:00", "fin": "00:30"}])
        result = generar_plantilla(payload, ruta_modelo=None)
        self.assertEqual([dia for dia in DIAS if result["plantilla"][dia]], ["lunes"])
        self.assertEqual(result["plantilla"]["lunes"], [{"rol": "auxiliar", "hora_inicio": "19:00",
                                                        "hora_fin": "22:00", "empleado_id": 1, "reserva_menor": True}])
        self.assertTrue(any("sin encajar" in aviso for aviso in result["avisos"]))

    def test_limites_de_ocho_horas_y_doce_de_descanso_entre_jornadas(self):
        payload = self.payload([self.empleado(es_menor=True, horas_mensuales=160)],
                               [{"inicio": "06:00", "fin": "22:00"}])
        result = generar_plantilla(payload, ruta_modelo=None)
        per_day, shifts = defaultdict(int), []
        for index, dia in enumerate(DIAS):
            for shift in result["plantilla"][dia]:
                start, end = minutos(shift["hora_inicio"]), minutos(shift["hora_fin"])
                per_day[index] += end - start
                shifts.append((index * 1440 + start, index * 1440 + end))
        self.assertEqual(sum(per_day.values()), 40 * 60)
        self.assertLessEqual(len(per_day), 5)
        self.assertTrue(all(total <= 8 * 60 for total in per_day.values()))
        for first, second in zip(shifts, shifts[1:]):
            self.assertGreaterEqual(second[0] - first[1], 12 * 60)

    def test_generador_final_conserva_reservas_y_asigna_el_hueco_adulto(self):
        from generador_horarios import app
        empleados = [self.empleado(1, horas_mensuales=10, es_menor=False),
                     self.empleado(2, es_menor=True), self.empleado(3, es_menor=True)]
        payload = self.payload(empleados)
        payload["horario"]["lunes"] = [{"inicio": "19:00", "fin": "00:30"}]
        result = generar_plantilla(payload, ruta_modelo=None)
        layout = []
        for index, dia in enumerate(DIAS):
            layout.append({"dia": dia, "fecha": (dt.date(2026, 10, 26) + dt.timedelta(days=index)).isoformat(),
                           "turnos_requeridos": [
                               {"turno_id": f"{dia}-{i}", "rol_necesario": shift["rol"],
                                "hora_inicio": shift["hora_inicio"], "hora_fin": shift["hora_fin"],
                                "duracion_horas": (minutos(shift["hora_fin"]) - minutos(shift["hora_inicio"])) / 60,
                                "empleado_id": shift.get("empleado_id"), "reserva_menor": shift.get("reserva_menor", False)}
                               for i, shift in enumerate(result["plantilla"][dia])]})
        response = app.test_client().post("/generar", json={"empleados": empleados, "plantilla_diseno": layout,
                                                          "tiempo_maximo": 0.05})
        self.assertEqual(response.status_code, 200, response.json)
        self.assertTrue(response.json["success"], response.json)
        self.assertEqual(len(response.json["resultados"]), 3)
        assigned = {row["turno_id"]: row["empleado_id"] for row in response.json["resultados"]}
        for shift in layout[0]["turnos_requeridos"]:
            self.assertEqual(assigned[shift["turno_id"]], shift["empleado_id"] or 1)


if __name__ == "__main__":
    unittest.main()
