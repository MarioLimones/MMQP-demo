# Verificación de la demo (2 de noviembre de 2026)

- Compilación Vite: correcta; el ZIP incluye `mmqp_hub/public/build`.
- PHPUnit: suite completa de 400 pruebas y 3504 aserciones correctas, con SQLite en memoria y almacenamiento de pruebas; incluye instalación de ejemplos, login, permisos, calendarios, horarios, fichajes, ausencias, caja y Consumos.
- Plantillas ampliadas: nueve pruebas y 615 aserciones correctas en la comprobación específica posterior, incluidas geometría, edición y conservación de cambios.
- Ayuda: 31 temas, con textos adaptados a la cuenta de administrador sin jornada y a los perfiles de empleados con calendario personal; comprobados el despliegue con ratón y teclado y las vistas de edición de Arqueo y Consumos en el navegador de la aplicación.
- JavaScript: 265 pruebas correctas, sin omisiones, con el runtime DOM de pruebas disponible.
- Python del generador: 83 pruebas correctas. Instalador local: seis comprobaciones de aislamiento correctas y preparación real verificada dos veces.
- Navegador Edge: login normal como administrador y empleado; consulta de historial y horario publicado, calendario global y portal del empleado en móvil (390 px, sin desbordamiento horizontal). Hay avisos previos de Alpine sobre la extensión opcional Collapse; las páginas comprobadas no mostraron errores JavaScript.
- Docker Compose y sintaxis de arranque comprobados. La imagen Docker no se ha construido ni arrancado en esta entrega porque el motor Docker local no estaba disponible. Tampoco se ha compilado el APK Android.

Los datos, sesiones, claves generadas, bases y logs usados durante estas pruebas se excluyen del ZIP. Para volver a empaquetar desde esta copia, ejecuta `python -B scripts/package-demo.py`; el empaquetador usa una lista permitida y audita el ZIP antes de entregarlo. Puedes repetir la revisión con `python -B scripts/audit-demo-package.py ../MMQP-demo.zip`.

Comprobación del perfil de trabajador: login normal con `empleado@mmqp.example`, cinco turnos visibles en la semana de ejemplo y consulta de horas al pulsar un día del calendario. La ayuda explica los puntos del calendario móvil. Acceso a Arqueo oculto y bloqueado para trabajadores; 39 pruebas relacionadas correctas (932 aserciones).

Historial de caja: fechas y selector de mes corregidos para pantallas estrechas; el resumen permanece abierto al hacer scroll y se cierra al pulsar de nuevo el día, su fecha o los controles de cierre. Verificado en navegador a 320, 555 y 1280 px, incluidos octubre (31 días) y noviembre (30 días). Pruebas relacionadas: 93 PHP (612 aserciones) y dos pruebas DOM/Alpine de persistencia y cierre.
