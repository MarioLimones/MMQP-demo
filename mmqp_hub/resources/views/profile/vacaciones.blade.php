<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-200 leading-tight">
            {{ __('Vacaciones') }}
        </h2>
    </x-slot>

    <div class="py-12" x-data="{ tab: 'mis_vacaciones' }">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if ($historicalCount > 0)
                <p role="status" class="mb-6 rounded-xl border border-amber-400 p-4 text-amber-800 dark:text-amber-200">Hay {{ $historicalCount }} fechas con bloqueos históricos pendientes de revisar con administración. Aquí se muestran las vacaciones aprobadas.</p>
            @endif
            
            <!-- TABS -->
            <div class="flex border-b border-slate-200 dark:border-slate-700 mb-8">
                <button @click="tab = 'mis_vacaciones'"
                        :class="tab === 'mis_vacaciones' ? 'border-sky-500 text-sky-700 dark:text-sky-400' : 'border-transparent text-slate-600 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-300'"
                        class="w-1/2 py-4 text-center font-bold text-lg border-b-4 transition-colors">
                    Mis vacaciones
                </button>
                <button @click="tab = 'solicitar'"
                        :class="tab === 'solicitar' ? 'border-sky-500 text-sky-700 dark:text-sky-400' : 'border-transparent text-slate-600 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-300'"
                        class="w-1/2 py-4 text-center font-bold text-lg border-b-4 transition-colors">
                    Solicitar vacaciones
                </button>
            </div>

            <!-- TAB CONTENT: MIS VACACIONES -->
            <div x-show="tab === 'mis_vacaciones'" class="space-y-8" x-transition>
                
                <!-- CALENDARIO -->
                <div class="bg-white dark:bg-slate-800 shadow-xl sm:rounded-3xl overflow-hidden border border-slate-200 dark:border-slate-700 p-6 sm:p-8"
                     x-data="vacacionesCalendar({{ $vacaciones_json }})">
                    
                    <div class="flex items-center justify-between mb-6">
                        <button type="button" aria-label="Mes anterior" @click="prevMonth()" class="p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <svg class="w-6 h-6 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <h3 class="text-xl font-bold text-slate-800 dark:text-white capitalize" x-text="monthName + ' ' + year"></h3>
                        <button type="button" aria-label="Mes siguiente" @click="nextMonth()" class="p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <svg class="w-6 h-6 text-slate-600 dark:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-7 gap-2 text-center mb-2">
                        <template x-for="day in ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']">
                            <div class="font-bold text-slate-500 dark:text-slate-400 text-sm" x-text="day"></div>
                        </template>
                    </div>

                    <div class="grid grid-cols-7 gap-2">
                        <!-- Blank days before 1st -->
                        <template x-for="blank in blankDays">
                            <div class="p-2"></div>
                        </template>
                        <!-- Days in month -->
                        <template x-for="date in noOfDays">
                            <div class="p-2 flex items-center justify-center rounded-xl aspect-square font-medium text-sm transition-all"
                                 :class="isVacation(date) ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/30 font-bold transform scale-105' : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700'"
                                 x-text="date"></div>
                        </template>
                    </div>
                </div>

                <!-- LISTA AGRUPADA -->
                <div class="bg-white dark:bg-slate-800 shadow-xl sm:rounded-3xl overflow-hidden border border-slate-200 dark:border-slate-700 p-6 sm:p-8">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-6">Tus Vacaciones Programadas</h3>
                    @if(empty($vacaciones_agrupadas))
                        <div class="text-center py-8 bg-slate-50 dark:bg-slate-900/30 rounded-2xl border border-slate-200 dark:border-slate-700/50">
                            <svg class="mx-auto h-12 w-12 text-slate-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <p class="text-slate-500 dark:text-slate-400 font-medium">No tienes vacaciones registradas.</p>
                        </div>
                    @else
                        <ul class="space-y-3">
                            @foreach($vacaciones_agrupadas as $rango)
                                <li class="flex items-center gap-3 p-4 bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-700/50">
                                    <div class="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                    <span class="font-bold text-slate-700 dark:text-slate-300">{{ $rango }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

            </div>

            <!-- TAB CONTENT: SOLICITAR -->
            <div x-show="tab === 'solicitar'" style="display: none;" x-transition>
                <div class="bg-white dark:bg-slate-800 shadow-xl sm:rounded-3xl overflow-hidden border border-slate-200 dark:border-slate-700 p-12 text-center">
                    <div class="w-20 h-20 bg-sky-100 dark:bg-sky-500/20 text-sky-500 dark:text-sky-400 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-800 dark:text-white mb-4">Próximamente</h3>
                    <p class="text-slate-600 dark:text-slate-400 max-w-md mx-auto">
                        La parte de solicitar vacaciones a través de la aplicación web aún no está disponible. Estamos trabajando en ello para futuras actualizaciones.
                    </p>
                </div>
            </div>

        </div>
    </div>

    <!-- AlpineJS Component for Calendar -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('vacacionesCalendar', (vacaciones) => ({
                month: new Date().getMonth(),
                year: new Date().getFullYear(),
                vacaciones: vacaciones || [],
                daysInMonth(month, year) {
                    return new Date(year, month + 1, 0).getDate();
                },
                get blankDays() {
                    let firstDay = new Date(this.year, this.month, 1).getDay();
                    firstDay = firstDay === 0 ? 6 : firstDay - 1; // Adjust for Monday start
                    let blanks = [];
                    for (let i = 0; i < firstDay; i++) {
                        blanks.push(i);
                    }
                    return blanks;
                },
                get noOfDays() {
                    let days = this.daysInMonth(this.month, this.year);
                    let daysArray = [];
                    for (let i = 1; i <= days; i++) {
                        daysArray.push(i);
                    }
                    return daysArray;
                },
                get monthName() {
                    const date = new Date(this.year, this.month, 1);
                    return date.toLocaleString('es-ES', { month: 'long' });
                },
                nextMonth() {
                    if (this.month === 11) {
                        this.month = 0;
                        this.year++;
                    } else {
                        this.month++;
                    }
                },
                prevMonth() {
                    if (this.month === 0) {
                        this.month = 11;
                        this.year--;
                    } else {
                        this.month--;
                    }
                },
                isVacation(day) {
                    const m = (this.month + 1).toString().padStart(2, '0');
                    const d = day.toString().padStart(2, '0');
                    const dateStr = `${this.year}-${m}-${d}`;
                    return this.vacaciones.includes(dateStr);
                }
            }));
        });
    </script>
</x-app-layout>
