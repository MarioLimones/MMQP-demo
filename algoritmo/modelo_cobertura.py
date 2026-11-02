"""Cobertura programada anónima: entrenamiento sklearn e inferencia solo JSON.

No aprende nombres/asignaciones ni mide demanda. Los días sin registros de un
puesto son desconocidos, nunca cierres. El día operativo abarca 05:00–05:00.
"""
from __future__ import annotations

from collections import Counter, defaultdict
from datetime import date, datetime, timezone
import hashlib
import json
import math
from pathlib import Path
import re
import unicodedata

FORMATO = "mmqp.coverage-model"
VERSION = 1
ROLES = ("auxiliar", "encargado", "limpiadora", "repartidor")
INICIO_DIA, FIN_DIA = 300, 1740


def _normalizar(value):
    return "".join(c for c in unicodedata.normalize("NFD", str(value).lower().strip())
                   if unicodedata.category(c) != "Mn")


def _fecha(value):
    if not isinstance(value, str) or not re.fullmatch(r"\d{4}-\d{2}-\d{2}", value):
        raise ValueError("Cada turno necesita su fecha documental ISO completa.")
    return date.fromisoformat(value)


def _minuto(value, *, fin=False):
    if fin and value == "24:00":
        return 1440
    if not isinstance(value, str) or not re.fullmatch(r"(?:[01]\d|2[0-3]):[0-5]\d", value):
        raise ValueError("Hora inválida.")
    hour, minute = map(int, value.split(":"))
    return hour * 60 + minute


def _caracteristicas(day, point):
    leap = day.year % 4 == 0 and (day.year % 100 != 0 or day.year % 400 == 0)
    angle = 2 * math.pi * (day.timetuple().tm_yday - 1) / (366 if leap else 365)
    hour_angle = 2 * math.pi * (point % 1440) / 1440
    return [day.weekday(), math.sin(angle), math.cos(angle), point,
            math.sin(hour_angle), math.cos(hour_angle)]


def _limpiar(rows):
    if not isinstance(rows, list):
        raise ValueError("Los datos deben contener una lista de turnos.")
    reasons, candidates, source_groups = Counter(), [], defaultdict(list)
    for raw in rows:
        if not isinstance(raw, dict):
            reasons["fila_invalida"] += 1
            continue
        if raw.get("tipo", "turno") != "turno":
            reasons["incidencia"] += 1
            continue
        origin = raw.get("datos_origen", {})
        code = origin.get("codigo", "") if isinstance(origin, dict) else ""
        if (raw.get("borrado") or _normalizar(raw.get("estado", "")) == "borrado"
                or _normalizar(code) == "borrado_documento"
                or re.match(r"^borrado\b", _normalizar(raw.get("notas_dia", "")))):
            reasons["borrado"] += 1
            continue
        role = _normalizar(raw.get("puesto", raw.get("rol_necesario", "")))
        if role not in ROLES:
            reasons["puesto_desconocido"] += 1
            continue
        try:
            day = _fecha(raw.get("fecha"))
            start = _minuto(raw.get("hora_inicio"))
            end = _minuto(raw.get("hora_fin"), fin=True)
        except (ValueError, TypeError):
            reasons["fecha_u_hora_invalida"] += 1
            continue
        if start == end:
            reasons["duracion_cero"] += 1
            continue
        duration = (end - start) % 1440
        # Una fecha documental a las 03:00 pertenece al día civil indicado.
        # No se traslada a la madrugada del día siguiente para completar datos.
        if start < INICIO_DIA:
            reasons["inicio_antes_dia_operativo"] += 1
            continue
        end = start + duration
        if end > FIN_DIA or duration > 18 * 60:
            reasons["fuera_dia_operativo_o_duracion"] += 1
            continue
        declared = raw.get("duracion_minutos")
        if declared is not None and (isinstance(declared, bool) or declared != duration):
            reasons["duracion_incompatible"] += 1
            continue
        source = raw.get("source_id")
        if not isinstance(source, str) or not source.strip():
            reasons["sin_procedencia"] += 1
            continue
        raw_identity = raw.get("empleado_id", raw.get("empleado", ""))
        if isinstance(raw_identity, bool) or not isinstance(raw_identity, (str, int)):
            reasons["sin_identidad_para_deduplicar"] += 1
            continue
        identity = str(raw_identity).strip()
        if not identity:
            reasons["sin_identidad_para_deduplicar"] += 1
            continue
        row = (day, role, start, end, identity, source.strip())
        candidates.append(row)
        source_groups[row[5]].append(row)
    conflicting_sources = {key for key, group in source_groups.items()
                           if len({r[:5] for r in group}) > 1}
    accepted, seen_sources, seen_semantic = [], set(), set()
    for row in candidates:
        if row[5] in conflicting_sources:
            reasons["source_id_conflictivo"] += 1
        elif row[5] in seen_sources or row[:5] in seen_semantic:
            reasons["duplicado"] += 1
        else:
            seen_sources.add(row[5])
            seen_semantic.add(row[:5])
            accepted.append(row)
    # También detecta conflictos entre fechas civiles contiguas.
    by_person = defaultdict(list)
    for index, row in enumerate(accepted):
        by_person[row[4]].append((row[0].toordinal() * 1440 + row[2],
                                 row[0].toordinal() * 1440 + row[3], index))
    conflicts = set()
    for intervals in by_person.values():
        intervals.sort()
        active = []
        for start, end, index in intervals:
            active = [(finish, previous) for finish, previous in active if finish > start]
            if active:
                conflicts.add(index)
                conflicts.update(previous for _, previous in active)
            active.append((end, index))
    if conflicts:
        reasons["solapamiento_persona"] += len(conflicts)
    return [r for i, r in enumerate(accepted) if i not in conflicts], dict(sorted(reasons.items()))


