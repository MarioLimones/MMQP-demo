import json
import re
import unicodedata
import os
from datetime import date, timedelta
from typing import List, Dict, Any, Tuple, Optional
from ortools.sat.python import cp_model
from flask import Flask, request, jsonify

app = Flask(__name__)

# La demo calcula desde sus contratos y turnos ficticios. No carga modelos
# personales históricos ni archivos pickle/joblib de la instalación original.
modelo_ml = None

# --- CONSTANTES ---
DESCANSO_MINIMO_MISMO_DIA_MINUTOS = 150
DESCANSO_MINIMO_DISTINTO_DIA_MINUTOS = 600
MAX_MINUTOS_TURNO_SIN_PENALIZACION = 600
MAX_DIAS_TRABAJADOS_SIN_PENALIZACION = 5
CORTE_MANANA_TARDE_MINUTOS = 16 * 60
DIAS_ORDENADOS = ["lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo"]
DIAS_ORDENADOS_IDX = {dia: idx for idx, dia in enumerate(DIAS_ORDENADOS)}
DIAS_FINDE = {"viernes", "sabado", "domingo"}
DIAS_SEMANA = {"lunes", "martes", "miercoles", "jueves"}


def normalizar_texto(texto: str) -> str:
    """Convierte texto a minúsculas y elimina tildes/acentos."""
    if not texto:
        return ""
    texto = texto.lower().strip()
    return ''.join(c for c in unicodedata.normalize('NFD', texto) if unicodedata.category(c) != 'Mn')


def time_to_minutes(t_str: str, is_end: bool = False) -> int:
    """Convierte formato HH:MM a minutos desde medianoche."""
    h, m = map(int, t_str.split(':'))
    if h < 5:
        h += 24
    if is_end and t_str == "00:00":
        h = 24
    return h * 60 + m


def _fechas_iso_semana(metadatos: Optional[Dict[str, Any]]) -> Dict[int, str]:
    """Devuelve un mapa {dia_idx (0..6): 'YYYY-MM-DD'} a partir de metadatos.semana_id (ej. '2026-W43')."""
    if not isinstance(metadatos, dict):
        return {}
    semana_id = str(metadatos.get('semana_id') or '').strip()
    match = re.match(r'^(\d{4})-W(\d{1,2})$', semana_id)
    if not match:
        return {}
    year, week = int(match.group(1)), int(match.group(2))
    try:
        return {d_idx: date.fromisocalendar(year, week, d_idx + 1).isoformat() for d_idx in range(7)}
    except ValueError:
        return {}


def auditar_y_ampliar_horas(empleados: List[Dict[str, Any]], turnos: List[Dict[str, Any]], ampliar_limites: bool = False) -> Any:
    """
    Calcula la demanda de horas por rol y la oferta (contratos).
    Devuelve (horas_maximas_emp, horas_minimas_emp) o un dict si requiere confirmación.
    """
    horas_maximas_emp = {}
    horas_minimas_emp = {}

    for e in empleados:
        hm = e.get('horas_mensuales')
        ho = e.get('horas_semanales_override')
        if ho is not None:
            horas_maximas_emp[e['id']] = float(ho)
            horas_minimas_emp[e['id']] = float(ho)
        elif hm is not None:
            h_semanal = hm / 4.0
            horas_maximas_emp[e['id']] = h_semanal
            horas_minimas_emp[e['id']] = h_semanal
        else:
            horas_maximas_emp[e['id']] = float('inf')
            horas_minimas_emp[e['id']] = 0.0

    horas_req_rol = {}
    for t in turnos:
        rol = t['rol_necesario']
        horas_req_rol[rol] = horas_req_rol.get(rol, 0.0) + t['duracion_horas']

    horas_disp_rol = {}
    for e in empleados:
        rol = e['rol']
        h = horas_maximas_emp[e['id']]
        if h != float('inf'):
            horas_disp_rol[rol] = horas_disp_rol.get(rol, 0.0) + h

    roles_con_deficit = {}
    roles_con_exceso = {}

    roles_evaluar = set(horas_req_rol.keys()).union(set(horas_disp_rol.keys()))

    for rol in roles_evaluar:
        req = horas_req_rol.get(rol, 0.0)
        disp = horas_disp_rol.get(rol, 0.0)
        has_inf = any(horas_maximas_emp[e['id']] == float('inf') for e in empleados if e['rol'] == rol)

        if not has_inf and req > disp:
            roles_con_deficit[rol] = req - disp
        elif not has_inf and disp > req:
            roles_con_exceso[rol] = disp - req

    if (roles_con_deficit or roles_con_exceso) and not ampliar_limites:
        return {
            "requires_confirmation": True,
            "deficit": roles_con_deficit,
            "exceso": roles_con_exceso,
            "requeridas": horas_req_rol,
            "disponibles": horas_disp_rol
        }

    if roles_con_exceso and ampliar_limites:
        for e in empleados:
            if e['rol'] in roles_con_exceso:
                horas_minimas_emp[e['id']] = 0.0

    if roles_con_deficit and ampliar_limites:
        # En modo API, ampliamos automáticamente las horas
        for r, deficit in roles_con_deficit.items():
            emps_role = [e['id'] for e in empleados if e['rol'] == r and horas_maximas_emp[e['id']] != float('inf')]

            while deficit > 0.01:
                if not emps_role:
                    break
                min_h = min(horas_maximas_emp[eid] for eid in emps_role)
                mins_eids = [eid for eid in emps_role if abs(horas_maximas_emp[eid] - min_h) < 0.001]

                other_hs = [horas_maximas_emp[eid] for eid in emps_role if horas_maximas_emp[eid] > min_h + 0.001]
                next_h = min(other_hs) if other_hs else float('inf')

                gap = next_h - min_h
                total_gap = gap * len(mins_eids)

                if total_gap <= deficit:
                    for eid in mins_eids:
                        horas_maximas_emp[eid] += gap
                    deficit -= total_gap
                else:
                    inc = deficit / len(mins_eids)
                    for eid in mins_eids:
                        horas_maximas_emp[eid] += inc
                    deficit = 0

    return (horas_maximas_emp, horas_minimas_emp)


