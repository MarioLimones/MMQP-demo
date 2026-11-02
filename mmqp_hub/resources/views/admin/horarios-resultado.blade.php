<x-app-layout>
    <div id="main-content-wrapper" class="py-12 sm:py-16 lg:py-20 bg-slate-50 dark:bg-slate-900 min-h-screen print:py-0 print:bg-white transition-colors duration-300" x-data='resultadosHorarios(@json($data), @json($es_borrador ?? false))'>
        <div class="calendar-viewport"
            @if(request()->routeIs('dashboard.calendario_global'))
                role="region" aria-label="Calendario global"
            @endif
        >
            <div class="calendar-content w-full px-4 sm:px-8 lg:px-12 mx-auto print:hidden">
                @include('admin.horarios-resultado.header')

                @include('admin.horarios-resultado.alertas')
                @include('components.absence-feedback', ['conflicts' => $data['absence_conflicts'] ?? []])

                <template x-for="rol in rolesDisponibles" :key="rol">
                    <div class="calendar-role bg-white dark:bg-slate-800 shadow-2xl shadow-slate-200/50 dark:shadow-none rounded-2xl sm:rounded-[2rem] border border-slate-100 dark:border-slate-700 mb-16 print:shadow-none print:border-none print:mb-8 transition-colors duration-300 overflow-hidden w-full">
                        <div class="calendar-role-heading bg-amber-400 px-6 sm:px-10 py-5 sm:py-6 flex items-center gap-4 border-b border-amber-500">
                            <h3 class="text-xl sm:text-2xl font-black text-slate-900 uppercase tracking-tight" x-text="getRolPlural(rol)"></h3>
                        </div>

                        <div class="w-full">
                            <x-tabla-horario />
                        </div>
                    </div>
                </template>
            </div>

            @include('admin.horarios-resultado.modal-impresion')
            @include('admin.horarios-resultado.modal-turno')
            @include('admin.horarios-resultado.modal-publicar')
        </div>
    </div>

    @include('admin.horarios-resultado.scripts')
</x-app-layout>