def _cobertura(rows, points):
    coverage = {}
    for day, role, start, end, *_ in rows:
        vector = coverage.setdefault(day.isoformat(), {}).setdefault(role, [0] * len(points))
        for index, point in enumerate(points):
            vector[index] += int(start <= point < end)
    return coverage


def _baseline(dates, vectors, points):
    sums, counts = defaultdict(lambda: [0.0] * len(points)), Counter()
    for day, vector in zip(dates, vectors):
        weekday = day.weekday()
        counts[weekday] += 1
        sums[weekday] = [a + b for a, b in zip(sums[weekday], vector)]
    overall = [sum(v[i] for v in vectors) / len(vectors) for i in range(len(points))]
    return {weekday: [v / counts[weekday] for v in sums[weekday]] if counts[weekday] else overall
            for weekday in range(7)}


def _metricas(real, predicted, points):
    errors = [abs(a - b) for a, b in zip(real, predicted)]
    active = [error for error, target in zip(errors, real) if target > 0]
    width = len(points)
    daily = [abs(sum(real[i:i + width]) - sum(predicted[i:i + width])) * 24 / width
             for i in range(0, len(real), width)]
    return {"mae_personas": round(sum(errors) / len(errors), 6),
            "mae_personas_con_cobertura": round(sum(active) / len(active), 6) if active else None,
            "mae_horas_persona_dia": round(sum(daily) / len(daily), 6),
            "puntos": len(real), "dias": len(daily)}