def _preparar_turnos(plantilla: List[Dict[str, Any]], metadatos: Optional[Dict[str, Any]] = None) -> List[Dict[str, Any]]:
    """Extrae y normaliza los turnos de la plantilla agregando minutos absolutos y fecha ISO."""
    turnos = []
    fechas_iso = _fechas_iso_semana(metadatos)
    festivos = set((metadatos or {}).get('festivos') or [])
    for dia_info in plantilla:
        dia = dia_info.get('dia')
        dia_norm = normalizar_texto(dia)
        fecha = dia_info.get('fecha')
        d_idx = DIAS_ORDENADOS_IDX.get(dia_norm, 0)
        fecha_iso = fechas_iso.get(d_idx)

        for t in dia_info.get('turnos_requeridos', []):
            turno_ext = t.copy()
            turno_ext['dia'] = dia
            turno_ext['fecha'] = fecha
            turno_ext['es_festivo'] = (fecha_iso or fecha) in festivos
            if fecha_iso:
                turno_ext['fecha_iso'] = fecha_iso
            turno_ext['min_inicio'] = time_to_minutes(t['hora_inicio'])
            turno_ext['min_fin'] = time_to_minutes(t['hora_fin'], is_end=True)
            # El turno puede terminar al día siguiente después del corte de las 05:00.
            # Su fin debe permanecer después del inicio para comprobar descansos.
            if turno_ext['min_fin'] < turno_ext['min_inicio']:
                turno_ext['min_fin'] += 24 * 60

            # Tiempo absoluto desde el inicio de la semana
            turno_ext['abs_min_inicio'] = d_idx * 24 * 60 + turno_ext['min_inicio']
            turno_ext['abs_min_fin'] = d_idx * 24 * 60 + turno_ext['min_fin']

            turnos.append(turno_ext)
    return turnos


def _validar_preasignaciones_menores(empleados: List[Dict[str, Any]], turnos: List[Dict[str, Any]]):
    """Valida menores y rechaza reservas de autoplantilla cuyo empleado ya no está."""
    por_id = {e['id']: e for e in empleados}
    reservados = {}
    for turno in turnos:
        emp_id = turno.get('empleado_id')
        if not emp_id:
            continue
        empleado = por_id.get(emp_id)
        if empleado is None and not turno.get('reserva_menor'):
            # Las preasignaciones manuales antiguas se liberan si el empleado ya no está activo.
            continue
        if empleado is None:
            raise ValueError('Hay un turno reservado para un empleado que ya no está disponible. '
                             'Regenera la autoplantilla o revisa su asignación.')
        if not empleado.get('es_menor'):
            continue
        if not 6 * 60 <= turno['min_inicio'] < turno['min_fin'] <= 22 * 60:
            raise ValueError(f'{empleado["nombre"]}: los turnos de menores deben quedar entre las 06:00 y las 22:00. '
                             'Revisa el turno reservado antes de generar.')
        reservados.setdefault(emp_id, []).append(turno)

    for emp_id, shifts in reservados.items():
        empleado = por_id[emp_id]
        minutos_por_dia = {}
        for turno in shifts:
            dia = normalizar_texto(turno['dia'])
            minutos_por_dia[dia] = minutos_por_dia.get(dia, 0) + turno['min_fin'] - turno['min_inicio']
        if any(minutos > 8 * 60 for minutos in minutos_por_dia.values()):
            raise ValueError(f'{empleado["nombre"]}: los turnos reservados superan las 8 horas diarias permitidas para menores.')
        ordered = sorted(shifts, key=lambda turno: turno['abs_min_inicio'])
        for first, second in zip(ordered, ordered[1:]):
            if (normalizar_texto(first['dia']) != normalizar_texto(second['dia'])
                    and second['abs_min_inicio'] - first['abs_min_fin'] < 12 * 60):
                raise ValueError(f'{empleado["nombre"]}: los turnos reservados deben dejar 12 horas entre jornadas para menores.')

