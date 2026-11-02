"""Checks for demo-specific boundaries, using invented request data only."""
import importlib.util
from pathlib import Path
import sys
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import generador_horarios as generator


class DemoPrivacyTests(unittest.TestCase):
    def test_generator_does_not_load_identity_preference_models(self):
        self.assertIsNone(generator.modelo_ml)

    def test_unexpected_error_does_not_return_internal_details(self):
        with patch.object(generator, 'generar_horarios', side_effect=RuntimeError('private-path-and-details')):
            with self.assertLogs(generator.app.logger, level='ERROR'):
                response = generator.app.test_client().post('/generar', json={'demo': True})
        self.assertEqual(500, response.status_code)
        self.assertFalse(response.json['success'])
        self.assertNotIn('traceback', response.json)
        self.assertNotIn('private-path-and-details', response.get_data(as_text=True))

    def test_external_ocr_is_disabled_before_opening_or_sending_an_image(self):
        path = Path(__file__).resolve().parents[1] / 'extraer_horarios_ocr.py'
        spec = importlib.util.spec_from_file_location('demo_ocr', path)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        with self.assertRaisesRegex(RuntimeError, 'no está disponible en MMQP demo'):
            module.extraer_horario_de_imagen('invented.png', 'invented.png', 'auxiliar')


if __name__ == '__main__':
    unittest.main()