def entrenar(rows, *, intervalo=15, test_fraction=0.2, random_state=42,
             n_estimators=80, source_metadata=None, min_anios=1):
    """Devuelve (artefacto, informe) sin escribir archivos ni cambiar la fuente.

    El último 20 % de las fechas completas se reserva antes de entrenar.
    El bosque solo se selecciona si mejora el baseline de día de semana/hora.
    El método elegido después se ajusta con todos los días válidos.
    """
    if intervalo != 15 or not 0 < test_fraction < 0.5 or min_anios < 1 or n_estimators < 1:
        raise ValueError("Configuración de entrenamiento inválida.")
    clean, exclusions = _limpiar(rows)
    if not clean:
        raise ValueError("No hay turnos documentales válidos para entrenar.")
    points = list(range(INICIO_DIA, FIN_DIA, intervalo))
    coverage = _cobertura(clean, points)
    dates = sorted(_fecha(key) for key in coverage)
    years = sorted({d.year for d in dates})
    if len(years) < min_anios:
        raise ValueError(f"Se requieren {min_anios} años documentados; solo hay {len(years)}.")
    if len(dates) < 3:
        raise ValueError("Se necesitan al menos tres fechas completas para validar cronológicamente.")
    split = max(1, min(len(dates) - 1, int(len(dates) * (1 - test_fraction))))
    cutoff = dates[split]
    # La inferencia no importa dependencias científicas ni carga código serializado.
    import numpy as np
    from sklearn.ensemble import RandomForestRegressor

    profiles, validation, support, observed = {}, {}, {}, {}
    for role in ROLES:
        role_dates = [d for d in dates if role in coverage[d.isoformat()]]
        if not role_dates:
            continue
        train_dates = [d for d in role_dates if d < cutoff]
        test_dates = [d for d in role_dates if d >= cutoff]
        vectors = [coverage[d.isoformat()][role] for d in role_dates]
        baseline_all = _baseline(role_dates, vectors, points)
        observed[role] = len(role_dates)
        support[role] = dict(sorted(Counter(f"{d.month:02d}-{d.weekday()}" for d in role_dates).items()))

        def data(days):
            x = np.asarray([_caracteristicas(d, p) for d in days for p in points], dtype=np.float32)
            y = np.asarray([v for d in days for v in coverage[d.isoformat()][role]], dtype=np.float32)
            return x, y

        method = "baseline_dia_hora"
        record = {"metodo_seleccionado": method, "dias_entrenamiento": len(train_dates),
                  "dias_validacion": len(test_dates), "random_forest": None, "baseline": None}
        if train_dates and test_dates:
            x_train, y_train = data(train_dates)
            x_test, y_test = data(test_dates)
            forest = RandomForestRegressor(n_estimators=n_estimators, max_depth=16,
                                            min_samples_leaf=8, random_state=random_state, n_jobs=-1)
            forest.fit(x_train, y_train)
            prediction = forest.predict(x_test)
            baseline_train = _baseline(train_dates, [coverage[d.isoformat()][role] for d in train_dates], points)
            baseline_prediction = [v for d in test_dates for v in baseline_train[d.weekday()]]
            record["random_forest"] = _metricas(y_test.tolist(), prediction.tolist(), points)
            record["baseline"] = _metricas(y_test.tolist(), baseline_prediction, points)
            if record["random_forest"]["mae_personas"] < record["baseline"]["mae_personas"]:
                method = "random_forest"
                x_all, y_all = data(role_dates)
                forest.fit(x_all, y_all)
        else:
            record["aviso"] = "Sin fechas a ambos lados del corte: baseline sin validación para este puesto."
        record["metodo_seleccionado"] = method
        validation[role] = record
        # Promedio de fechas de cada mes/weekday en un año de referencia bisiesto.
        profile_dates = []
        for month in range(1, 13):
            for day in range(1, 32):
                try:
                    profile_dates.append(date(2024, month, day))
                except ValueError:
                    pass
        if method == "random_forest":
            features = [_caracteristicas(d, p) for d in profile_dates for p in points]
            predicted = forest.predict(np.asarray(features, dtype=np.float32)).reshape(len(profile_dates), len(points))
        else:
            predicted = np.asarray([baseline_all[d.weekday()] for d in profile_dates])
        profiles[role] = {}
        for month in range(1, 13):
            profiles[role][str(month)] = {}
            for weekday in range(7):
                indexes = [i for i, d in enumerate(profile_dates) if d.month == month and d.weekday() == weekday]
                profiles[role][str(month)][str(weekday)] = [round(max(0.0, float(v)), 6)
                                                           for v in predicted[indexes].mean(axis=0)]

    canonical = sorted((d.isoformat(), role, start, end, identity, source)
                       for d, role, start, end, identity, source in clean)
    digest = hashlib.sha256(json.dumps(canonical, separators=(",", ":"), ensure_ascii=True).encode()).hexdigest()
    metadata = {"turnos_recibidos": len(rows), "turnos_utilizados": len(clean), "anios": years,
                "turnos_por_anio": dict(sorted(Counter(str(r[0].year) for r in clean).items())),
                "turnos_por_rol": dict(sorted(Counter(r[1] for r in clean).items())),
                "fecha_desde": dates[0].isoformat(), "fecha_hasta": dates[-1].isoformat(),
                "fecha_max": dates[-1].isoformat(), "sha256_datos": digest, "min_anios": min_anios,
                "entrenamiento": {"candidato": "RandomForestRegressor", "n_estimators": n_estimators,
                                  "max_depth": 16, "min_samples_leaf": 8, "random_state": random_state,
                                  "features": ["dia_semana", "sin_anio", "cos_anio", "minuto_operativo", "sin_hora", "cos_hora"]},
                "dias_observados_por_rol": observed, "soporte_mes_dia_por_rol": support,
                "exclusiones": exclusions, "fuente": _fuente_publica(source_metadata),
                "politica_ceros": "Solo dentro de fechas con al menos un turno válido observado de ese puesto; días sin ese puesto son desconocidos.",
                "objetivo": "Cobertura programada histórica; no mide demanda, ventas ni preferencias personales.",
                "validacion": {"tipo": "holdout_cronologico_fechas_completas", "fecha_corte": cutoff.isoformat(),
                               "fecha_fin_entrenamiento": dates[split - 1].isoformat(),
                               "fecha_inicio_validacion": cutoff.isoformat(),
                               "dias_entrenamiento_total": split, "dias_validacion_total": len(dates) - split,
                               "fraccion_solicitada": test_fraction, "por_rol": validation,
                               "limitacion": "Holdout usado para comparar y seleccionar el método; no es un test independiente posterior a esa selección. El último periodo puede no representar todas las estaciones. Cobertura muestreada cada 15 minutos; no es una validación futura de demanda."}}
    artifact = {"format": FORMATO, "version": VERSION,
                "created_at": datetime.now(timezone.utc).isoformat(),
                "intervalo_minutos": intervalo, "puntos": points, "metadata": metadata,
                "perfiles": profiles, "cobertura_por_fecha": coverage}
    _validar_modelo(artifact)
    return artifact, {"format": "mmqp.coverage-training-report", "version": VERSION,
                      "created_at": artifact["created_at"], **metadata}


