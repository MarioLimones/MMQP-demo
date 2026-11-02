import importlib.util
from pathlib import Path
import unittest


SPEC = importlib.util.spec_from_file_location("extraer_horarios_pdf", Path(__file__).parents[1] / "extraer_horarios_pdf.py")
pdf = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(pdf)


def parse(*pages):
    return pdf.parse_pages(enumerate(pages, 1), "registro.pdf", "a" * 64)


class PdfScheduleExtractionTests(unittest.TestCase):
    def test_programmed_times_midnight_continuation_ignores_deleted_rows(self):
        shifts, report = parse("""PERSONA A, ANA
DNI: Num. Afiliación:123
Dia 31/12/2025 Fichado a lasHora E/S
22:00Entrada  -  repartidor 40 horas 22:07
Borrado 22:08""", """01:30Salida  -  repartidor 40 horas No fichado
03:30 Horas
Total Horas: 3,5
EL TRABAJADOR POR LA EMPRESA""")
        self.assertEqual(0, report["errores"])
        self.assertEqual(1, len(shifts))
        self.assertEqual("22:00", shifts[0]["hora_inicio"])
        self.assertEqual("22:07", shifts[0]["fichaje_entrada"])
        self.assertEqual("No fichado", shifts[0]["fichaje_salida"])
        self.assertEqual("2026-01-01", shifts[0]["fecha_fin"])
        self.assertEqual([1, 2], shifts[0]["paginas_origen"])
        self.assertEqual("", shifts[0]["notas_dia"])
        self.assertEqual([], report["incidencias_importables"])
        self.assertEqual([], report["cobertura_dias"][0]["notas"])
        self.assertEqual(1, report["registros_borrado_ignorados"])
        self.assertEqual(2, len(report["eventos_origen"]))
        self.assertEqual(210, report["minutos_turnos_completos"])

    def test_deleted_only_day_is_covered_without_an_importable_record(self):
        shifts, report = parse("""PERSONA A, ANA
DNI: 123
Dia 01/01/2025 Fichado a lasHora E/S
Borrado 13:01
Borrado 11:33
00:00 Horas
Total Horas: 0""")
        self.assertEqual([], shifts)
        self.assertEqual([], report["incidencias_importables"])
        self.assertEqual([], report["eventos_origen"])
        self.assertEqual([], report["incidencias"])
        self.assertEqual(2, report["registros_borrado_ignorados"])
        self.assertEqual(1, report["dias"])
        self.assertEqual([], report["cobertura_dias"][0]["notas"])
        self.assertTrue(report["cobertura_dias"][0]["solo_borrados_ignorados"])
        self.assertTrue(all(report["comprobaciones"].values()))

    def test_deleted_rows_do_not_change_valid_shifts_or_incomplete_events(self):
        source = """PERSONA A, ANA
DNI: 123
Dia 01/01/2025 Fichado a lasHora E/S
13:00Entrada  -  auxiliar tienda 40 12:59
16:00Salida  -  auxiliar tienda 40 16:02
20:00Entrada  -  auxiliar tienda 40 20:00
03:00 Horas
Total Horas: 3"""
        expected_shifts, expected_report = parse(source)
        shifts, report = parse(source.replace("16:00Salida", "Borrado 13:01\n16:00Salida").replace("03:00 Horas", "BORRADO 20:01\n03:00 Horas"))
        self.assertEqual(expected_shifts, shifts)
        self.assertEqual(expected_report["incidencias_importables"], report["incidencias_importables"])
        self.assertEqual(expected_report["eventos_origen"], report["eventos_origen"])
        self.assertEqual(1, report["eventos_sin_pareja"])
        self.assertEqual(3, len(report["eventos_origen"]))
        self.assertEqual(2, report["registros_borrado_ignorados"])
        self.assertFalse(report["cobertura_dias"][0]["solo_borrados_ignorados"])

    def test_deleted_row_without_a_day_is_ignored(self):
        shifts, report = parse("Borrado 13:01")
        self.assertEqual([], shifts)
        self.assertEqual([], report["incidencias"])
        self.assertEqual([], report["incidencias_importables"])
        self.assertEqual([], report["eventos_origen"])
        self.assertEqual(1, report["registros_borrado_ignorados"])

    def test_never_pairs_between_workers_or_days(self):
        shifts, report = parse("""PERSONA A, ANA
DNI: Num. Afiliación:123
Dia 01/01/2025 Fichado a lasHora E/S
20:00Entrada  -  auxiliar tienda 40 No fichado
00:00 Horas
Dia 02/01/2025 Fichado a lasHora E/S
22:00Salida  -  auxiliar tienda 40 No fichado
00:00 Horas
Total Horas: 0
PERSONA B, BEA
DNI: Num. Afiliación:456
Dia 01/01/2025 Fichado a lasHora E/S
23:00Salida  -  repartidor 40 horas No fichado
00:00 Horas
Total Horas: 0""")
        self.assertEqual([], shifts)
        self.assertEqual(3, report["eventos_sin_pareja"])
        self.assertEqual(3, report["incidencias_por_codigo"]["evento_sin_pareja"])
        self.assertEqual(3, len(report["incidencias_importables"]))
        self.assertEqual(3, len(report["eventos_origen"]))
        self.assertTrue(all(i["tipo"] == "incidencia" for i in report["incidencias_importables"]))
        self.assertEqual(2, report["trabajadores"])

    def test_double_shift_decimal_rounding_and_ids_are_stable(self):
        source = """PERSONA A, ANA
DNI: Num. Afiliación:123
Dia 01/01/2025 Fichado a lasHora E/S
13:00Entrada  -  auxiliar tienda 40 12:59
16:00Salida  -  auxiliar tienda 40 16:02
20:00Entrada  -  auxiliar tienda 40 20:00
20:01Salida  -  auxiliar tienda 40 No fichado
03:01 Horas
Total Horas: 3,02"""
        first, report = parse(source)
        second, _ = parse(source)
        self.assertEqual(0, report["errores"])
        self.assertEqual([r["source_id"] for r in first], [r["source_id"] for r in second])
        self.assertEqual(2, len(set(r["source_id"] for r in first)))

    def test_total_and_unrecognized_content_are_errors(self):
        _, report = parse("""PERSONA A, ANA
DNI: 123
Dia 01/01/2025 Fichado a lasHora E/S
20:00Entrada  -  auxiliar tienda 40 No fichado
22:00Salida  -  auxiliar tienda 40 No fichado
03:00 Horas
fila desconocida
Total Horas: 4""")
        self.assertEqual(1, report["incidencias_por_codigo"]["total_diario_no_coincide"])
        self.assertEqual(1, report["incidencias_por_codigo"]["total_trabajador_no_coincide"])
        self.assertEqual(1, report["incidencias_por_codigo"]["linea_no_reconocida"])

    def test_roleless_shift_is_preserved_without_inventing_role(self):
        shifts, report = parse("""PERSONA A, ANA
DNI: 123
Dia 01/01/2025 Fichado a lasHora E/S
14:15Entrada 14:15
14:16Salida 14:16
00:01 Horas
Total Horas: 0,02""")
        self.assertEqual(0, report["errores"])
        self.assertEqual("sin_puesto", shifts[0]["puesto"])
        self.assertEqual(1, report["avisos"])


if __name__ == "__main__":
    unittest.main()
