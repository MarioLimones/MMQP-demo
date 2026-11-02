import json
data = json.load(open('test_payload_manual.json', encoding='utf-8'))
for d in data['plantilla_diseno']:
    if d['dia'] == 'Martes':
        for idx, t in enumerate(d['turnos_requeridos']):
            if t['rol_necesario'] == 'repartidor':
                print(f"Index {idx+1}: {t['hora_inicio']} - {t['hora_fin']}, Puesto: {t.get('puesto_num')}")
