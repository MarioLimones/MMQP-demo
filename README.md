# MMQP Demo

Demo funcional de MMQP para enseñar el producto. Conserva la aplicación de gestión, el generador de horarios y el código Android del kiosco. Los perfiles, horarios, plantillas y registros de ejemplo se crean desde cero con datos ficticios al instalar.

**[Descargar la demo en ZIP](https://github.com/MarioLimones/MMQP-demo/archive/refs/heads/main.zip)**

La aplicación se abre en tu propio equipo. La forma más sencilla de probarla es con Docker: no necesitas instalar PHP, Python ni Node por separado. Sigue los pasos de abajo y entra con una de las cuentas de prueba.

## Inicio con Docker

1. Descarga el ZIP del enlace de arriba o pulsa **Code → Download ZIP** en GitHub.
2. **Extrae todo el ZIP**. Entra en la carpeta `MMQP-demo-main` que contiene `compose.yaml` y `Dockerfile`. No ejecutes los comandos dentro del archivo comprimido.
3. Abre **Docker Desktop** y espera a que su motor esté listo. En Linux puedes usar Docker Engine con el complemento Compose.
4. Abre una terminal en esa carpeta. En Windows puedes usar **Abrir en Terminal** desde el Explorador de archivos.
5. Ejecuta el comando siguiente. La primera construcción descarga las dependencias y puede tardar varios minutos.

Desde la carpeta que contiene `compose.yaml`:

```bash
docker compose up --build -d
```

6. Cuando termine, abre **[http://localhost:8080](http://localhost:8080)** en el navegador del mismo equipo e inicia sesión con una de las cuentas de la tabla de abajo.

El arranque crea la base SQLite exclusiva de esta demo, aplica sus migraciones y añade los ejemplos automáticamente. No tienes que crear usuarios, copiar un `.env` ni importar una base de datos. Para comprobar el estado y los últimos mensajes de arranque:

```bash
docker compose ps
docker compose logs --tail=100 demo
```

Solo se publica el puerto web. Laravel, Nginx y el generador Python funcionan en el mismo contenedor. No se monta ninguna carpeta de otra instalación. La base y el almacenamiento se conservan en los volúmenes propios `mmqp-demo_demo_data` y `mmqp-demo_demo_storage`.

Para actualizar el código y reconstruir sin perder los cambios de la demo:

```bash
docker compose up --build -d
```

Para detenerla conservando los datos:

```bash
docker compose down
```

Para volver a abrirla después, ejecuta `docker compose up -d` desde la misma carpeta.

Si prefieres descargar con Git, puedes sustituir los dos primeros pasos por:

```bash
git clone https://github.com/MarioLimones/MMQP-demo.git
cd MMQP-demo
```

## Accesos de prueba

| Perfil | Correo | Contraseña |
| --- | --- | --- |
| Administración | `admin@mmqp.example` | `DemoMMQP!2026` |
| Oficina | `oficina@mmqp.example` | `DemoMMQP!2026` |
| Empleado | `empleado@mmqp.example` | `DemoMMQP!2026` |

Son credenciales públicas creadas para la demo, sin relación con ninguna cuenta operativa. El PIN del empleado **Encargado 01 Demo** es `00000003`. Sus DNI de ejemplo (como `00000003D`, con letra de control deliberadamente inválida) y correos `.example` son ficticios. Se crean 14 perfiles en total.

**¿Con cuál empiezo?** Usa Administración para recorrer las herramientas de gestión; Oficina para sus tareas administrativas; y Empleado para consultar sus turnos y su calendario. La cuenta de administrador no tiene jornada ni turnos propios: su calendario personal estará vacío. Para ver al equipo desde esa cuenta, abre **Calendario Global**. El trabajador no tiene acceso a Arqueo.

## Si algo no abre

| Qué ocurre | Qué revisar |
| --- | --- |
| Docker no conecta con el motor | Abre Docker Desktop y espera a que termine de arrancar. Puedes comprobarlo con `docker info`. |
| `localhost:8080` no responde | Espera a que termine la construcción. Consulta `docker compose ps` y `docker compose logs --tail=100 demo`. |
| El puerto 8080 está ocupado | Usa otro puerto con los comandos que aparecen debajo. |
| El calendario del administrador está vacío | Es normal: no tiene jornada. Abre Calendario Global o entra con la cuenta de empleado. |
| La instalación lleva tiempo creada y no hay turnos este mes | Los ejemplos se generan para la fecha del primer arranque. Consulta esa semana o restaura los ejemplos si quieres empezar de nuevo. |
| El navegador muestra una pantalla anterior | Recarga la página. Después de actualizar código, reconstruye con `docker compose up --build -d`. |

Para usar el puerto **8081** en PowerShell:

```powershell
$env:MMQP_DEMO_PORT="8081"
$env:MMQP_DEMO_URL="http://localhost:8081"
docker compose up --build -d
```

En Linux o macOS:

```bash
MMQP_DEMO_PORT=8081 MMQP_DEMO_URL=http://localhost:8081 docker compose up --build -d
```

Abre entonces `http://localhost:8081`. Mantén ambas variables al reconstruir esa instalación.

## Qué se puede probar

- Perfiles de administración, oficina y empleados; maestro de empleados y disponibilidad. El trabajador dispone de su zona personal de turnos y calendarios; Arqueo está reservado a Administración y Oficina, también al abrir enlaces directos.
- Planificación de horarios, edición, publicación, historial y una plantilla de ejemplo.
- Calendarios, solicitudes de ausencia y festivos de ejemplo.
- Fichajes y registro de jornada con registros ficticios.
- Arqueo con una plantilla A4 ya diseñada, tres perfiles de ejemplo, entregas y resumen del turno.
- Consumos con dos proveedores inventados, ocho productos y una ficha de cantidades de prueba.
- Cuadre de caja con registros propios de la demo.
- «Más información» en cada apartado: explicaciones breves y cercanas, cerradas por defecto y disponibles también con teclado.
- Etiquetas visibles en los horarios de ejemplo; los dos publicados y el borrador se crean desde cero con perfiles ficticios.
- Generación automática de horarios y plantilla con datos sintéticos, sin historial de entrenamiento operativo.

La aplicación muestra que se trata de una demo. Los datos del ejemplo se sitúan respecto a la fecha de instalación para que los calendarios tengan contenido visible. Volver a arrancar no repone ni sobrescribe lo que hayas editado.

## Restaurar los ejemplos iniciales

El siguiente comando **elimina los cambios y archivos de esta demo** y sus volúmenes. Ejecútalo solo desde esta copia, con su `compose.yaml`; no afecta a la instalación original de MMQP:

```bash
docker compose down --volumes
docker compose up --build -d
```

La nueva instalación genera otra clave y vuelve a crear todos los ejemplos. No hay un botón público para borrar o reiniciar la demo.

## Instalación local sin Docker

Esta alternativa es para quien ya tenga un entorno de desarrollo. Requiere PHP compatible con `mmqp_hub/composer.json` (8.2.35+, 8.3.36+, 8.4.27+ o 8.5.12+ en su rama), Composer, Node.js 20.19+ o 22.12+ según Vite, y Python 3.11+. PHP necesita PDO SQLite, mbstring, XML/DOM y las extensiones habituales de Laravel; la importación de PDF de Consumos usa Python.

1. Crea el entorno Python **dentro de esta copia**:

```bash
python -m venv algoritmo/venv
```

En Windows:

```powershell
.\algoritmo\venv\Scripts\python.exe -m pip install -r algoritmo/requirements.txt -r mmqp_hub/requirements-consumos.txt
```

En Linux/macOS:

```bash
./algoritmo/venv/bin/python -m pip install -r algoritmo/requirements.txt -r mmqp_hub/requirements-consumos.txt
```

2. Instala y prepara la aplicación:

```bash
cd mmqp_hub
composer run setup
```

También puedes ejecutar por separado `composer install`, `npm ci`, `npm run build` y, desde la raíz, `php scripts/prepare-demo.php`.

El preparador crea una clave nueva, un `.env` de demo y `mmqp_hub/database/demo.sqlite`. Rechaza configuraciones ajenas y bases externas; no utiliza bases, claves o archivos de otra instalación. Si ya completaste la instalación, puedes repetir `php scripts/prepare-demo.php` para aplicar migraciones sin borrar cambios.

3. Arranca dos terminales. Para Laravel, desde `mmqp_hub`:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Para el generador, desde la raíz, en Windows:

```powershell
.\algoritmo\venv\Scripts\python.exe algoritmo/generador_horarios.py
```

O en Linux/macOS:

```bash
./algoritmo/venv/bin/python algoritmo/generador_horarios.py
```

Abre **http://127.0.0.1:8000**. El generador usa el puerto local 5000. El paquete incluye los assets web compilados en `mmqp_hub/public/build`; el instalador los reconstruye para esa copia. No hace falta dejar Vite abierto.

## Código Android del kiosco

`fichaje_app` contiene el código fuente del kiosco, sin APK compilado, claves de firma ni configuración personal del IDE. Abre la carpeta con Android Studio. La aplicación usa un identificador de paquete propio (`com.example.fichajesapp.demo`) y el nombre MMQP demo. Su URL predeterminada, `http://10.0.2.2:8080/`, conecta el emulador Android con esta demo en Docker. Para cambiarla compila con la propiedad Gradle `-PMMQP_DEMO_URL=http://10.0.2.2:8000/` si usas Laravel local, o con la URL del servidor de demo accesible desde el dispositivo físico. La URL debe terminar en `/`. Esta propiedad genera `BuildConfig.DEMO_BASE_URL`; no hay que modificar el código de la API.

## Nuevo repositorio y enlace de LinkedIn

El repositorio es **[MarioLimones/MMQP-demo](https://github.com/MarioLimones/MMQP-demo)**. Contiene una copia independiente, sin el historial Git del proyecto original. El `.gitignore` excluye bases, `.env`, almacenamiento, copias de seguridad y dependencias. Los assets web compilados se incluyen para que también estén en la descarga de GitHub.

El ZIP es el proyecto instalable, no un alojamiento publicado. Para compartir un enlace interactivo en LinkedIn, despliega esta copia en un servidor con Docker y configura `MMQP_DEMO_URL` con su URL pública y `MMQP_DEMO_PORT` con el puerto HTTP del contenedor. Por ejemplo, antes de `docker compose up --build -d`, define `MMQP_DEMO_URL=https://tu-demo.example` y sitúa el servicio detrás del HTTPS de tu alojamiento. Las cuentas de arriba permiten que los visitantes prueben los perfiles.

Los visitantes con acceso al mismo despliegue comparten los cambios sobre los datos ficticios. La demo no envía correos ni notificaciones push reales y debe utilizarse siempre con información inventada.
días) y noviembre (30 días). Pruebas relacionadas: 93 PHP (612 aserciones) y dos pruebas DOM/Alpine de persistencia y cierre.