def _detectar_conflicto_preasignados(empleados: List[Dict[str, Any]], turnos: List[Dict[str, Any]]) -> Optional[str]:
    """Verifica si los turnos preasignados manualmente a un empleado activo violan descansos o solapan."""
    nombres_emp = {e['id']: e.get('nombre', f"ID {e['id']}") for e in empleados}
    por_empleado: Dict[Any, List[Dict[str, Any]]] = {}
    for t in turnos:
        emp_id = t.get('empleado_id')
        if emp_id in nombres_emp:
            por_empleado.setdefault(emp_id, []).append(t)

    for emp_id, lista in por_empleado.items():
        for i in range(len(lista)):
            for j in range(i + 1, len(lista)):
                t1, t2 = lista[i], lista[j]
                first, second = (t1, t2) if t1['abs_min_inicio'] <= t2['abs_min_inicio'] else (t2, t1)
                descanso = second['abs_min_inicio'] - first['abs_min_fin']
                mismo_dia = normalizar_texto(first['dia']) == normalizar_texto(second['dia'])
                minimo = DESCANSO_MINIMO_MISMO_DIA_MINUTOS if mismo_dia else DESCANSO_MINIMO_DISTINTO_DIA_MINUTOS
                if descanso < minimo:
                    nombre = nombres_emp[emp_id]
                    return (
                        f"Conflicto en turnos preasignados a {nombre}: "
                        f"{first['dia']} ({first['hora_inicio']}-{first['hora_fin']}) y "
                        f"{second['dia']} ({second['hora_inicio']}-{second['hora_fin']}) "
                        f"no cumplen el descanso mínimo requerido."
                    )
    return None


def _extraer_fechas_bloqueadas(empleado: Dict[str, Any]) -> set:
    """Extrae un conjunto de fechas YYYY-MM-DD bloqueadas (soporta strings o dicts {'inicio', 'fin'})."""
    fechas = set()
    for campo in ('fechas_libres_solicitadas', 'vacaciones'):
        for item in (empleado.get(campo) or []):
            if isinstance(item, str):
                fechas.add(item)
            elif isinstance(item, dict):
                if 'fecha' in item and isinstance(item['fecha'], str):
                    fechas.add(item['fecha'])
                elif 'inicio' in item and 'fin' in item:
                    try:
                        cur = date.fromisoformat(str(item['inicio']))
                        end = date.fromisoformat(str(item['fin']))
                        while cur <= end:
                            fechas.add(cur.isoformat())
                            cur += timedelta(days=1)
                    except ValueError:
                        pass
    return fechas


def _parsear_dias_disponibles(raw_dias_disp: Any) -> Dict[str, set]:
    """Normaliza dias_disponibles tanto si viene como dict {'martes': ['tarde']} o lista ['lunes', 'martes-tarde']."""
    dias_disp_esp: Dict[str, set] = {}
    if isinstance(raw_dias_disp, dict):
        for k, val in raw_dias_disp.items():
            dia_k = normalizar_texto(k)
            if isinstance(val, (list, tuple, set)):
                dias_disp_esp[dia_k] = {normalizar_texto(v) for v in val}
            elif isinstance(val, str):
                dias_disp_esp[dia_k] = {normalizar_texto(val)}
    elif isinstance(raw_dias_disp, list):
        for item in raw_dias_disp:
            norm = normalizar_texto(str(item))
            if '-' in norm:
                partes = norm.split('-', 1)
                dia_k, franja = partes[0].strip(), partes[1].strip()
                dias_disp_esp.setdefault(dia_k, set()).add(franja)
            else:
                dias_disp_esp.setdefault(norm, set()).update({"manana", "tarde"})
    return dias_disp_esp


