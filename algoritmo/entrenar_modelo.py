"""Entrena cobertura anónima desde la exportación documental de MMQP.

python algoritmo/entrenar_modelo.py --datos algoritmo/datos_entrenamiento_historico.json
No utiliza el antiguo OCR datos_entrenamiento.json ni preferencias de empleados.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import sys
import tempfile

from modelo_cobertura import entrenar as entrenar_cobertura


def _escritura_atomica(path, value):
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = None
    try:
        with tempfile.NamedTemporaryFile(mode="w", encoding="utf-8", dir=path.parent,
                                         prefix="." + path.name + ".", suffix=".tmp", delete=False) as stream:
            temporary = Path(stream.name)
            json.dump(value, stream, ensure_ascii=False, separators=(",", ":"), allow_nan=False)
            stream.write("\n")
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temporary, path)
    finally:
        if temporary is not None and temporary.exists():
            temporary.unlink()


def entrenar(argv=None):
    base = Path(__file__).resolve().parent
    parser = argparse.ArgumentParser(description="Aprende cobertura histórica documentada sin asignar nombres.")
    parser.add_argument("--datos", type=Path, default=base / "datos_entrenamiento_historico.json")
    parser.add_argument("--modelo", type=Path, default=base / "modelo_cobertura.json")
    parser.add_argument("--informe", type=Path, default=base / "informe_entrenamiento.json")
    parser.add_argument("--min-anios", type=int, default=4)
    parser.add_argument("--arboles", type=int, default=80)
    args = parser.parse_args(argv)
    try:
        if len({args.datos.resolve(), args.modelo.resolve(), args.informe.resolve()}) != 3:
            raise ValueError("Datos, modelo e informe deben usar rutas diferentes.")
        with args.datos.open(encoding="utf-8-sig") as source:
            document = json.load(source)
        metadata = {}
        if isinstance(document, dict):
            if document.get("errores_extraccion", 0):
                raise ValueError("La fuente declara errores de extracción: corrígelos antes de entrenar.")
            if document.get("format") != "mmqp.historical-training" or document.get("version") != 1:
                raise ValueError("Usa la exportación documental horarios:exportar-entrenamiento (versión 1).")
            rows, metadata = document.get("turnos"), document.get("metadata", {})
            if not isinstance(metadata, dict):
                raise ValueError("Los metadatos de procedencia deben ser un objeto.")
            if "exported_shifts" in metadata and metadata["exported_shifts"] != len(rows):
                raise ValueError("El número de turnos no coincide con la exportación.")
            if "dataset_sha256" in metadata:
                encoded = json.dumps(rows, ensure_ascii=False, separators=(",", ":"), allow_nan=False).encode("utf-8")
                if hashlib.sha256(encoded).hexdigest() != metadata["dataset_sha256"]:
                    raise ValueError("El SHA256 no coincide: la exportación ha cambiado o está dañada.")
        else:
            rows = document
        model, report = entrenar_cobertura(rows, n_estimators=args.arboles,
                                          source_metadata=metadata, min_anios=args.min_anios)
        # Se calculan y validan ambos documentos antes de sustituir archivos.
        _escritura_atomica(args.informe, report)
        _escritura_atomica(args.modelo, model)
    except (OSError, ValueError, TypeError, ImportError) as exc:
        print(f"No se entrenó el modelo: {exc}", file=sys.stderr)
        return 1
    meta = model["metadata"]
    print(f"Modelo entrenado: {meta['turnos_utilizados']} turnos, años {', '.join(map(str, meta['anios']))}.")
    print(f"Fechas: {meta['fecha_desde']} a {meta['fecha_hasta']}. Exclusiones: {meta['exclusiones']}.")
    for role, result in meta["validacion"]["por_rol"].items():
        forest, baseline = result["random_forest"], result["baseline"]
        if forest and baseline:
            print(f"{role}: MAE bosque={forest['mae_personas']}, baseline={baseline['mae_personas']}; "
                  f"seleccionado={result['metodo_seleccionado']}.")
    print(f"Modelo: {args.modelo}\nInforme: {args.informe}")
    return 0


if __name__ == "__main__":
    sys.exit(entrenar())
