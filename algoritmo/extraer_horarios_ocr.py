"""Utilidad OCR histórica; desactivada en esta distribución de demostración.

La importación local PDF de la aplicación sigue disponible. No se empaquetan
credenciales ni imágenes de horarios de la instalación original.
"""
import os
import json
import time
import glob


def _ocr_client():
    raise RuntimeError("El OCR externo no está disponible en MMQP demo. Usa la importación local PDF con documentos ficticios.")


def extraer_horario_de_imagen(ruta_imagen, nombre_archivo, ultimo_rol):
    _ocr_client()
    print(f"Procesando: {nombre_archivo}...")
    
    try:
        from PIL import Image
        imagen = Image.open(ruta_imagen)
    except Exception as e:
        print(f"Error al abrir la imagen {ruta_imagen}: {e}")
        return []

    prompt = f"""
    Eres un experto en extraer datos de imágenes de horarios de trabajo.
    Analiza esta imagen y extrae todos los turnos asignados a los empleados.
    
    CRÍTICO:
    1. Fechas: El nombre de este archivo es '{nombre_archivo}'. Úsalo para deducir el mes y el año de la semana. Quiero que en "fecha_o_dia" pongas SIEMPRE la fecha completa, por ejemplo: "Lunes 10-02-2024".
    2. Roles (Cabeceras): El rol de los empleados suele estar escrito como una CABECERA (ej. "AUXILIARES", "REPARTIDORES", "ENCARGADOS"). Tienes que extraer este puesto y asignarlo a cada empleado debajo de ella en el campo "puesto".
    3. Continuación de Roles: Si NO hay ninguna cabecera de rol en la imagen, es porque esta página es una continuación de la anterior. Asume que el rol de estos empleados es '{ultimo_rol}'.
    4. Limpieza de Estados: Ignora por completo anotaciones que no sean horas reales (como "BM", "VAC", "L", "X", etc.). Si la celda de un día contiene esto en lugar de horas, NO extraigas ese turno, simplemente ignóralo. Solo crea turnos cuando haya horas legibles.
    5. Notas del día: A veces debajo de los días de la semana (ej. debajo de "SABADO 27") hay anotaciones como "Cumpleaños", "vispera", "festivo". Extráelas y devuélvelas en el campo "notas_dia". Si no hay, déjalo vacío.

    Devuelve ÚNICAMENTE un array JSON válido con la siguiente estructura. No incluyas nada más en tu respuesta (ni bloques de código markdown como ```json).
    [
      {{
        "fecha_o_dia": "Lunes 10-02-2024",
        "empleado": "Nombre del empleado",
        "hora_inicio": "08:00",
        "hora_fin": "15:00",
        "puesto": "El puesto deducido de la cabecera o el rol heredado",
        "notas_dia": "Notas adicionales debajo del día, si las hay"
      }}
    ]
    """
    
    try:
        # Usamos el modelo desde la variable de entorno, por defecto gemini-1.5-flash
        modelo_gemini = os.getenv('GEMINI_MODEL', 'gemini-1.5-flash')
        response = _ocr_client().models.generate_content(
            model=modelo_gemini,
            contents=[imagen, prompt]
        )
        
        # Limpiamos la respuesta de la IA (a veces meten markdown)
        texto = response.text.replace("```json", "").replace("```", "").strip()
        
        # Convertimos el texto a diccionario de Python (JSON)
        datos = json.loads(texto)
        print(f"  -> Extraídos {len(datos)} turnos.")
        return datos
    
    except Exception as e:
        error_msg = str(e)
        if "429" in error_msg:
            print("  [AVISO] Límite de velocidad alcanzado (429). El servidor nos pide esperar más tiempo. Pausando 60 segundos...")
            time.sleep(60)
            try:
                response = _ocr_client().models.generate_content(
                    model='gemini-3.5-flash-lite',
                    contents=[imagen, prompt]
                )
                texto = response.text.replace("```json", "").replace("```", "").strip()
                return json.loads(texto)
            except Exception as e2:
                print(f"Error en el reintento: {e2}")
                return []
        else:
            print(f"Error procesando la imagen con la IA: {e}")
            return []

def procesar_todas_las_imagenes(carpeta_imagenes, archivo_salida):
    if not os.path.exists(carpeta_imagenes):
        print(f"Error: La carpeta {carpeta_imagenes} no existe.")
        return

    # Buscar JPGs y PNGs
    archivos_jpg = glob.glob(os.path.join(carpeta_imagenes, "*.jpg"))
    archivos_png = glob.glob(os.path.join(carpeta_imagenes, "*.png"))
    archivos = archivos_jpg + archivos_png
    
    # IMPORTANTE: Ordenar los archivos alfabéticamente para que la secuencia de roles (1, 2, 3...) tenga sentido
    archivos.sort()

    print(f"Se encontraron {len(archivos)} imágenes en {carpeta_imagenes}.")
    if len(archivos) == 0:
        return

    todos_los_turnos = []
    
    # Si ya existe el archivo json, lo cargamos para no perder lo anterior
    if os.path.exists(archivo_salida):
        try:
            with open(archivo_salida, 'r', encoding='utf-8') as f:
                todos_los_turnos = json.load(f)
            print(f"Se ha cargado un archivo anterior con {len(todos_los_turnos)} turnos.")
        except json.JSONDecodeError:
            print("El archivo JSON anterior estaba vacío o corrupto, se empezará de cero.")

    # Para no procesar imágenes que ya estaban, leemos los nombres
    imagenes_ya_procesadas = set([t.get("archivo_origen") for t in todos_los_turnos if t.get("archivo_origen")])

    ultimo_rol = "auxiliar" # Valor por defecto inicial

    for ruta in archivos:
        nombre_archivo = os.path.basename(ruta)
        
        if nombre_archivo in imagenes_ya_procesadas:
            print(f"Saltando {nombre_archivo} (ya procesada)")
            # Actualizamos el ultimo_rol basándonos en lo procesado para mantener la cadena
            turnos_previos = [t for t in todos_los_turnos if t.get("archivo_origen") == nombre_archivo]
            for t in turnos_previos:
                if t.get("puesto"):
                    ultimo_rol = t["puesto"]
            continue
            
        turnos = extraer_horario_de_imagen(ruta, nombre_archivo, ultimo_rol)
        
        # Añadimos el nombre del archivo para saber de dónde salió y actualizamos el último rol
        for turno in turnos:
            turno['archivo_origen'] = nombre_archivo
            if turno.get('puesto'):
                ultimo_rol = turno['puesto']
            
        todos_los_turnos.extend(turnos)
        
        # Pausa de 10 segundos entre imágenes para no saturar la API
        print("Esperando 4 segundos para no superar el límite de peticiones por minuto...")
        time.sleep(4)
            
    # Guardar todo en un gran archivo JSON para entrenar tu IA luego
    with open(archivo_salida, 'w', encoding='utf-8') as f:
        json.dump(todos_los_turnos, f, indent=4, ensure_ascii=False)
        
    print(f"\n¡Éxito! Se han extraído {len(todos_los_turnos)} turnos y guardado en {archivo_salida}")

if __name__ == "__main__":
    # ==========================================
    # 2. PON AQUÍ LA RUTA DE TU CARPETA CON IMÁGENES
    # ==========================================
    import os
    base_dir = os.path.dirname(os.path.abspath(__file__))
    CARPETA_CON_PNGS = os.path.join(base_dir, "horarios") 
    ARCHIVO_RESULTADO = os.path.join(base_dir, "datos_entrenamiento.json")
    
    procesar_todas_las_imagenes(CARPETA_CON_PNGS, ARCHIVO_RESULTADO)