def _crear_variables_asignacion(model: cp_model.CpModel, empleados: List[Dict[str, Any]], turnos: List[Dict[str, Any]]) -> Tuple[Dict[Tuple[Any, Any], Any], Dict[Any, List[Dict[str, Any]]]]:
    """Crea las variables x[emp_id, t_id] y devuelve diccionarios útiles."""
    x = {}
    turnos_validos_por_empleado = {e['id']: [] for e in empleados}
    empleados_con_reservas = {t['empleado_id'] for t in turnos
                             if t.get('empleado_id') and t.get('reserva_menor')}

    ids_empleados_activos = {e['id'] for e in empleados}

    for e in empleados:
        emp_id = e['id']
        rol_emp = e['rol']
        tipo_disp = normalizar_texto(e.get('tipo_disponibilidad', 'completa'))
        dias_bloq = {normalizar_texto(d) for d in e.get('dias_bloqueados_recurrentes', [])}
        fechas_bloq = _extraer_fechas_bloqueadas(e)
        dias_disp_esp = _parsear_dias_disponibles(e.get('dias_disponibles', {}))

        for t in turnos:
            t_id = t['turno_id']
            dia_norm = normalizar_texto(t['dia'])

            raw_preassigned = t.get('empleado_id')
            # Solo bloquear el turno si el empleado preasignado está realmente activo y disponible en esta generación
            preassigned_emp_id = raw_preassigned if raw_preassigned in ids_empleados_activos else None
            is_preassigned_to_me = (preassigned_emp_id == emp_id)

            # Holiday preferences are independent of the weekly pattern, also for manual reservations.
            holiday_mode = e.get('disponibilidad_festivos', 'semanal')
            holiday_available = t.get('es_festivo') and holiday_mode == 'disponible'
            if t.get('es_festivo') and holiday_mode == 'no_disponible':
                if is_preassigned_to_me:
                    raise ValueError(f'{e.get("nombre", emp_id)}: no está disponible para trabajar en festivos.')
                continue
            # An explicit holiday opt-in never cancels a dated absence or medical leave.
            if t.get('es_festivo') and (e.get('en_baja') or t.get('fecha') in fechas_bloq or t.get('fecha_iso') in fechas_bloq):
                if is_preassigned_to_me:
                    raise ValueError(f'{e.get("nombre", emp_id)}: el festivo coincide con una ausencia o baja.')
                continue

            # Si el turno ya tiene un empleado activo asignado y no soy yo, ni siquiera me lo planteo
            if preassigned_emp_id and not is_preassigned_to_me:
                continue

            # La autoplantilla ya ha reservado los turnos de estos menores;
            # los huecos restantes corresponden al resto del equipo.
            if e.get('es_menor') and emp_id in empleados_con_reservas and not is_preassigned_to_me:
                continue

            # El límite nocturno de menores también se aplica a las reservas manuales.
            if e.get('es_menor') and not 6 * 60 <= t['min_inicio'] < t['min_fin'] <= 22 * 60:
                if is_preassigned_to_me:
                    raise ValueError(f'{e["nombre"]}: los turnos de menores deben quedar entre las 06:00 y las 22:00.')
                continue
            
            # Solo aplicamos los filtros de disponibilidad si NO estoy preasignado a mano
            if not is_preassigned_to_me:
                if rol_emp != t['rol_necesario']:
                    continue
                if not holiday_available and dia_norm in dias_bloq:
                    continue
                if (t.get('fecha') and t['fecha'] in fechas_bloq) or (t.get('fecha_iso') and t['fecha_iso'] in fechas_bloq):
                    continue
                if not holiday_available and tipo_disp in ("personalizada", "dias_especificos"):
                    if dia_norm not in dias_disp_esp:
                        continue
                    # Verificamos si tiene la disponibilidad de mañana o tarde (corte unificado: 16:00)
                    is_manana = t['min_inicio'] < CORTE_MANANA_TARDE_MINUTOS
                    is_tarde = t['min_inicio'] >= CORTE_MANANA_TARDE_MINUTOS
                    disp_horarios = dias_disp_esp[dia_norm]

                    if is_manana and "manana" not in disp_horarios:
                        continue
                    if is_tarde and "tarde" not in disp_horarios:
                        continue

            var_name = f"x_emp{emp_id}_t{t_id}"
            x[(emp_id, t_id)] = model.NewBoolVar(var_name)
            turnos_validos_por_empleado[emp_id].append(t)

            # Si estoy preasignado a mano en la plantilla, fuerzo a que la IA me asigne el turno SÍ o SÍ
            if is_preassigned_to_me:
                model.Add(x[(emp_id, t_id)] == 1)

    # Restricción: un turno solo puede ser asignado a un empleado como máximo
    for t in turnos:
        t_id = t['turno_id']
        variables_turno = [x[(e['id'], t_id)] for e in empleados if (e['id'], t_id) in x]
        model.AddAtMostOne(variables_turno)

    return x, turnos_validos_por_empleado


def _agregar_restricciones_horas(
    model: cp_model.CpModel,
    x: Dict,
    empleados: List[Dict[str, Any]],
    turnos_validos_por_empleado: Dict,
    horas_maximas_emp: Dict,
    horas_minimas_emp: Dict,
    ampliar_limites: bool = False
):
    """Añade los límites duros de horas semanales por empleado (Hard Upper Bound)."""
    for e in empleados:
        emp_id = e['id']
        h_max = horas_maximas_emp.get(emp_id)

        assigned_mins = []
        for t in turnos_validos_por_empleado[emp_id]:
            duracion_mins = int(round(float(t.get('duracion_horas', 0)) * 60))
            assigned_mins.append(x[(emp_id, t['turno_id'])] * duracion_mins)

        if assigned_mins and h_max is not None and h_max != float('inf'):
            model.Add(sum(assigned_mins) <= round(h_max * 60))


def _agregar_restricciones_descansos(model: cp_model.CpModel, x: Dict, empleados: List[Dict[str, Any]], turnos_validos_por_empleado: Dict):
    """Evita solapamientos e impone descansos mínimos entre turnos del mismo empleado."""
    for e in empleados:
        emp_id = e['id']
        turnos_emp = turnos_validos_por_empleado[emp_id]

        for i in range(len(turnos_emp)):
            for j in range(i + 1, len(turnos_emp)):
                t1 = turnos_emp[i]
                t2 = turnos_emp[j]

                # Ordenar cronológicamente
                first, second = (t1, t2) if t1['abs_min_inicio'] <= t2['abs_min_inicio'] else (t2, t1)

                descanso = second['abs_min_inicio'] - first['abs_min_fin']
                mismo_dia = normalizar_texto(first['dia']) == normalizar_texto(second['dia'])
                
                descanso_entre_jornadas = 12 * 60 if e.get('es_menor') else DESCANSO_MINIMO_DISTINTO_DIA_MINUTOS
                descanso_minimo_requerido = DESCANSO_MINIMO_MISMO_DIA_MINUTOS if mismo_dia else descanso_entre_jornadas
                
                # Si solapan (descanso < 0) o no cumplen el descanso mínimo:
                if descanso < descanso_minimo_requerido:
                    model.AddImplication(x[(emp_id, t1['turno_id'])], x[(emp_id, t2['turno_id'])].Not())


