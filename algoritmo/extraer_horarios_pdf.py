"""Extrae registros de jornada PDF de texto sin OCR ni acceso a la base de datos.

Uso: python extraer_horarios_pdf.py PDF [PDF ...] --salida CARPETA
Instalacion: python -m pip install -r algoritmo/requirements-pdf.txt
Ejemplo (PowerShell, desde la raiz):
  python algoritmo/extraer_horarios_pdf.py C:/documentos/2023.pdf C:/documentos/2024.pdf C:/documentos/2025.pdf C:/documentos/2026.pdf --salida C:/documentos/resultado
Dependencia: pypdf. Cada pagina se lee en el orden del flujo PDF (columna
izquierda y despues derecha), conservando continuaciones entre paginas.
Los extremos incompletos nunca se rellenan ni se emparejan entre dias/personas.
El JSON de turnos conserva horas programadas; los fichajes se guardan aparte.
Las filas Borrado se ignoran y solo se contabilizan en la cobertura.
"""

from __future__ import annotations

import argparse
from collections import Counter, defaultdict
from datetime import datetime, timedelta
from decimal import Decimal
import hashlib
import json
from pathlib import Path
import re
import unicodedata


DAY = re.compile(r"^Dia (\d{2}/\d{2}/\d{4})\s+Fichado a las\s*Hora E/S$")
EVENT = re.compile(r"^(\d{2}:\d{2})\s*(Entrada|Salida)(.*?)\s+(No fichado|\d{2}:\d{2})$")
DAY_TOTAL = re.compile(r"^(\d{2,3}):(\d{2}) Horas$")
PERSON_TOTAL = re.compile(r"^Total Horas:\s*([\d.,]+)$")
DAYS = ("lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo")


def normalized(value):
    value = unicodedata.normalize("NFKD", value)
    return " ".join("".join(c for c in value if not unicodedata.combining(c)).lower().split())


def canonical_role(value):
    key = normalized(value)
    if "encargad" in key or "jefe de tienda" in key or "gerent" in key:
        return "encargado"
    if "repart" in key:
        return "repartidor"
    if "auxiliar" in key:
        return "auxiliar"
    if "limpi" in key:
        return "limpiadora"
    if "oficina" in key:
        return "oficina"
    return ""


def minutes(value, end=False):
    hour, minute = map(int, value.split(":"))
    if minute > 59 or hour > 24 or (hour == 24 and (minute or not end)):
        raise ValueError("Hora invalida: " + value)
    return hour * 60 + minute


def duration(start, end):
    first, last = minutes(start), minutes(end, end=True)
    return last - first if last >= first else last + 1440 - first


def digest(value):
    return hashlib.sha256(value.encode("utf-8")).hexdigest()


def page_body(text):
    """Elimina solo el encabezado repetido, sin recortar filas de jornada."""
    lines = text.splitlines()
    for index, line in enumerate(lines):
        if "de 23 de octubre." in line:
            return lines[index + 1 :]
    # Las pruebas sinteticas omiten el encabezado. Un PDF real lo exige abajo.
    return lines


