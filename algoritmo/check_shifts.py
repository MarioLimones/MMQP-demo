import json
data = json.load(open('test_payload_manual.json', encoding='utf-8'))
for d in data['plantilla_diseno']:
    if d['dia'] in ['Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado']:
        print(f"--- {d['dia']} ---")
        for t in d['turnos_requeridos']:
            if t['rol_necesario'] == 'repartidor':
                print(f"{t['hora_inicio']} - {t['hora_fin']}")