def _fuente_publica(metadata):
    """Solo conserva agregados conocidos, nunca nombres, documentos ni notas."""
    if not isinstance(metadata, dict):
        return {}
    keys = ("historical_schedules", "exported_shifts", "excluded_incidents", "by_year",
            "by_role", "date_min", "date_max", "dataset_sha256")
    result = {key: metadata[key] for key in keys if key in metadata}
    if isinstance(metadata.get("source_files"), list):
        result["source_files"] = [{key: source[key] for key in ("archivo_sha256", "turnos") if key in source}
                                  for source in metadata["source_files"] if isinstance(source, dict)]
    return result


def _vector_valido(vector, length):
    return (isinstance(vector, list) and len(vector) == length
            and all(isinstance(v, (int, float)) and not isinstance(v, bool)
                    and math.isfinite(v) and v >= 0 for v in vector))


def _validar_modelo(model):
    if (not isinstance(model, dict) or model.get("format") != FORMATO
            or type(model.get("version")) is not int or model["version"] != VERSION):
        raise ValueError("Formato de modelo histórico incompatible.")
    points = list(range(INICIO_DIA, FIN_DIA, 15))
    if (type(model.get("intervalo_minutos")) is not int or model["intervalo_minutos"] != 15
            or model.get("puntos") != points or any(type(v) is not int for v in model["puntos"])):
        raise ValueError("La cuadrícula del modelo debe cubrir 05:00–05:00 en cuartos de hora.")
    meta = model.get("metadata")
    if not isinstance(meta, dict):
        raise ValueError("Faltan metadatos del modelo.")
    years, minimum = meta.get("anios"), meta.get("min_anios")
    if (not isinstance(years, list) or not years or any(type(y) is not int or not 1900 <= y <= 9999 for y in years)
            or years != sorted(set(years)) or type(minimum) is not int or minimum < 1 or len(years) < minimum):
        raise ValueError("Los años documentados del modelo son inválidos o insuficientes.")
    first, last = _fecha(meta.get("fecha_desde")), _fecha(meta.get("fecha_hasta"))
    if first > last or first.year != years[0] or last.year != years[-1] or meta.get("fecha_max") != last.isoformat():
        raise ValueError("El rango de fechas del modelo es incompatible con sus años.")
    if (type(meta.get("turnos_utilizados")) is not int or meta["turnos_utilizados"] < 1
            or not re.fullmatch(r"[a-f0-9]{64}", str(meta.get("sha256_datos", "")))):
        raise ValueError("Procedencia del modelo inválida.")
    profiles = model.get("perfiles")
    if not isinstance(profiles, dict) or not profiles or not set(profiles).issubset(ROLES):
        raise ValueError("Puestos del modelo inválidos.")
    for months in profiles.values():
        if not isinstance(months, dict) or set(months) != {str(m) for m in range(1, 13)}:
            raise ValueError("Faltan meses en los perfiles del modelo.")
        for weekdays in months.values():
            if not isinstance(weekdays, dict) or set(weekdays) != {str(d) for d in range(7)}:
                raise ValueError("Faltan días de semana en los perfiles del modelo.")
            if not all(_vector_valido(v, len(points)) for v in weekdays.values()):
                raise ValueError("Cobertura inválida: debe ser finita y no negativa.")
    observed = model.get("cobertura_por_fecha")
    if not isinstance(observed, dict) or not observed:
        raise ValueError("Faltan coberturas observadas fechadas.")
    observed_years = set()
    for key, roles in observed.items():
        day = _fecha(key)
        observed_years.add(day.year)
        if (not first <= day <= last or not isinstance(roles, dict) or not roles
                or not set(roles).issubset(profiles)
                or not all(_vector_valido(v, len(points)) for v in roles.values())):
            raise ValueError("Cobertura documental fechada inválida.")
    if sorted(observed_years) != years or min(observed) != first.isoformat() or max(observed) != last.isoformat():
        raise ValueError("Los años o extremos no coinciden con las fechas observadas.")


