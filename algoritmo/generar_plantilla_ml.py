"""Base factible desde contratos y disponibilidad, guiada por cobertura histórica.

Se conserva el nombre del ejecutable por compatibilidad. JSON por stdin/stdout.
"""
import datetime as dt
import json
import math
import sys
import unicodedata
from collections import defaultdict
from pathlib import Path

from ortools.sat.python import cp_model
from modelo_cobertura import cargar_modelo, perfil_cobertura, perfil_observado

DIAS = ["lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo"]
ROLES = {"encargado", "repartidor", "auxiliar", "limpiadora"}
DESCANSO_DIARIO = 600
DESCANSO_PARTIDO = 150
MAX_DIARIO = 600
MAX_DIAS = 5
INICIO_MENORES = 6 * 60
FIN_MENORES = 22 * 60
MAX_DIARIO_MENORES = 8 * 60
DESCANSO_DIARIO_MENORES = 12 * 60
MODELO_PATH = Path(__file__).resolve().with_name("modelo_cobertura.json")


def normalizar(texto):
    return "".join(c for c in unicodedata.normalize("NFD", str(texto).lower().strip())
                   if unicodedata.category(c) != "Mn")


def minutos(hora):
    parsed = dt.datetime.strptime(hora, "%H:%M")
    value = parsed.hour * 60 + parsed.minute
    return value + 1440 if value < 300 else value


def hora(value):
    return f"{value // 60 % 24:02d}:{value % 60:02d}"


def puntos_cobertura(inicio, fin):
    return sorted(set(range(inicio, fin, 15)) | {fin - 1})


def inicio_semana(semana):
    year, week = semana.split("-W")
    monday = dt.date.fromisocalendar(int(year), int(week), 1)
    if monday.strftime("%G-W%V") != semana:
        raise ValueError("Selecciona una semana ISO válida.")
    return monday


def preparar_franjas(horario):
    franjas = {}
    for dia in DIAS:
        rangos = []
        for franja in horario.get(dia, []):
            start, end = minutos(franja["inicio"]), minutos(franja["fin"])
            if end <= start:
                end += 1440
            if end > 29 * 60 or end - start > 18 * 60:
                raise ValueError("Las franjas deben quedar entre las 05:00 y las 05:00 del día siguiente y durar como máximo 18 horas.")
            rangos.append((start, end))
        rangos.sort()
        if any(a[1] > b[0] for a, b in zip(rangos, rangos[1:])):
            raise ValueError("Las franjas de un mismo día no pueden solaparse.")
        franjas[dia] = rangos
    return franjas


def ventanas_empleado(empleado, monday, franjas, festivos=None):
    bloqueados = {normalizar(d) for d in empleado.get("dias_bloqueados_recurrentes", [])}
    fechas = set(empleado.get("fechas_libres_solicitadas", []) + empleado.get("vacaciones", []))
    disponibles = empleado.get("dias_disponibles") or {}
    if isinstance(disponibles, list):
        disponibles = {normalizar(d): ["manana", "tarde"] for d in disponibles}
    disponibles = {normalizar(d): {normalizar(v) for v in values} for d, values in disponibles.items()}
    for index, dia in enumerate(DIAS):
        fecha = monday + dt.timedelta(days=index)
        es_festivo = fecha.isoformat() in (festivos or set())
        modo_festivos = empleado.get('disponibilidad_festivos', 'semanal')
        if es_festivo and modo_festivos == 'no_disponible':
            continue
        disponible_festivo = es_festivo and modo_festivos == 'disponible'
        if (not disponible_festivo and dia in bloqueados) or fecha.isoformat() in fechas:
            continue
        if empleado.get("fecha_inicio") and fecha.isoformat() < empleado["fecha_inicio"]:
            continue
        for block, (start, end) in enumerate(franjas[dia]):
            # MMQP distingue mañana/tarde por el inicio del turno (corte 14:00).
            if not disponible_festivo and empleado.get("tipo_disponibilidad") == "personalizada":
                periodo = "manana" if start < 14 * 60 else "tarde"
                if periodo not in disponibles.get(dia, set()):
                    continue
            if empleado.get("es_menor"):
                start, end = max(start, INICIO_MENORES), min(end, FIN_MENORES)
            if end > 1440 and (fecha + dt.timedelta(days=1)).isoformat() in fechas:
                end = 1440
            if end <= start:
                continue
            yield index, block, start, end


