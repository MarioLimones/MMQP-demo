import copy
from datetime import date, timedelta
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from entrenar_modelo import entrenar as cli
from modelo_cobertura import cargar_modelo, entrenar, perfil_cobertura, perfil_observado


def turno(day, **changes):
    return {"fecha": day, "puesto": "auxiliar", "hora_inicio": "12:00", "hora_fin": "14:00",
            "source_id": "documento:" + day, "empleado_id": "anon_qa", **changes}


class ModeloCoberturaTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.rows = [turno((date(2023, 1, 2) + timedelta(days=i)).isoformat()) for i in range(21)]
        cls.model, cls.report = entrenar(cls.rows, n_estimators=4)

    def save(self, path, model=None):
        path.write_text(json.dumps(self.model if model is None else model), encoding="utf-8")

    def test_holdout_usa_fechas_completas_sin_fuga(self):
        validation = self.report["validacion"]
        self.assertLess(validation["fecha_fin_entrenamiento"], validation["fecha_inicio_validacion"])
        role = validation["por_rol"]["auxiliar"]
        self.assertEqual(role["dias_entrenamiento"] + role["dias_validacion"], 21)
        self.assertEqual(role["dias_validacion"], 5)
        self.assertEqual(role["baseline"]["mae_personas"], 0)
        self.assertEqual(role["metodo_seleccionado"], "baseline_dia_hora")
        encoded = json.dumps(self.model)
        self.assertNotIn("anon_qa", encoded)
        self.assertNotIn("documento:", encoded)
        self.assertEqual(self.model["metadata"]["anios"], [2023])

    def test_no_confunde_ausencia_con_cierre_y_consulta_cruce_medianoche(self):
        rows = [turno("2023-01-02", hora_inicio="22:00", hora_fin="00:30"),
                turno("2023-01-03", puesto="repartidor"),
                turno("2023-01-04", hora_inicio="22:00", hora_fin="00:30")]
        model, _ = entrenar(rows, n_estimators=2)
        profile = perfil_observado(model, "2023-01-02", "auxiliar", [300, 1320, 1440, 1455, 1470, 1740])
        self.assertEqual(profile, {300: 0, 1320: 1, 1440: 1, 1455: 1, 1470: 0, 1740: 0})
        self.assertIsNone(perfil_observado(model, "2023-01-03", "auxiliar", [720]))
        self.assertIsNone(perfil_observado(model, "2023-01-05", "auxiliar", [720]))
        self.assertEqual(perfil_cobertura(model, "2027-01-04", "inexistente", [720]), {})
        self.assertEqual(model["metadata"]["dias_observados_por_rol"]["auxiliar"], 2)

    def test_excluye_borrado_incidencias_desconocidos_duplicados_y_conflictos(self):
        rows = list(self.rows)
        rows += [turno("2023-02-01", puesto="sin_puesto"),
                 turno("2023-02-02", hora_inicio="00:30", hora_fin="00:30"),
                 turno("2023-02-03", notas_dia="Borrado documento"),
                 turno("2023-02-04", tipo="incidencia"),
                 turno("lunes 5"),
                 turno("2023-02-06", hora_inicio="03:00", hora_fin="04:00"),
                 turno("2023-02-07", duracion_minutos=500),
                 turno("2023-02-08", source_id=""),
                 dict(self.rows[0]),
                 turno("2023-02-09", source_id="conflicto"),
                 turno("2023-02-10", source_id="conflicto"),
                 turno("2023-02-11", source_id="solape-1"),
                 turno("2023-02-11", hora_inicio="13:00", hora_fin="15:00", source_id="solape-2")]
        model, report = entrenar(rows, n_estimators=2)
        self.assertEqual(report["turnos_utilizados"], 21)
        self.assertEqual(report["exclusiones"], {"borrado": 1, "duracion_cero": 1,
                         "duracion_incompatible": 1, "duplicado": 1, "fecha_u_hora_invalida": 1,
                         "incidencia": 1, "inicio_antes_dia_operativo": 1, "puesto_desconocido": 1,
                         "sin_procedencia": 1, "solapamiento_persona": 2, "source_id_conflictivo": 2})
        self.assertEqual(model["metadata"]["fecha_hasta"], "2023-01-22")

    def test_no_infiere_fecha_de_dia_semana_y_exige_cuatro_anios_si_se_solicita(self):
        with self.assertRaisesRegex(ValueError, "4 años"):
            entrenar(self.rows, min_anios=4, n_estimators=1)
        with self.assertRaisesRegex(ValueError, "No hay turnos"):
            entrenar([turno("lunes")], n_estimators=1)

    def test_rechaza_identidad_nula_sin_suponer_persona_y_admite_fin_24(self):
        rows = list(self.rows) + [turno("2023-02-01", empleado_id=None, empleado="No usar este nombre"),
                                 turno("2023-02-02", empleado_id=True),
                                 turno("2023-02-03", empleado_id=""),
                                 turno("2023-02-04", hora_inicio="22:00", hora_fin="24:00")]
        model, report = entrenar(rows, n_estimators=2)
        self.assertEqual(report["exclusiones"]["sin_identidad_para_deduplicar"], 3)
        self.assertEqual(report["turnos_utilizados"], 22)
        self.assertEqual(perfil_observado(model, "2023-02-04", "auxiliar", [1320, 1425, 1440]),
                         {1320: 1, 1425: 1, 1440: 0})

    def test_cargador_rechaza_numeros_y_metadatos_corruptos(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "modelo.json"
            mutations = [lambda m: m["perfiles"]["auxiliar"]["1"]["0"].__setitem__(0, float("nan")),
                         lambda m: m["perfiles"]["auxiliar"]["1"]["0"].__setitem__(0, float("inf")),
                         lambda m: m["perfiles"]["auxiliar"]["1"]["0"].__setitem__(0, -1),
                         lambda m: m["metadata"].__setitem__("anios", [2023, 2024, 2025, 2026]),
                         lambda m: m["metadata"].__setitem__("min_anios", 4),
                         lambda m: m["perfiles"]["auxiliar"].pop("12"),
                         lambda m: m["puntos"].pop(),
                         lambda m: m["cobertura_por_fecha"]["2023-01-02"]["auxiliar"].__setitem__(0, -1)]
            for mutation in mutations:
                with self.subTest(mutation=mutation):
                    model = copy.deepcopy(self.model)
                    mutation(model)
                    self.save(path, model)
                    with self.assertRaises(ValueError):
                        cargar_modelo(path)

    def test_inferencia_no_necesita_sklearn_numpy_ni_pickle(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "modelo.json"
            self.save(path)
            script = """
import builtins, sys
original = builtins.__import__
def guarded(name, *args, **kwargs):
    if name.split('.')[0] in {'sklearn', 'numpy', 'joblib', 'pickle'}:
        raise AssertionError('Dependencia de entrenamiento en inferencia: ' + name)
    return original(name, *args, **kwargs)
builtins.__import__ = guarded
sys.path.insert(0, sys.argv[1])
from modelo_cobertura import cargar_modelo, perfil_cobertura
model = cargar_modelo(sys.argv[2])
assert perfil_cobertura(model, '2027-01-04', 'auxiliar', [720, 840]) == {720: 1.0, 840: 0.0}
"""
            result = subprocess.run([sys.executable, "-c", script, str(Path(__file__).resolve().parents[1]), str(path)],
                                    capture_output=True, text=True)
            self.assertEqual(result.returncode, 0, result.stderr)

    def test_cli_fallido_preserva_modelo_informe_y_datos(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            model, report, data = root / "modelo.json", root / "informe.json", root / "datos.json"
            model.write_text("modelo anterior", encoding="utf-8")
            report.write_text("informe anterior", encoding="utf-8")
            data.write_text(json.dumps({"format": "mmqp.historical-training", "version": 1,
                                        "turnos": self.rows}), encoding="utf-8")
            previous = data.read_bytes()
            status = cli(["--datos", str(data), "--modelo", str(model), "--informe", str(report), "--arboles", "1"])
            self.assertEqual(status, 1)
            self.assertEqual(model.read_text(encoding="utf-8"), "modelo anterior")
            self.assertEqual(report.read_text(encoding="utf-8"), "informe anterior")
            self.assertEqual(data.read_bytes(), previous)
            data.write_text(json.dumps({"errores_extraccion": 1}), encoding="utf-8")
            self.assertEqual(cli(["--datos", str(data), "--modelo", str(model), "--informe", str(report)]), 1)
            self.assertEqual(model.read_text(encoding="utf-8"), "modelo anterior")
            data.write_text(json.dumps({"format": "mmqp.historical-training", "version": 1,
                                        "metadata": {"dataset_sha256": "0" * 64}, "turnos": self.rows}), encoding="utf-8")
            self.assertEqual(cli(["--datos", str(data), "--modelo", str(model), "--informe", str(report)]), 1)
            self.assertEqual(model.read_text(encoding="utf-8"), "modelo anterior")


if __name__ == "__main__":
    unittest.main()