def _agregar_restricciones_puestos(model: cp_model.CpModel, x: Dict, turnos: List[Dict[str, Any]], empleados: List[Dict[str, Any]]):
    """Garantiza que turnos distintos del mismo puesto en el mismo día sean cubiertos por la misma persona (si no solapan)."""
    grupos_puesto = {}
    for t in turnos:
        p = t.get('puesto_num')
        if p is None:
            continue
        clave = (t['dia'], t['rol_necesario'], p)
        grupos_puesto.setdefault(clave, []).append(t)

    for clave, grupo in grupos_puesto.items():
        if len(grupo) < 2:
            continue

        candidatos_por_turno = {t['turno_id']: set() for t in grupo}
        for t in grupo:
            t_id = t['turno_id']
            for e in empleados:
                if (e['id'], t_id) in x:
                    candidatos_por_turno[t_id].add(e['id'])

        for i in range(len(grupo)):
            for j in range(i + 1, len(grupo)):
                t1 = grupo[i]
                t2 = grupo[j]
                t1_id = t1['turno_id']
                t2_id = t2['turno_id']

                solapan = t1['min_inicio'] < t2['min_fin'] and t2['min_inicio'] < t1['min_fin']
                emps_comunes = candidatos_por_turno[t1_id] & candidatos_por_turno[t2_id]

                if not solapan:
                    # Mismo empleado debe cubrir ambos turnos del mismo puesto
                    for emp_id in emps_comunes:
                        model.Add(x[(emp_id, t1_id)] == x[(emp_id, t2_id)])

                    # Si hay candidatos comunes, los empleados que solo pueden uno de los dos no pueden asignarse
                    if emps_comunes:
                        solo_t1 = candidatos_por_turno[t1_id] - candidatos_por_turno[t2_id]
                        solo_t2 = candidatos_por_turno[t2_id] - candidatos_por_turno[t1_id]
                        for eid in solo_t1:
                            model.Add(x[(eid, t1_id)] == 0)
                        for eid in solo_t2:
                            model.Add(x[(eid, t2_id)] == 0)


def _configurar_dias_libres_y_penalizaciones(
    model: cp_model.CpModel,
    x: Dict,
    empleados: List[Dict[str, Any]],
    turnos_validos_por_empleado: Dict,
    ampliar_limites: bool
) -> Tuple[List[Any], List[Any], List[Any], List[Any]]:
    """
    Configura las variables de penalización y recompensas suaves:
    - Penalización proporcional por minutos trabajados por encima de 10h al día
    - Restricción o penalización por >5 días trabajados a la semana (según ampliar_limites)
    - Recompensa por días libres consecutivos entre semana y penalización real por Sábado+Domingo libre
    """
    dias_libres_seguidos_vars = []
    penalizacion_10h_vars = []
    penalizacion_5dias_vars = []
    penalizacion_finde_libre_vars = []
    trabaja_dia = {}

    for e in empleados:
        emp_id = e['id']
        for d_idx, dia_str in enumerate(DIAS_ORDENADOS):
            trabaja_dia[(emp_id, d_idx)] = model.NewBoolVar(f"trabaja_emp{emp_id}_dia{d_idx}")

            shifts_on_day = []
            minutos_on_day = []
            minutos_reales_on_day = []

            max_mins_dia = 0
            for t in turnos_validos_por_empleado[emp_id]:
                if normalizar_texto(t['dia']) == dia_str:
                    dur_mins = int(round(float(t.get('duracion_horas', 0)) * 60))
                    shifts_on_day.append(x[(emp_id, t['turno_id'])])
                    minutos_on_day.append(x[(emp_id, t['turno_id'])] * dur_mins)
                    max_mins_dia += dur_mins
                    if e.get('es_menor'):
                        minutos_reales_on_day.append(x[(emp_id, t['turno_id'])] * (t['min_fin'] - t['min_inicio']))

            if shifts_on_day:
                model.AddMaxEquality(trabaja_dia[(emp_id, d_idx)], shifts_on_day)

                if e.get('es_menor'):
                    model.Add(sum(minutos_reales_on_day) <= 8 * 60)

                # Penalización proporcional por cada minuto que supere el límite diario (10h = 600 min)
                cota_exceso = max(0, max_mins_dia - MAX_MINUTOS_TURNO_SIN_PENALIZACION)
                if cota_exceso > 0:
                    exceso_10h = model.NewIntVar(0, cota_exceso, f"mas_10h_{emp_id}_{d_idx}")
                    model.Add(exceso_10h >= sum(minutos_on_day) - MAX_MINUTOS_TURNO_SIN_PENALIZACION)
                    penalizacion_10h_vars.append(exceso_10h)
            else:
                model.Add(trabaja_dia[(emp_id, d_idx)] == 0)

        # Objetivo: Máximo 5 días trabajados
        dias_trabajados = sum(trabaja_dia[(emp_id, d_idx)] for d_idx in range(7))

        if not ampliar_limites:
            # Restricción DURA (Hard limit), salvo que tenga más de 5 días preasignados a mano
            dias_preasignados = len({
                normalizar_texto(t['dia'])
                for t in turnos_validos_por_empleado[emp_id]
                if t.get('empleado_id') == emp_id
            })
            limite_duro_dias = max(MAX_DIAS_TRABAJADOS_SIN_PENALIZACION, dias_preasignados)
            model.Add(dias_trabajados <= limite_duro_dias)
            exceso_dias = model.NewIntVar(0, 0, f"exceso_dias_{emp_id}")
            model.Add(exceso_dias == 0)
            penalizacion_5dias_vars.append(exceso_dias)
        else:
            # Restricción BLANDA (Soft limit)
            exceso_dias = model.NewIntVar(0, 7, f"exceso_dias_{emp_id}")
            model.Add(exceso_dias >= dias_trabajados - MAX_DIAS_TRABAJADOS_SIN_PENALIZACION)
            penalizacion_5dias_vars.append(exceso_dias)

        puede_trabajar_finde = any(
            normalizar_texto(t['dia']) in ('sabado', 'domingo')
            for t in turnos_validos_por_empleado[emp_id]
        )

        # Objetivo suave: Días libres consecutivos (pero penalizar si es Sábado+Domingo)
        for d_idx in range(6):
            consecutivo = model.NewBoolVar(f"libres_consec_{emp_id}_{d_idx}")
            # Equivalencia exacta: consecutivo == 1 <-> (not trabaja_dia[d_idx] and not trabaja_dia[d_idx+1])
            model.AddImplication(consecutivo, trabaja_dia[(emp_id, d_idx)].Not())
            model.AddImplication(consecutivo, trabaja_dia[(emp_id, d_idx + 1)].Not())
            model.AddBoolOr([trabaja_dia[(emp_id, d_idx)], trabaja_dia[(emp_id, d_idx + 1)], consecutivo])

            if d_idx == 5:
                # Sábado (5) y Domingo (6) libres consecutivos = penalizar si el empleado es elegible en finde
                if puede_trabajar_finde:
                    penalizacion_finde_libre_vars.append(consecutivo)
            else:
                # Otros días consecutivos = premio
                dias_libres_seguidos_vars.append(consecutivo)

    return dias_libres_seguidos_vars, penalizacion_10h_vars, penalizacion_5dias_vars, penalizacion_finde_libre_vars