def parse_pages(pages, filename, source_sha256, require_headers=False):
    shifts, issues, incomplete, workers, day_records = [], [], [], [], []
    importable_issues, raw_events = [], []
    page_records = []
    person = None
    day = None
    ordinals = defaultdict(int)

    def importable_issue(code, message, data, pages=None):
        key = (person["identidad"], day["fecha"], "incidencia")
        ordinals[key] += 1
        importable_issues.append({
            "tipo": "incidencia",
            "source_id": digest(f"{source_sha256}|{person['identidad']}|{day['fecha']}|incidencia|{ordinals[key]}"),
            "fecha": day["fecha"], "empleado": person["empleado"],
            "empleado_documento": person["empleado_documento"],
            "archivo_origen": filename, "archivo_sha256": source_sha256,
            "paginas_origen": sorted(pages or day["paginas"]),
            "notas_dia": message, "datos_origen": {"codigo": code, **data},
        })

    def issue(code, severity="error", **details):
        item = {"codigo": code, "nivel": severity, "archivo_origen": filename}
        if person:
            item.update(empleado=person["empleado"], empleado_documento=person["empleado_documento"])
        if day:
            item.update(fecha=day["fecha"], paginas_origen=sorted(day["paginas"]))
        item.update(details)
        issues.append(item)

    def finish_day():
        nonlocal day
        if day is None:
            return
        pending = None
        pairs = []
        orphans = []
        for event in day["eventos"]:
            if event["tipo"] == "Entrada":
                if pending:
                    orphans.append(pending)
                pending = event
            elif pending is None:
                orphans.append(event)
            else:
                pairs.append((pending, event))
                pending = None
        if pending:
            orphans.append(pending)
        total = 0
        for first, last in pairs:
            try:
                span = duration(first["hora"], last["hora"])
            except ValueError as error:
                issue("hora_programada_invalida", detalle=str(error))
                continue
            key = (person["identidad"], day["fecha"])
            ordinals[key] += 1
            ordinal = ordinals[key]
            source_id = digest(f"{source_sha256}|{person['identidad']}|{day['fecha']}|{ordinal}")
            raw_role = first["puesto"] or last["puesto"]
            role = canonical_role(raw_role)
            if not role:
                issue("puesto_no_indicado" if not raw_role else "puesto_desconocido", severity="error" if raw_role else "aviso", source_id=source_id, puesto_documento=raw_role)
                role = "sin_puesto"
            elif canonical_role(last["puesto"]) not in ("", role):
                issue("puesto_cambia_dentro_turno", source_id=source_id, entrada=first["puesto"], salida=last["puesto"])
            record = {
                "source_id": source_id,
                "fecha": day["fecha"],
                "fecha_o_dia": day["fecha"],
                "empleado": person["empleado"],
                "empleado_documento": person["empleado_documento"],
                "hora_inicio": first["hora"],
                "hora_fin": last["hora"],
                "fecha_fin": (datetime.fromisoformat(day["fecha"]) + timedelta(days=1 if minutes(last["hora"], end=True) < minutes(first["hora"]) or last["hora"] == "24:00" else 0)).date().isoformat(),
                "duracion_minutos": span,
                "puesto": role,
                "puesto_documento": raw_role,
                "puesto_salida_documento": last["puesto"],
                "archivo_origen": filename,
                "archivo_sha256": source_sha256,
                "paginas_origen": sorted({first["pagina"], last["pagina"]}),
                "fichaje_entrada": first["fichaje"],
                "fichaje_salida": last["fichaje"],
                "notas_dia": "; ".join(n["texto"] for n in day["notas"]),
                "ordinal_dia": ordinal,
            }
            shifts.append(record)
            person["turnos"] += 1
            total += span
        for event in orphans:
            entry = {"empleado": person["empleado"], "empleado_documento": person["empleado_documento"], "fecha": day["fecha"], "archivo_origen": filename, **event}
            incomplete.append(entry)
            issue("evento_sin_pareja", severity="aviso", evento=event)
            importable_issue("evento_sin_pareja", f"Registro original incompleto: {event['tipo']} {event['hora']} sin pareja; fichaje {event['fichaje']}.", event, {event["pagina"]})
        expected = day["minutos_documento"]
        deleted_only = not day["eventos"] and day["registros_borrado_ignorados"] > 0
        if expected is None and not deleted_only:
            issue("falta_total_diario")
        elif expected is not None and expected != total and not deleted_only:
            discrepancy = {"minutos_documento": expected, "minutos_turnos_completos": total, "eventos_sin_pareja": len(orphans)}
            issue("total_diario_no_coincide", severity="aviso", **discrepancy)
            importable_issue("total_diario_no_coincide", f"El PDF declara {expected // 60:02d}:{expected % 60:02d} horas; los intervalos Hora E/S suman {total // 60:02d}:{total % 60:02d}.", discrepancy)
        for event in day["eventos"]:
            key = (person["identidad"], day["fecha"], "evento")
            ordinals[key] += 1
            raw_events.append({"source_id": digest(f"{source_sha256}|{person['identidad']}|{day['fecha']}|evento|{ordinals[key]}"), "empleado": person["empleado"], "empleado_documento": person["empleado_documento"], "fecha": day["fecha"], "archivo_origen": filename, "archivo_sha256": source_sha256, **event})
        record = {
            "empleado": person["empleado"], "empleado_documento": person["empleado_documento"],
            "fecha": day["fecha"], "paginas_origen": sorted(day["paginas"]),
            "entradas": sum(e["tipo"] == "Entrada" for e in day["eventos"]),
            "salidas": sum(e["tipo"] == "Salida" for e in day["eventos"]),
            "turnos_completos": len(pairs), "eventos_sin_pareja": len(orphans),
            "minutos_documento": expected, "minutos_turnos_completos": total,
            "notas": day["notas"],
            "registros_borrado_ignorados": day["registros_borrado_ignorados"],
            "solo_borrados_ignorados": deleted_only,
        }
        day_records.append(record)
        person["dias"] += 1
        person["minutos_diarios_documento"] += expected or 0
        person["minutos_turnos_completos"] += total
        person["entradas"] += record["entradas"]
        person["salidas"] += record["salidas"]
        person["eventos_sin_pareja"] += len(orphans)
        day = None

    def finish_person():
        nonlocal person
        if person is None:
            return
        finish_day()
        if person["horas_documento"] is None:
            issue("falta_total_trabajador")
        else:
            # El documento redondea horas decimales a dos posiciones.
            expected = Decimal(person["horas_documento"])
            daily = Decimal(person["minutos_diarios_documento"]) / 60
            if abs(expected - daily) > Decimal("0.005"):
                issue("total_trabajador_no_coincide", severity="aviso", horas_documento=str(expected), minutos_diarios_documento=person["minutos_diarios_documento"])
        workers.append({k: sorted(v) if isinstance(v, set) else v for k, v in person.items() if k != "identidad"})
        person = None

    for number, text in pages:
        if require_headers and "de 23 de octubre." not in text:
            issue("encabezado_de_pagina_no_reconocido", pagina=number)
        lines = page_body(text)
        counters = Counter()
        if not text.strip():
            issue("pagina_sin_texto", pagina=number)
        index = 0
        while index < len(lines):
            raw = lines[index].strip()
            index += 1
            if not raw:
                continue
            if index < len(lines) and lines[index].strip().startswith("DNI:"):
                finish_person()
                document = " ".join(lines[index].split())
                index += 1
                name = " ".join(raw.split())
                person = {"empleado": name, "empleado_documento": document, "identidad": digest(normalized(document)), "paginas_origen": {number}, "dias": 0, "turnos": 0, "entradas": 0, "salidas": 0, "eventos_sin_pareja": 0, "minutos_diarios_documento": 0, "minutos_turnos_completos": 0, "horas_documento": None}
                counters["trabajadores_iniciados"] += 1
                continue
            if person:
                person["paginas_origen"].add(number)
            match = DAY.fullmatch(raw)
            if match:
                finish_day()
                if not person:
                    issue("dia_sin_trabajador", pagina=number, texto=raw)
                    continue
                date = datetime.strptime(match[1], "%d/%m/%Y").date().isoformat()
                day = {"fecha": date, "eventos": [], "notas": [], "paginas": {number}, "minutos_documento": None, "registros_borrado_ignorados": 0}
                counters["dias"] += 1
                continue
            match = EVENT.fullmatch(raw)
            if match:
                counters["entradas" if match[2] == "Entrada" else "salidas"] += 1
                if not day:
                    issue("evento_sin_dia", pagina=number, texto=raw)
                    continue
                day["paginas"].add(number)
                day["eventos"].append({"tipo": match[2], "hora": match[1], "puesto": match[3].strip(" -"), "fichaje": match[4], "pagina": number, "texto_original": raw})
                continue
            match = DAY_TOTAL.fullmatch(raw)
            if match:
                counters["totales_diarios"] += 1
                if not day:
                    issue("total_diario_sin_dia", pagina=number, texto=raw)
                elif day["minutos_documento"] is not None:
                    issue("total_diario_repetido", pagina=number, texto=raw)
                else:
                    day["minutos_documento"] = int(match[1]) * 60 + int(match[2])
                    day["paginas"].add(number)
                continue
            match = PERSON_TOTAL.fullmatch(raw)
            if match:
                counters["totales_trabajador"] += 1
                if not person:
                    issue("total_sin_trabajador", pagina=number, texto=raw)
                else:
                    finish_day()
                    person["horas_documento"] = match[1].replace(",", ".")
                    finish_person()
                continue
            if re.match(r"^Borrado\b", raw, re.IGNORECASE):
                counters["registros_borrado_ignorados"] += 1
                if day:
                    day["registros_borrado_ignorados"] += 1
                    day["paginas"].add(number)
                continue
            if raw.startswith("EL TRABAJADOR") and "POR LA EMPRESA" in raw:
                counters["firmas"] += 1
                continue
            issue("linea_no_reconocida", pagina=number, texto=raw)
            counters["lineas_no_reconocidas"] += 1
        page_records.append({"pagina": number, "caracteres": len(text), **dict(counters)})
    finish_person()
    if len(raw_events) != 2 * len(shifts) + len(incomplete):
        issue("cobertura_eventos_incompleta", eventos_origen=len(raw_events), turnos=len(shifts), eventos_sin_pareja=len(incomplete))
    represented_days = {(r["empleado_documento"], r["fecha"]) for r in shifts + importable_issues}
    missing_days = [r for r in day_records if not r["solo_borrados_ignorados"] and (r["empleado_documento"], r["fecha"]) not in represented_days]
    for missing in missing_days:
        issue("dia_sin_representacion_importable", empleado_documento=missing["empleado_documento"], fecha=missing["fecha"], paginas_origen=missing["paginas_origen"])
    counts = Counter(code["codigo"] for code in issues)
    report = {
        "archivo_origen": filename, "archivo_sha256": source_sha256,
        "paginas": len(page_records), "cobertura_paginas": page_records,
        "trabajadores": len(workers), "dias": len(day_records),
        "entradas": sum(p.get("entradas", 0) for p in page_records),
        "salidas": sum(p.get("salidas", 0) for p in page_records),
        "turnos_completos": len(shifts), "eventos_sin_pareja": len(incomplete),
        "registros_borrado_ignorados": sum(p.get("registros_borrado_ignorados", 0) for p in page_records),
        "minutos_turnos_completos": sum(s["duracion_minutos"] for s in shifts),
        "minutos_diarios_documento": sum(w["minutos_diarios_documento"] for w in workers),
        "errores": sum(i["nivel"] == "error" for i in issues),
        "avisos": sum(i["nivel"] == "aviso" for i in issues),
        "incidencias_por_codigo": dict(counts), "incidencias": issues,
        "resumen_trabajadores": workers, "cobertura_dias": day_records,
        "eventos_incompletos": incomplete,
        "incidencias_importables": importable_issues,
        "eventos_origen": raw_events,
        "comprobaciones": {"todos_eventos_representados": len(raw_events) == 2 * len(shifts) + len(incomplete), "todos_dias_representados": not missing_days, "todos_paginas_leidas": [p["pagina"] for p in page_records] == list(range(1, len(page_records) + 1))},
    }
    return shifts, report