def planificar_empleado(empleado, target, ventanas, cobertura, demanda=None):
    """Encaja un contrato y rellena huecos dejados por empleados anteriores.

    El resultado es un testigo factible; solo se reservan sus turnos si es menor.
    """
    if not target or not ventanas:
        return []
    model = cp_model.CpModel()
    max_diario = MAX_DIARIO_MENORES if empleado.get("es_menor") else MAX_DIARIO
    descanso_diario = DESCANSO_DIARIO_MENORES if empleado.get("es_menor") else DESCANSO_DIARIO
    shifts, preferencias, residuos, cobertura_minima = [], [], [], []
    max_preferencias = 0
    por_dia = defaultdict(list)
    for index, block, opening, closing in ventanas:
        capacity = min(closing - opening, max_diario, target)
        minimum = min(120, target, capacity)
        key = f"{index}_{block}"
        present = model.NewBoolVar("p_" + key)
        start = model.NewIntVar(opening, closing, "s_" + key)
        end = model.NewIntVar(opening, closing, "e_" + key)
        duration = model.NewIntVar(0, capacity, "m_" + key)
        # Cuartos de hora, salvo el resto necesario para conservar el contrato exacto.
        unidades = model.NewIntVar(0, capacity // 15, "u_" + key)
        resto = model.NewBoolVar("r_" + key)
        model.Add(duration == unidades * 15 + (target % 15) * resto)
        residuos.append(resto)
        pasos = model.NewIntVar(0, (closing - opening) // 15, "pasos_" + key)
        model.Add(start == opening + pasos * 15)
        model.Add(end == start + duration)
        model.Add(duration >= minimum).OnlyEnforceIf(present)
        model.Add(duration == 0).OnlyEnforceIf(present.Not())
        if opening < 14 * 60:
            model.Add(start < 14 * 60).OnlyEnforceIf(present)
        por_dia[index].append((present, duration))
        shifts.append((index, block, start, end, duration, present))
        preferencias.append(present * (3 if index >= 4 else 1))
        max_preferencias += 3 if index >= 4 else 1
        for point in puntos_cobertura(opening, closing):
            covered = model.NewBoolVar(f"c_{key}_{point}")
            model.Add(start <= point).OnlyEnforceIf(covered)
            model.Add(end > point).OnlyEnforceIf(covered)
            model.Add(covered <= present)
            previous = cobertura[(index, block, point)]
            expected = (demanda or {}).get((index, block, point))
            if expected is None:
                weight = 300 if previous == 0 else max(1, 4 - previous)
            else:
                # Premio marginal: primero acercarse a la cobertura aprendida;
                # el contrato sigue teniendo prioridad sobre todas las preferencias.
                weight = 1 + round(1000 * max(0, expected - previous)
                                   + 20 * expected / (previous + 1))
            preferencias.append(covered * weight)
            max_preferencias += weight
            if previous == 0:
                cobertura_minima.append(covered)
    dias_trabajados = []
    for index, values in por_dia.items():
        worked = model.NewBoolVar(f"d_{index}")
        model.AddMaxEquality(worked, [p for p, _ in values])
        model.Add(sum(m for _, m in values) <= max_diario)
        dias_trabajados.append(worked)
    model.Add(sum(dias_trabajados) <= MAX_DIAS)
    model.Add(sum(residuos) <= 1)
    for pos, first in enumerate(shifts):
        for second in shifts[pos + 1:]:
            d1, _, _, e1, _, p1 = first
            d2, _, s2, _, _, p2 = second
            rest = DESCANSO_PARTIDO if d1 == d2 else descanso_diario
            model.Add(d2 * 1440 + s2 >= d1 * 1440 + e1 + rest).OnlyEnforceIf([p1, p2])
    total = sum(s[4] for s in shifts)
    model.Add(total <= target)
    # Las franjas elegidas requieren una primera persona antes de reforzar
    # picos históricos. Si faltan horas se conservan los avisos de huecos.
    coverage_priority = 1 + max_preferencias
    preferencias.extend(covered * coverage_priority for covered in cobertura_minima)
    max_preferencias += coverage_priority * len(cobertura_minima)
    # Un minuto contratado pesa más que todas las preferencias juntas.
    priority = 1 + max_preferencias
    model.Maximize(total * priority + sum(preferencias))
    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = 1
    solver.parameters.num_search_workers = 1
    solver.parameters.random_seed = 0
    status = solver.Solve(model)
    if status not in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        raise RuntimeError("No se pudo calcular una base. Revisa las franjas e inténtalo de nuevo.")
    return [(index, block, solver.Value(start), solver.Value(end))
            for index, block, start, end, _, present in shifts if solver.Value(present)]


def fecha_equivalente(fecha, years):
    """Mismo día semanal más cercano al aniversario, también en años bisiestos."""
    try:
        anniversary = fecha.replace(year=fecha.year - years)
    except ValueError:
        anniversary = fecha.replace(year=fecha.year - years, day=28)
    delta = (fecha.weekday() - anniversary.weekday() + 3) % 7 - 3
    return anniversary + dt.timedelta(days=delta)


def perfiles_referencia(modelo, fecha, rol, puntos, recientes, *, antes_de=None):
    """Conserva cada fuente separada para normalizar la semana antes de mezclar.

    None significa desconocido; un diccionario de ceros sigue siendo observado.
    La cobertura estacional solo se permite si terminó antes de la semana pedida.
    """
    values = []
    oldest = min(modelo["metadata"]["anios"]) if modelo else fecha.year
    dates = [(fecha - dt.timedelta(days=7), 64, "semana_anterior")]
    dates += [(fecha_equivalente(fecha, years), 64 / 4 ** years, f"hace_{years}_anios")
              for years in range(1, fecha.year - oldest + 1)]
    for reference, weight, tipo in dates:
        key = reference.isoformat()
        if rol in recientes.get(key, {}):
            profile = {point: 0 for point in puntos}
            for shift in recientes[key][rol]:
                start, end = minutos(shift["hora_inicio"]), minutos(shift["hora_fin"])
                if start == end:
                    continue
                if end <= start:
                    end += 1440
                for point in puntos:
                    if start <= point < end:
                        profile[point] += 1
        else:
            profile = perfil_observado(modelo, reference, rol, puntos) if modelo else None
        if profile:
            values.append({"perfil": profile, "peso": weight, "tipo": tipo,
                           "referencia": {"fecha": key, "tipo": tipo, "peso": weight}})
    # Al pedir semanas pasadas no se usa un modelo entrenado con fechas posteriores.
    if modelo and modelo["metadata"]["fecha_hasta"] < (antes_de or fecha).isoformat():
        profile = perfil_cobertura(modelo, fecha, rol, puntos)
        if profile:
            values.append({"perfil": profile, "peso": 0.25, "tipo": "modelo_estacional", "referencia": None})
    return values


def perfil_priorizado(modelo, fecha, rol, puntos, recientes, *, antes_de=None):
    """Consulta de un día para diagnóstico; la generación normaliza la semana.

    La intensidad absoluta de una plantilla antigua nunca aumenta su prioridad.
    Los puntos omitidos por una fuente siguen siendo desconocidos.
    """
    values = perfiles_referencia(modelo, fecha, rol, puntos, recientes, antes_de=antes_de)
    referencias = [value["referencia"] for value in values if value["referencia"] is not None]
    if not values:
        return None, referencias
    normalized = [(value, sum(value["perfil"].values())) for value in values]
    result = {}
    for point in puntos:
        observed = [(value, total) for value, total in normalized if point in value["perfil"]]
        if observed:
            result[point] = sum(value["peso"] * value["perfil"][point] / total if total > 0 else 0
                                for value, total in observed) / sum(value["peso"] for value, _ in observed)
    return result or None, referencias


def demanda_historica(modelo, monday, franjas, empleados, recientes=None):
    """Escala el patrón aprendido a las horas disponibles del equipo actual.

    Las fechas reales, los roles y las franjas elegidas determinan la forma.
    No se reutilizan identidades ni asignaciones de trabajadores anteriores.
    """
    demandas, fuentes = {}, []
    for rol in sorted({emp["rol"] for emp, _, _ in empleados}):
        puntos, por_fuente = {}, {}
        for index, dia in enumerate(DIAS):
            date = monday + dt.timedelta(days=index)
            for block, (start, end) in enumerate(franjas[dia]):
                values = perfiles_referencia(modelo, date, rol, puntos_cobertura(start, end),
                                             recientes or {}, antes_de=monday)
                for value in values:
                    if value["referencia"] is not None:
                        source = {"dia": dia, "rol": rol, **value["referencia"]}
                        if source not in fuentes:
                            fuentes.append(source)
                    entry = por_fuente.setdefault(value["tipo"], {"peso": value["peso"], "perfil": {}})
                    for point, coverage in value["perfil"].items():
                        key = (index, block, point)
                        entry["perfil"][key] = coverage
                        # El último punto de comprobación puede caer dentro del
                        # mismo cuarto: no debe duplicar su peso al escalar horas.
                        puntos[key] = min(15, end - point) if (point - start) % 15 == 0 else 0
        # Una misma fuente conserva su proporción entre lunes, sábado y todas
        # las franjas. Dividir por su semana elimina cambios de tamaño de equipo.
        for value in por_fuente.values():
            profile = value["perfil"]
            total = sum(coverage * puntos[key] for key, coverage in profile.items())
            value["perfil"] = {key: coverage / total if total > 0 else 0 for key, coverage in profile.items()}
        raw = {}
        for key in puntos:
            observed = [value for value in por_fuente.values() if key in value["perfil"]]
            raw[key] = sum(value["peso"] * value["perfil"][key] for value in observed) / sum(value["peso"] for value in observed)
        minutos_patron = sum(raw[key] * width for key, width in puntos.items())
        if minutos_patron > 0:
            minutos_actuales = sum(target for emp, target, _ in empleados if emp["rol"] == rol)
            factor = minutos_actuales / minutos_patron
            demandas[rol] = {key: value * factor for key, value in raw.items()}
        elif raw:
            # Conservar cero observado permite distinguirlo de falta de datos.
            demandas[rol] = raw
    return demandas, fuentes


def generar_plantilla(payload, *, ruta_modelo=MODELO_PATH):
    monday = inicio_semana(payload["semana"])
    franjas = preparar_franjas(payload["horario"])
    plantilla = {dia: [] for dia in DIAS}
    avisos, resumen, empleados = [], [], []
    for empleado in payload["empleados"]:
        if not empleado.get("activo", True) or empleado.get("en_baja") or empleado.get("rol") not in ROLES:
            continue
        mensual = empleado.get("horas_mensuales")
        if mensual is None or not math.isfinite(float(mensual)) or float(mensual) <= 0:
            avisos.append(f'{empleado["nombre"]}: configura sus horas mensuales para incluirlo en la plantilla.')
            continue
        target = round(float(mensual) * 60 / 4)
        ventanas = list(ventanas_empleado(empleado, monday, franjas, set(payload.get('festivos') or [])))
        empleados.append((empleado, target, ventanas))
    # Reservar primero a los menores deja a los adultos los huecos restantes.
    empleados.sort(key=lambda row: (not bool(row[0].get("es_menor")), len(row[2]), row[0]["id"]))
    modelo = None
    aprendizaje = {"estado": "no_disponible"}
    try:
        if ruta_modelo is not None:
            modelo = cargar_modelo(ruta_modelo)
    except (OSError, ValueError, TypeError, KeyError):
        pass
    if modelo is None:
        avisos.append("No hay un modelo histórico válido disponible. Se usa la semana anterior publicada si existe y, como respaldo, los contratos y la disponibilidad; revisa el entrenamiento.")
    else:
        aprendizaje = {"estado": "entrenado", **modelo["metadata"]}
    demandas, fuentes = demanda_historica(modelo, monday, franjas, empleados, payload.get("referencias_recientes"))
    aprendizaje["referencias"] = fuentes
    if modelo:
        roles_sin_patron = sorted({emp["rol"] for emp, _, _ in empleados} - demandas.keys())
        if roles_sin_patron:
            avisos.append("Sin patrón histórico dentro de estas franjas para: " + ", ".join(roles_sin_patron) + ". Se usan sus contratos y disponibilidad.")
    cobertura_por_rol = defaultdict(lambda: defaultdict(int))
    intervalos = defaultdict(list)
    for empleado, target, ventanas in empleados:
        rol = empleado["rol"]
        cobertura = cobertura_por_rol[rol]
        generated = 0
        for index, block, start, end in planificar_empleado(empleado, target, ventanas, cobertura, demandas.get(rol)):
            generated += end - start
            turno = {"rol": rol, "hora_inicio": hora(start), "hora_fin": hora(end)}
            if empleado.get("es_menor"):
                turno["empleado_id"] = empleado["id"]
                turno["reserva_menor"] = True
            plantilla[DIAS[index]].append(turno)
            intervalos[(rol, index)].append((start, end))
            opening, closing = franjas[DIAS[index]][block]
            for point in puntos_cobertura(opening, closing):
                if start <= point < end:
                    cobertura[(index, block, point)] += 1
        resumen.append({"id": empleado["id"], "nombre": empleado["nombre"], "rol": rol,
                        "minutos_objetivo": target, "minutos_generados": generated})
        if generated < target:
            avisos.append(f'{empleado["nombre"]}: quedan {(target - generated) / 60:g} h sin encajar por su disponibilidad, las franjas o los descansos.')
    huecos = []
    for rol in sorted({emp["rol"] for emp, _, _ in empleados}):
        for index, dia in enumerate(DIAS):
            for opening, closing in franjas[dia]:
                cursor = opening
                for start, end in sorted(intervalos[(rol, index)]):
                    if end <= cursor or start >= closing:
                        continue
                    if start > cursor:
                        huecos.append({"dia": dia, "rol": rol, "inicio": hora(cursor), "fin": hora(start)})
                    cursor = max(cursor, min(end, closing))
                if cursor < closing:
                    huecos.append({"dia": dia, "rol": rol, "inicio": hora(cursor), "fin": hora(closing)})
    if huecos:
        avisos.append("Quedan franjas sin cubrir con las horas y disponibilidad actuales. Revísalas antes de generar el horario.")
    if not any(plantilla.values()):
        avisos.append("No hay horas disponibles para estas franjas. Revisa los contratos, las ausencias y el horario de trabajo.")
    for shifts in plantilla.values():
        shifts.sort(key=lambda s: (s["rol"], minutos(s["hora_inicio"])))
    return {"plantilla": plantilla, "resumen": resumen, "avisos": avisos, "huecos": huecos,
            "modelo_historico": aprendizaje}


if __name__ == "__main__":
    try:
        print(json.dumps(generar_plantilla(json.load(sys.stdin)), ensure_ascii=True))
    except (ValueError, KeyError, TypeError, RuntimeError) as error:
        print(json.dumps({"error": str(error)}, ensure_ascii=True))
        sys.exit(1)
