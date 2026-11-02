<?php

// First matching route wins. Query-specific entries precede their section entry.
return [
    'calendario-personal' => [
        'routes' => ['dashboard.calendario'],
        'administrator' => [
            'title' => 'Tu cuenta administra el equipo',
            'paragraphs' => [
                'Has entrado como administrador. Esta cuenta no tiene jornada ni turnos propios, así que es normal que su calendario personal esté vacío.',
                'Para consultar los horarios del equipo, abre Calendario Global. Con la cuenta empleado@mmqp.example sí encontrarás un calendario personal con turnos ficticios preparados para la demo.',
            ],
            'tip' => 'Abre Calendario Global para ver la semana del equipo.',
        ],
        'title' => 'Tus turnos, de un vistazo',
        'paragraphs' => [
            'Aquí tienes tus turnos publicados para organizarte sin tener que buscar el último horario entre mensajes. En la demo hemos preparado una semana con turnos inventados.',
            'Los días con un punto tienen turnos. Pulsa uno para ver sus horas debajo del calendario; en el móvil, baja un poco si el detalle queda fuera de la pantalla.',
        ],
        'tip' => 'Pulsa un día marcado para consultar su horario.',
    ],
    'calendario-equipo' => [
        'routes' => ['dashboard.calendario_global'],
        'title' => 'Así se organiza el equipo',
        'paragraphs' => [
            'Este calendario reúne los turnos de todos para que sea fácil ver quién trabaja cada día y con quién coincides.',
            'Puedes consultar las horas por persona y cambiar de semana. Los nombres y horarios que ves aquí se han inventado para la demo; no pertenecen a una empresa real.',
        ],
        'tip' => 'Busca a Encargado 01 y compara sus turnos durante la semana.',
    ],
    'portal' => [
        'routes' => ['dashboard'],
        'administrator' => [
            'title' => 'Has entrado con la cuenta de administración',
            'paragraphs' => [
                'Esta cuenta sirve para organizar el equipo. No tiene jornada ni turnos propios, por eso no verás un próximo turno ni actividad en su calendario personal.',
                'Desde aquí puedes revisar los horarios del equipo en Calendario Global. Si quieres probar la experiencia de un trabajador, entra con la cuenta empleado@mmqp.example.',
            ],
            'tip' => 'Abre Calendario Global para ver cómo se reparte la semana.',
        ],
        'title' => 'Bienvenido a tu portal',
        'paragraphs' => [
            'La idea es que al entrar encuentres lo que más necesitas: tu próximo turno y los accesos a tus calendarios.',
            'Verás los turnos publicados que te corresponden. Los de esta demo se han inventado para que puedas consultar tu semana sin utilizar datos reales.',
        ],
        'tip' => 'Abre el calendario para ver cómo continúa la semana.',
    ],
    'empleado-formulario' => [
        'routes' => ['admin.empleados.crear', 'admin.empleados.editar'],
        'title' => 'Vamos a preparar una ficha',
        'paragraphs' => [
            'Esta ficha reúne los datos de la persona, su puesto y las condiciones que ayudan a organizar sus turnos.',
            'La disponibilidad, las horas de contrato y las fechas de alta o fin permiten planificar contando con las personas adecuadas. Puedes explorar el formulario con un perfil inventado.',
        ],
        'tip' => 'Prueba con un nombre de ejemplo y revisa las opciones de disponibilidad.',
    ],
    'empleados-activos' => [
        'routes' => ['admin.empleados.activos'],
        'title' => '¿Quién está trabajando ahora?',
        'paragraphs' => [
            'Aquí puedes consultar las personas que tienen un fichaje abierto. Es una forma rápida de saber quién ha entrado y todavía no ha registrado su salida.',
            'La demo incluye un fichaje de ejemplo para que veas el resultado. Los turnos previstos y las entradas registradas son cosas distintas.',
        ],
        'tip' => 'Compáralo con Control de asistencia para ver el detalle del día.',
    ],
    'empleados' => [
        'routes' => ['admin.empleados*'],
        'title' => 'Conoce al equipo de la demo',
        'paragraphs' => [
            'Aquí reunimos las fichas del equipo para no tener sus datos repartidos en distintas hojas. Cada persona tiene su puesto, su jornada y su disponibilidad.',
            'Preparamos perfiles ficticios con situaciones diferentes, incluido uno de baja y otro inactivo. Puedes buscarlos, filtrarlos y abrir una ficha para ver cómo se organiza la información.',
        ],
        'tip' => 'Filtra por puesto y abre una ficha.',
    ],
    'solicitudes' => [
        'routes' => ['admin.solicitudes*'],
        'title' => 'Un lugar para las peticiones del equipo',
        'paragraphs' => [
            'Cuando alguien pide un día libre o vacaciones, su solicitud llega a este buzón. Así es más fácil revisarla y dejar una respuesta que la persona pueda consultar.',
            'Puedes distinguir las pendientes de las ya resueltas. Las ausencias aprobadas se tienen en cuenta al organizar los horarios.',
        ],
        'tip' => 'Abre la solicitud de ejemplo pendiente y revisa sus fechas.',
    ],
    'vacaciones-equipo' => [
        'routes' => ['admin.vacaciones*'],
        'title' => 'Las ausencias, con un poco de perspectiva',
        'paragraphs' => [
            'Este calendario ayuda a ver cuándo falta alguien y a organizar el trabajo con tiempo. Reúne vacaciones, días libres y bajas del equipo.',
            'Desde administración también se pueden anotar ausencias y ajustar sus fechas. Hemos dejado varios ejemplos inventados para que veas cómo conviven en el calendario.',
        ],
        'tip' => 'Selecciona una ausencia de ejemplo para ver sus detalles.',
    ],
    'festivos' => [
        'routes' => ['admin.festivos.*'],
        'title' => 'Los días especiales también cuentan',
        'paragraphs' => [
            'No todas las semanas son iguales. Aquí se indican los festivos que debe conocer la planificación para interpretar bien la disponibilidad de cada persona.',
            'El calendario de la demo incluye un festivo inventado. Puedes consultar o editar los días para entender cómo se prepara una semana con fechas especiales.',
        ],
        'tip' => 'Compara el festivo con la disponibilidad de un perfil de ejemplo.',
    ],
    'asistencia' => [
        'routes' => ['admin.fichajes.*'],
        'title' => 'Lo previsto y lo que ha ocurrido',
        'paragraphs' => [
            'Aquí se ponen juntos los turnos previstos y las entradas y salidas registradas. Así puedes revisar el día sin saltar entre varias pantallas.',
            'Puedes elegir otra fecha y consultar los fichajes de cada persona. Los registros de esta demo están inventados para mostrar tanto jornadas terminadas como una entrada todavía abierta.',
        ],
        'tip' => 'Consulta un día de la semana anterior.',
    ],
    'registro-jornada' => [
        'routes' => ['admin.registro-jornada.*'],
        'title' => 'De los fichajes a un informe',
        'paragraphs' => [
            'Cuando necesitas revisar un periodo, esta pantalla reúne las entradas y salidas de las personas que elijas en un documento.',
            'Selecciona las fechas y el equipo que quieres incluir. El informe de la demo utiliza únicamente fichajes ficticios; sirve para conocer el formato y probar la consulta.',
        ],
        'tip' => 'Incluye la semana anterior y un empleado de ejemplo para empezar.',
    ],
    'horarios-historial' => [
        'routes' => ['admin.horarios.historial*'],
        'title' => 'Volver a un horario cuando lo necesitas',
        'paragraphs' => [
            'Los horarios se guardan aquí para poder consultarlos más adelante. Verás qué semana corresponde a cada uno y si está publicada o sigue en borrador.',
            'Los ejemplos de esta demo se han creado desde cero con perfiles inventados. No son horarios de la empresa ni copias de su historial.',
        ],
        'tip' => 'Abre el año, el mes y un horario.',
    ],
    'horario-resultado' => [
        'routes' => ['admin.horarios.resultado'],
        'title' => 'Revisar antes de compartir',
        'paragraphs' => [
            'Esta es la vista detallada del horario: quién cubre cada turno, qué horas suma y qué queda por revisar.',
            'Un borrador permite preparar cambios antes de publicarlos. Un horario publicado es el que puede consultar el equipo. Los horarios que dejamos preparados son ejemplos inventados para mostrar el funcionamiento.',
        ],
        'tip' => 'Fíjate en el estado del horario antes de empezar a editar.',
    ],
    'horarios' => [
        'routes' => ['admin.horarios'],
        'title' => 'Vamos a preparar una semana',
        'paragraphs' => [
            'Aquí decides qué turnos hacen falta y cómo repartirlos entre el equipo. Puedes partir de una plantilla, ajustar las horas y pedir una propuesta automática.',
            'El trabajo se prepara en un borrador para que puedas revisarlo antes de publicar. Las ausencias y la disponibilidad ayudan a evitar asignaciones que no encajan.',
        ],
        'tip' => 'Carga la plantilla de ejemplo.',
    ],
    'plantillas' => [
        'routes' => ['admin.plantillas*'],
        'title' => 'Un punto de partida para otra semana',
        'paragraphs' => [
            'Una plantilla guarda una estructura de turnos que puedes reutilizar. Así no hace falta dibujar una semana parecida desde cero cada vez.',
            'Después puedes adaptar los días y las horas al equipo disponible. La plantilla de la demo es inventada y está pensada para que puedas probar ese primer paso.',
        ],
        'tip' => 'Carga la plantilla y cambia un turno.',
    ],
    'arqueo-diseno' => [
        'routes' => ['admin.arqueo', 'arqueo'],
        'query' => ['modo' => 'editar'],
        'title' => 'Una hoja que se adapta al trabajo',
        'paragraphs' => [
            'La plantilla es la hoja que se reutilizará cada día. Aquí puedes organizar sus casillas, cambiar los títulos y decidir qué datos se rellenarán después.',
            'Te dejamos un diseño de ejemplo, creado para la demo. Puedes explorarlo y ajustar sus casillas para entender cómo se prepara una hoja de trabajo.',
        ],
        'tip' => 'Selecciona una casilla y prueba a editar.',
    ],
    'arqueo-dia' => [
        'routes' => ['admin.arqueo', 'arqueo'],
        'query' => ['modo' => 'rellenar'],
        'title' => 'La hoja de cada día',
        'paragraphs' => [
            'Con la plantilla preparada, ya puedes completar la hoja del día. La fecha y los datos vinculados al equipo ayudan a evitar escribir lo mismo una y otra vez.',
            'Los importes y las anotaciones se guardan para esa fecha. Cambiar de día permite trabajar con otra hoja sin sustituir la plantilla.',
        ],
        'tip' => 'Guarda un importe y vuelve a abrirlo.',
    ],
    'arqueo' => [
        'routes' => ['admin.arqueo', 'arqueo'],
        'title' => 'Primero la hoja, después el día',
        'paragraphs' => [
            'Arqueo permite preparar una hoja de trabajo y utilizarla cada día para anotar importes y observaciones del equipo.',
            'Editar plantilla sirve para diseñar esa hoja; rellenar el día sirve para usarla sin cambiar su estructura. Ya tienes un ejemplo inventado para probar ambas partes sin empezar en blanco.',
        ],
        'tip' => 'Entra en Editar plantilla para ver el diseño que hemos preparado.',
    ],
    'consumos-guardado' => [
        'routes' => ['admin.consumos.historial.show'],
        'title' => 'Así quedó este consumo',
        'paragraphs' => [
            'Estás viendo una copia guardada de la ficha, con los datos que tenía en ese momento. Sirve para consultar cómo quedó el trabajo aunque después cambie el documento.',
            'Puedes recorrer sus hojas y revisar los productos. La ficha de ejemplo se ha preparado con proveedores, productos y cantidades inventados.',
        ],
        'tip' => 'Compara las cantidades de consumo, stock y pedido de un producto.',
    ],
    'consumos-historial' => [
        'routes' => ['admin.consumos.historial'],
        'title' => 'Encontrar un consumo ya guardado',
        'paragraphs' => [
            'Aquí puedes volver a las fichas que se guardaron en otros días y abrirlas tal como quedaron entonces.',
            'El calendario te ayuda a encontrarlas por fecha, sin tener que recordar el nombre de un archivo. Hemos guardado una ficha ficticia para que puedas hacer el recorrido completo.',
        ],
        'tip' => 'Selecciona el día que tiene un registro y abre la ficha de ejemplo.',
    ],
    'consumos' => [
        'routes' => ['admin.consumos', 'admin.consumos.documento.*', 'admin.consumos.fichas.*', 'admin.consumos.importaciones.*'],
        'title' => 'Del consumo al pedido, paso a paso',
        'paragraphs' => [
            'Aquí reunimos los productos, su consumo y lo que queda en stock para preparar el pedido con más contexto.',
            'Editar documento sirve para organizar la hoja. Cargar datos de consumo permite revisar los productos del informe y completar la ficha. El historial conserva lo guardado. Ya tienes ejemplos inventados para probarlo.',
        ],
        'tip' => 'Abre Editar documento y explora la plantilla.',
    ],
    'cuadre-revisiones' => [
        'routes' => ['admin.cuadre.revisiones'],
        'title' => 'Entender los cambios de una caja',
        'paragraphs' => [
            'A veces hace falta corregir un dato después de guardar. Las revisiones permiten consultar qué se cambió y quién dejó ese ajuste.',
            'Así se puede entender la evolución de una caja sin perder el contexto de los registros anteriores. Los movimientos que ves en esta demo son ficticios.',
        ],
        'tip' => 'Compara una revisión con el registro actual.',
    ],
    'cuadre-historial' => [
        'routes' => ['admin.cuadre.historial'],
        'title' => 'La caja, con perspectiva',
        'paragraphs' => [
            'Este historial reúne las aperturas y los cierres que se han guardado. Puedes consultar un mes y volver a una caja concreta para revisar sus importes.',
            'Las diferencias ayudan a localizar los días que merecen una segunda mirada. Hemos añadido registros ficticios para que puedas ver el recorrido desde el resumen hasta el detalle.',
        ],
        'tip' => 'Abre una caja y consulta sus revisiones.',
    ],
    'cuadre' => [
        'routes' => ['admin.cuadre'],
        'title' => 'Vamos a contar la caja',
        'paragraphs' => [
            'El cuadre ayuda a dejar por escrito el efectivo contado al abrir o cerrar un turno. Introduces los billetes y las monedas, y la pantalla reúne los importes.',
            'También puedes anotar el ingreso y comprobar cómo queda el fondo. Los ejemplos sirven para practicar el recuento y consultar después lo guardado.',
        ],
        'tip' => 'Cambia un billete y observa el total.',
    ],
    'perfil-datos' => [
        'routes' => ['profile.datos'],
        'administrator' => [
            'title' => 'Los datos de tu cuenta de administración',
            'paragraphs' => [
                'Aquí puedes consultar la información de la cuenta con la que has entrado. En este caso es la de administración, que no tiene contrato, jornada ni turnos propios.',
                'Las fichas de los trabajadores sí reúnen su información laboral. Puedes conocer esa vista entrando con la cuenta de empleado de la demo.',
            ],
            'tip' => 'Revisa los datos de esta cuenta o abre una ficha desde Empleados.',
        ],
        'title' => 'Los datos de tu ficha',
        'paragraphs' => [
            'Aquí se reúne la información de la cuenta y, cuando corresponde, los datos del contrato.',
            'La idea es que el empleado pueda consultar su información en un mismo lugar. En esta demo verás un perfil inventado; para probar cambios en las fichas puedes entrar con la cuenta de administración.',
        ],
        'tip' => 'Consulta la jornada y las fechas del contrato.',
    ],
    'perfil-solicitudes' => [
        'routes' => ['profile.disponibilidad'],
        'title' => 'Pedir un día y seguir la respuesta',
        'paragraphs' => [
            'Si necesitas un día libre, puedes solicitarlo aquí y consultar después cómo va la petición. Así la conversación queda reunida junto a sus fechas.',
            'La solicitud pasa a administración para que la revise. Mientras está pendiente, todavía no es una ausencia aprobada.',
        ],
        'tip' => 'Envía una solicitud ficticia y después revísala con la cuenta de administración.',
    ],
    'perfil-vacaciones' => [
        'routes' => ['profile.vacaciones'],
        'title' => 'Tus vacaciones, bien localizadas',
        'paragraphs' => [
            'Este calendario ayuda a consultar los días de vacaciones que tienes registrados y a preparar una nueva solicitud.',
            'El equipo de administración revisa las peticiones para organizar la cobertura. Puedes probar el recorrido con unas fechas de ejemplo y volver después para consultar el estado de la solicitud.',
        ],
        'tip' => 'Abre Solicitar vacaciones y elige un periodo ficticio.',
    ],
    'perfil-notificaciones' => [
        'routes' => ['profile.notificaciones'],
        'administrator' => [
            'title' => 'Los avisos de turnos son para el equipo',
            'paragraphs' => [
                'Los empleados pueden recibir avisos sobre sus próximos turnos. Tu cuenta de administración no tiene turnos ni jornada propia.',
                'En esta demo los envíos están desactivados para todos los perfiles. Para ver la planificación del equipo, puedes consultar Calendario Global.',
            ],
            'tip' => 'Abre Calendario Global para consultar los horarios de ejemplo.',
        ],
        'title' => 'Un aviso cuando hace falta',
        'paragraphs' => [
            'Las notificaciones sirven para acercar al empleado los avisos relacionados con sus próximos turnos, sin tener que entrar continuamente a comprobarlos.',
            'En esta demo los envíos están desactivados: puedes conocer el apartado con tranquilidad, sin recibir correos ni notificaciones reales en tu dispositivo.',
        ],
        'tip' => 'Vuelve al calendario para consultar los turnos del perfil de ejemplo.',
    ],
    'perfil-seguridad' => [
        'routes' => ['profile.seguridad'],
        'title' => 'El acceso a tu cuenta',
        'paragraphs' => [
            'Este apartado reúne las opciones relacionadas con la contraseña de la cuenta. En el uso diario, cada persona puede mantener su propio acceso.',
            'Las cuentas de la demo se comparten entre visitantes. Para que todos puedan seguir probándola, te recomendamos conservar la contraseña de ejemplo que aparece al iniciar sesión.',
        ],
        'tip' => 'Explora el formulario sin cambiar la contraseña.',
    ],
    'perfil' => [
        'routes' => ['profile.*'],
        'administrator' => [
            'title' => 'Las opciones de tu cuenta de administración',
            'paragraphs' => [
                'Este perfil reúne los datos y las opciones de tu cuenta de administración. No tiene jornada, turnos ni calendario laboral propio.',
                'Las solicitudes y vacaciones personales corresponden a los trabajadores. Para conocer esa experiencia, entra con la cuenta de empleado de la demo.',
            ],
            'tip' => 'Abre la información de la cuenta para consultar sus datos.',
        ],
        'title' => 'Tu espacio dentro del equipo',
        'paragraphs' => [
            'Desde el perfil puedes consultar tus datos y acceder a las opciones de tu cuenta. Los empleados tienen también sus solicitudes y vacaciones a mano.',
            'Lo que aparece depende del tipo de cuenta. Si quieres conocer la experiencia de una persona del equipo, entra con el perfil de empleado de la demo.',
        ],
        'tip' => 'Abre Datos personales y de contacto.',
    ],
    'general' => [
        'routes' => ['*'],
        'title' => 'Te acompañamos en la visita',
        'paragraphs' => [
            'Puedes conocer MMQP a tu ritmo: abre los apartados, revisa los ejemplos y prueba cómo se conectan las tareas del día a día.',
            'Las personas, los horarios y los importes son inventados. Si quieres hacer una prueba, utiliza también datos ficticios para que todos puedan seguir explorando la demo.',
        ],
        'tip' => 'Empieza por el equipo o el calendario.',
    ],
];