def cargar_modelo(path):
    """Valida JSON sin ejecutar pickle/joblib ni importar sklearn."""
    def reject(value):
        raise ValueError(f"Número no finito: {value}")
    with Path(path).open(encoding="utf-8") as source:
        model = json.load(source, parse_constant=reject)
    _validar_modelo(model)
    return model


def _consultar_vector(vector, puntos):
    return {p: float(vector[(int(p) - INICIO_DIA) // 15]) if INICIO_DIA <= p < FIN_DIA else 0.0
            for p in puntos if isinstance(p, (int, float)) and not isinstance(p, bool) and math.isfinite(p)}


def perfil_cobertura(modelo, fecha, rol, puntos):
    """Perfil estacional; 00:15 del día siguiente se consulta como minuto 1455."""
    day = _fecha(fecha) if isinstance(fecha, str) else fecha
    if not isinstance(day, date):
        raise ValueError("Fecha de consulta inválida.")
    vector = modelo.get("perfiles", {}).get(_normalizar(rol), {}).get(str(day.month), {}).get(str(day.weekday()))
    return _consultar_vector(vector, puntos) if vector is not None else {}


def perfil_observado(modelo, fecha, rol, puntos):
    """None = sin observación de ese puesto/fecha; cero = franja observada vacía."""
    day = _fecha(fecha) if isinstance(fecha, str) else fecha
    if not isinstance(day, date):
        raise ValueError("Fecha de consulta inválida.")
    vector = modelo.get("cobertura_por_fecha", {}).get(day.isoformat(), {}).get(_normalizar(rol))
    return _consultar_vector(vector, puntos) if vector is not None else None
