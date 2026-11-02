import sys
import unittest
from pathlib import Path
from collections import Counter
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from generador_horarios import app

class EquilibrioTest(unittest.TestCase):
    def test_reparte_sin_superar_objetivos_semanales_aunque_contratos_sean_distintos(self):
        empleados = [{"id":i,"nombre":f"QA {i}","rol":"auxiliar","tipo_disponibilidad":"completa",
                      "horas_mensuales":contrato,"horas_semanales_override":3}
                     for i,contrato in [(1,160),(2,80)]]
        plantilla = [{"dia":dia,"fecha":fecha,"turnos_requeridos":[{
            "turno_id":dia,"rol_necesario":"auxiliar","hora_inicio":"12:00","hora_fin":"15:00","duracion_horas":3}]}
                     for dia,fecha in [("lunes","2026-10-26"),("martes","2026-10-27")]]
        response = app.test_client().post("/generar",json={"empleados":empleados,"plantilla_diseno":plantilla,"tiempo_maximo":0.05})
        self.assertEqual(response.status_code,200,response.json)
        self.assertTrue(response.json["success"],response.json)
        self.assertEqual(Counter(row["empleado_id"] for row in response.json["resultados"]),{1:1,2:1})