def extract_pdf(path):
    from pypdf import PdfReader

    path = Path(path).resolve(strict=True)
    source_hash = hashlib.sha256(path.read_bytes()).hexdigest()
    reader = PdfReader(path)
    pages = ((number, page.extract_text(extraction_mode="plain") or "") for number, page in enumerate(reader.pages, 1))
    return parse_pages(pages, path.name, source_hash, require_headers=True)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("pdf", nargs="+", type=Path)
    parser.add_argument("--salida", required=True, type=Path)
    options = parser.parse_args(argv)
    if len({p.resolve() for p in options.pdf}) != len(options.pdf):
        parser.error("No se puede incluir dos veces el mismo PDF.")
    options.salida.mkdir(parents=True, exist_ok=True)
    combined, reports, incidents, events = [], [], [], []
    for path in options.pdf:
        shifts, report = extract_pdf(path)
        combined.extend(shifts)
        incidents.extend(report.pop("incidencias_importables"))
        events.extend(report.pop("eventos_origen"))
        reports.append(report)
        print(f"{path.name}: {report['paginas']} paginas, {len(shifts)} turnos, {report['errores']} errores y {report['avisos']} avisos", flush=True)
    combined.sort(key=lambda row: (row["fecha"], row["empleado"], row["hora_inicio"], row["source_id"]))
    if len({row["source_id"] for row in combined + incidents}) != len(combined) + len(incidents):
        raise RuntimeError("source_id duplicado; no se puede considerar valida la extraccion.")
    report = {"version": 1, "criterio_horas": "Hora E/S programada; fichajes conservados aparte", "archivos": reports, "archivos_procesados": len(reports), "paginas": sum(r["paginas"] for r in reports), "turnos_completos": len(combined), "errores": sum(r["errores"] for r in reports), "avisos": sum(r["avisos"] for r in reports)}
    envelope = {
        "version": 1, "errores_extraccion": report["errores"],
        "origen": "registro_jornada_pdf_texto",
        "manifiesto_archivos": [{key: source[key] for key in ("archivo_origen", "archivo_sha256", "paginas", "dias", "entradas", "salidas", "turnos_completos")} for source in reports],
        "turnos": combined, "incidencias": incidents,
    }
    for name, data in (("turnos_historicos_pdf.json", envelope), ("informe_cobertura_pdf.json", report), ("eventos_origen_pdf.json", events)):
        (options.salida / name).write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return 2 if report["errores"] else 0


if __name__ == "__main__":
    raise SystemExit(main())
