<x-app-layout>


    <style>
        /* Estilos generales FullCalendar (Light Mode por defecto) */
        .fc { font-family: inherit; }
        .fc-theme-standard .fc-scrollgrid { border-color: #e2e8f0; border-radius: 0.75rem; overflow: hidden; border-style: hidden; box-shadow: 0 0 0 1px #e2e8f0; }
        .fc-theme-standard td, .fc-theme-standard th { border-color: #e2e8f0; }
        .fc-col-header-cell { padding: 8px 0; background: #f8fafc; }
        .fc-col-header-cell-cushion { color: #475569; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; text-decoration: none !important; }
        .fc-daygrid-day-number { color: #334155; font-weight: 600; padding: 4px 8px !important; text-decoration: none !important; font-size: 0.9rem; }
        .fc-day-today { background-color: rgba(99, 102, 241, 0.05) !important; }
        
        .fc-button-primary { display: inline-flex; align-items: center; justify-content: center; background-color: #4f46e5 !important; border-color: #4f46e5 !important; font-weight: 600 !important; border-radius: 0.5rem !important; padding: 0.4rem 0.8rem !important; text-transform: capitalize; transition: all 0.2s; }
        .fc-button-primary:hover { background-color: #4338ca !important; border-color: #4338ca !important; }
        .fc-button-primary:not(:disabled).fc-button-active, .fc-button-primary:not(:disabled):active { background-color: #3730a3 !important; border-color: #3730a3 !important; }
        .fc-prev-button, .fc-next-button { padding: 0.4rem !important; aspect-ratio: 1; }
        #calendar .fc-prev-button + .fc-next-button { margin-left: 6px; }
        
        /* Ocultar el fondo y bordes de los eventos originales porque ahora son puntos */
        .fc-event { background: transparent !important; border: none !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; }
        .fc-event:hover { background: transparent !important; cursor: pointer; }
        
        .fc-toolbar-title { font-weight: 800 !important; font-size: 1.25rem !important; color: #1e293b; text-transform: capitalize; }
        .fc-header-toolbar { flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem !important; }
        
        /* Dark Mode (Tailwind) */
        .dark .fc-theme-standard .fc-scrollgrid { border-color: #334155; box-shadow: 0 0 0 1px #334155; }
        .dark .fc-theme-standard td, .dark .fc-theme-standard th { border-color: #334155; background-color: transparent !important; }
        .dark .fc-col-header-cell { background: #1e293b; }
        .dark .fc-col-header-cell-cushion { color: #cbd5e1; }
        .dark .fc-daygrid-day-number { color: #f8fafc; }
        .dark .fc-day-today { background-color: rgba(99, 102, 241, 0.25) !important; }
        .dark .fc-toolbar-title { color: #f8fafc; }
        .dark .fc-button-primary { background-color: #6366f1 !important; border-color: #6366f1 !important; }
        .dark .fc-button-primary:hover { background-color: #4f46e5 !important; border-color: #4f46e5 !important; }
    </style>

    <div class="py-6 sm:py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-slate-800 overflow-hidden shadow-sm sm:rounded-2xl transition-colors duration-300">
                <div class="p-4 sm:p-6">
                    <!-- Contenedor del calendario -->
                    <div id="calendar" class="w-full text-slate-800 dark:text-slate-200"></div>
                </div>
            </div>

            <!-- Panel de detalles del turno -->
            <div id="turno-detalles" class="hidden bg-white dark:bg-slate-800 overflow-hidden shadow-sm sm:rounded-2xl transition-colors duration-300 border border-slate-200 dark:border-slate-700">
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-2">
                        <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <h3 class="font-bold text-xl text-slate-800 dark:text-slate-200 capitalize" id="turno-fecha"></h3>
                    </div>
                    <p class="text-slate-600 dark:text-slate-400 text-lg">
                        Horario programado: <span id="turno-hora" class="font-bold text-red-600 dark:text-red-400 ml-1"></span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Cargar CSS y JS de FullCalendar desde CDN -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/locales/es.global.min.js'></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var eventos = @json($eventos);
            
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'es',
                dayHeaderDidMount: window.mmqpCalendarAccessibility.dayHeaderDidMount,
                viewDidMount: window.mmqpCalendarAccessibility.viewDidMount,
                headerToolbar: {
                    left: 'prev,next',
                    center: 'title',
                    right: 'today'
                },
                events: eventos,
                contentHeight: 'auto',
                firstDay: 1, // Lunes como primer día de la semana
                themeSystem: 'standard',
                eventContent: function(arg) {
                    // Determinar si es un turno pasado o futuro/hoy
                    var today = new Date();
                    today.setHours(0,0,0,0);
                    
                    var eventDate = new Date(arg.event.start);
                    eventDate.setHours(0,0,0,0);

                    var isPast = eventDate < today;
                    var colorClass = isPast ? 'bg-slate-400 dark:bg-slate-500' : 'bg-green-500';
                    
                    return {
                        html: '<div class="flex justify-center w-full mt-1 mb-1"><div class="w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full ' + colorClass + ' shadow-sm"></div></div>'
                    };
                },
                eventClick: function(info) {
                    var detalles = document.getElementById('turno-detalles');
                    var fecha = document.getElementById('turno-fecha');
                    var hora = document.getElementById('turno-hora');
                    
                    var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    fecha.innerText = info.event.start.toLocaleDateString('es-ES', options);
                    hora.innerText = info.event.title;
                    
                    detalles.classList.remove('hidden');
                    
                    // Hacer scroll suave hacia los detalles en móviles
                    detalles.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
            calendar.render();
        });
    </script>
</x-app-layout>