def _configurar_funcion_objetivo(
    model: cp_model.CpModel,
    x: Dict,
    empleados: List[Dict[str, Any]],
    turnos_validos_por_empleado: Dict,
    horas_maximas_emp: Dict,
    dias_libres_seguidos_vars: List,
    penalizacion_10h_vars: List,
    penalizacion_5dias_vars: List,
    penalizacion_finde_libre_vars: List
):
    """Establece la función objetivo (maximizar) teniendo en cuenta coberturas y penalizaciones."""
    total_turnos_cubiertos = sum(x.values())
    terminos_shortfall_critico = []
    terminos_shortfall_deseado = []
    terminos_overwork = []

    for e in empleados:
        emp_id = e['id']
        h_max = horas_maximas_emp.get(emp_id)

        # Obtener contrato base para el shortfall crítico
        hm = e.get('horas_mensuales')
        h_base = (hm / 4.0) if hm is not None else 0.0

        mins_expr = []
        max_posible_mins = 0
        for t in turnos_validos_por_empleado[emp_id]:
            duracion_mins = int(round(float(t.get('duracion_horas', 0)) * 60))
            mins_expr.append(x[(emp_id, t['turno_id'])] * duracion_mins)
            max_posible_mins += duracion_mins

        suma_mins = sum(mins_expr) if mins_expr else 0

        if h_max is not None and h_max != float('inf') and mins_expr:
            objetivo_max_mins = int(round(h_max * 60))
            # Si hay un ajuste manual inferior al contrato mensual, el objetivo crítico respeta ese tope
            objetivo_base_mins = min(int(round(h_base * 60)), objetivo_max_mins) if h_base > 0 else objetivo_max_mins

            # Usar peso según horas objetivo para priorizar contratos mayores
            h_ref_peso = h_base if h_base > 0 else h_max
            peso = max(1, int(h_ref_peso // 10)) if h_ref_peso > 0 else 1

            # 1. Penalizar Shortfall Crítico (faltan horas para el contrato / objetivo base)
            if objetivo_base_mins > 0:
                sf_critico = model.NewIntVar(0, objetivo_base_mins + 60, f"sfc_{emp_id}")
                model.Add(sf_critico >= objetivo_base_mins - suma_mins)
                terminos_shortfall_critico.append(peso * sf_critico)

            # 2. Penalizar Shortfall Deseado (faltan horas para llegar al ajuste manual superior al contrato)
            if objetivo_max_mins > objetivo_base_mins:
                sf_deseado = model.NewIntVar(0, objetivo_max_mins, f"sfd_{emp_id}")
                model.Add(sf_deseado >= objetivo_max_mins - suma_mins)
                terminos_shortfall_deseado.append(peso * sf_deseado)

            # 3. Penalizar Overwork (exceder horas máximas ajustadas)
            cota_overwork = max(1440, max_posible_mins)
            overwork = model.NewIntVar(0, cota_overwork, f"ow_{emp_id}")
            model.Add(overwork >= suma_mins - objetivo_max_mins)
            terminos_overwork.append(peso * overwork)

    # PESOS BALANCEADOS
    W_TURNO_CUBIERTO = 100000
    W_SHORTFALL_CRITICO_MINUTO = 200    # Penalización fuerte si no llega a sus horas base
    W_SHORTFALL_DESEADO_MINUTO = 2      # Penalización suave si no llega a las horas extra ajustadas
    W_OVERWORK_MINUTO = 100             # Penalización alta por pasarse del ajuste manual
    W_PREFERENCIA_CONTRATO = 20         # Bono estático por minuto asignado (multiplicado por peso)
    W_EXCESO_DIAS = 30000               # Penalización por superar los 5 días trabajados
    W_EXCESO_10H_MINUTO = 300           # Penalización por cada minuto por encima de 10h en un mismo día (18.000/hora)
    W_LIBRES_CONSECUTIVOS = 2000
    W_FINDE_LIBRE = 50000               # Penalización por dar Sábado y Domingo libre a un empleado elegible en finde

    # Añadir Decision Strategy para priorizar a los empleados con mayor peso
    variables_por_peso = {}
    for e in empleados:
        hm = e.get('horas_mensuales')
        h_base = (hm / 4.0) if hm is not None else 0.0
        peso = max(1, int(h_base // 10)) if h_base > 0 else 1

        emp_id = e['id']
        if emp_id in turnos_validos_por_empleado:
            for t in turnos_validos_por_empleado[emp_id]:
                if (emp_id, t['turno_id']) in x:
                    variables_por_peso.setdefault(peso, []).append(x[(emp_id, t['turno_id'])])

    for peso in sorted(variables_por_peso.keys(), reverse=True):
        model.AddDecisionStrategy(
            variables_por_peso[peso],
            cp_model.CHOOSE_FIRST,
            cp_model.SELECT_MAX_VALUE
        )

    # --- PREDICCIÓN ML BATCH ---
    probabilidades_ml = {}
    if modelo_ml:
        X_batch = []
        indices_batch = []
        dias_map = {'lunes': 0, 'martes': 1, 'miercoles': 2, 'jueves': 3, 'viernes': 4, 'sabado': 5, 'domingo': 6}

        for e in empleados:
            emp_id = e['id']
            emp_norm = normalizar_texto(e.get('nombre', ''))
            if emp_norm not in modelo_ml['clases_emp']:
                continue
            emp_enc = modelo_ml['clases_emp'].index(emp_norm)

            if emp_id in turnos_validos_por_empleado:
                for t in turnos_validos_por_empleado[emp_id]:
                    if (emp_id, t['turno_id']) not in x:
                        continue
                    rol_norm = normalizar_texto(t['rol_necesario'])
                    if rol_norm not in modelo_ml['clases_rol']:
                        continue
                    rol_enc = modelo_ml['clases_rol'].index(rol_norm)

                    dia_norm = normalizar_texto(t['dia'])
                    dia_idx = dias_map.get(dia_norm, 0)
                    min_inicio = t['min_inicio']
                    duracion = t['min_fin'] - t['min_inicio']
                    if duracion <= 0:
                        duracion += 24 * 60

                    X_batch.append([dia_idx, min_inicio, duracion, rol_enc, emp_enc])
                    indices_batch.append((emp_id, t['turno_id']))

        if X_batch:
            try:
                probs = modelo_ml['modelo'].predict_proba(X_batch)[:, 1]
                for i, idx in enumerate(indices_batch):
                    probabilidades_ml[idx] = float(probs[i])
            except Exception as e:
                print(f"Error prediciendo probabilidades batch: {e}")

    # Crear bonos estáticos para guiar a la LNS directamente hacia los empleados de más peso Y preferencias ML
    bonos_estaticos = []
    for emp_id in turnos_validos_por_empleado:
        hm = next((e.get('horas_mensuales') for e in empleados if e['id'] == emp_id), 0)
        h_base = (hm / 4.0) if hm is not None else 0.0
        peso = max(1, int(h_base // 10)) if h_base > 0 else 1
        for t in turnos_validos_por_empleado[emp_id]:
            if (emp_id, t['turno_id']) in x:
                var = x[(emp_id, t['turno_id'])]
                duracion = int(round(float(t.get('duracion_horas', 0)) * 60))

                # Multiplicador ML: Prob 0.5 -> 1.0x (Normal), Prob 1.0 -> 2.0x (Doble bono), Prob 0.0 -> 0x (Sin bono extra)
                ml_prob = probabilidades_ml.get((emp_id, t['turno_id']), 0.5)
                bono_ml = int(duracion * peso * W_PREFERENCIA_CONTRATO * (ml_prob * 2))

                bonos_estaticos.append(var * bono_ml)

    model.Maximize(
        W_TURNO_CUBIERTO * total_turnos_cubiertos
        + sum(bonos_estaticos)
        - W_SHORTFALL_CRITICO_MINUTO * sum(terminos_shortfall_critico)
        - W_SHORTFALL_DESEADO_MINUTO * sum(terminos_shortfall_deseado)
        - W_OVERWORK_MINUTO * sum(terminos_overwork)
        - W_EXCESO_DIAS * sum(penalizacion_5dias_vars)
        - W_EXCESO_10H_MINUTO * sum(penalizacion_10h_vars)
        + W_LIBRES_CONSECUTIVOS * sum(dias_libres_seguidos_vars)
        - W_FINDE_LIBRE * sum(penalizacion_finde_libre_vars)
    )


def generar_horarios(data: Dict[str, Any]) -> Any:
    """Función principal para generar los horarios dados los datos de la API."""
    ampliar_limites = data.get('ampliar_limites', False)
    empleados = data.get('empleados', [])
    plantilla = data.get('plantilla_diseno', [])
    metadatos = data.get('metadatos', {})

    turnos = _preparar_turnos(plantilla, metadatos)
    _validar_preasignaciones_menores(empleados, turnos)

    conflicto_preasignados = _detectar_conflicto_preasignados(empleados, turnos)
    if conflicto_preasignados:
        return {"error_message": conflicto_preasignados}

    auditoria_res = auditar_y_ampliar_horas(empleados, turnos, ampliar_limites)
    if isinstance(auditoria_res, dict) and auditoria_res.get("requires_confirmation"):
        return auditoria_res

    horas_maximas_emp, horas_minimas_emp = auditoria_res

    model = cp_model.CpModel()
    x, turnos_validos_por_empleado = _crear_variables_asignacion(model, empleados, turnos)

    _agregar_restricciones_horas(model, x, empleados, turnos_validos_por_empleado, horas_maximas_emp, horas_minimas_emp, ampliar_limites)
    _agregar_restricciones_descansos(model, x, empleados, turnos_validos_por_empleado)
    _agregar_restricciones_puestos(model, x, turnos, empleados)

    (dias_libres_seguidos_vars,
     penalizacion_10h_vars,
     penalizacion_5dias_vars,
     penalizacion_finde_libre_vars) = _configurar_dias_libres_y_penalizaciones(model, x, empleados, turnos_validos_por_empleado, ampliar_limites)

    _configurar_funcion_objetivo(model, x, empleados, turnos_validos_por_empleado, horas_maximas_emp, dias_libres_seguidos_vars, penalizacion_10h_vars, penalizacion_5dias_vars, penalizacion_finde_libre_vars)

    tiempo_maximo_minutos = float(data.get('tiempo_maximo', 2))

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = max(1.0, tiempo_maximo_minutos * 60.0)
    solver.parameters.num_search_workers = 4
    solver.parameters.log_search_progress = True

    status = solver.Solve(model)
    status_name = solver.StatusName(status)

    print(f"\n[{tiempo_maximo_minutos} min limit] Solver finalizó con estado: {status_name}. Tiempo invertido: {solver.WallTime():.2f}s")

    resultado = []
    if status in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        print(f"Puntuación (Objective Value): {solver.ObjectiveValue()}")
        for t in turnos:
            t_id = t['turno_id']
            for e in empleados:
                emp_id = e['id']
                if (emp_id, t_id) in x and solver.Value(x[(emp_id, t_id)]) == 1:
                    resultado.append({
                        "turno_id": t_id,
                        "empleado_id": emp_id,
                        "nombre": e['nombre']
                    })
                    break

    return resultado


@app.route('/generar', methods=['POST'])
def generar_endpoint():
    try:
        data = request.get_json()
        if not data:
            return jsonify({"success": False, "message": "No JSON received"}), 400

        resultado = generar_horarios(data)

        if isinstance(resultado, dict) and resultado.get("error_message"):
            return jsonify({
                "success": False,
                "message": resultado["error_message"]
            }), 422

        if isinstance(resultado, dict) and resultado.get("requires_confirmation"):
            return jsonify({
                "success": False,
                "requires_confirmation": True,
                "requeridas": resultado.get("requeridas", {}),
                "disponibles": resultado.get("disponibles", {}),
                "deficit": resultado.get("deficit", {}),
                "exceso": resultado.get("exceso", {}),
                "message": "Hay problemas con la cobertura de turnos debido a los contratos."
            }), 200

        if not resultado:
            return jsonify({
                "success": False,
                "message": "No se encontró solución factible con los datos proporcionados."
            }), 404

        return jsonify({
            "success": True,
            "resultados": resultado
        })
    except ValueError as e:
        return jsonify({"success": False, "message": str(e)}), 422
    except Exception as e:
        app.logger.exception("No se pudo generar el horario de demostración")
        return jsonify({"success": False, "message": "No se pudo generar el horario. Revisa los datos ficticios e inténtalo de nuevo."}), 500


if __name__ == "__main__":
    import sys

    # Forzar la salida estándar a UTF-8 para evitar errores de charmap en Windows
    if sys.stdout.encoding != 'utf-8':
        sys.stdout.reconfigure(encoding='utf-8')
    if sys.stderr.encoding != 'utf-8':
        sys.stderr.reconfigure(encoding='utf-8')

    print("Iniciando servidor API en el puerto 5000...")
    app.run(port=5000, debug=False, host='127.0.0.1')
