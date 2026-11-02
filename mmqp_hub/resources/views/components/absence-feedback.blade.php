@if ($errors->any())
    <div role="alert" tabindex="-1" class="mb-6 rounded-xl border border-rose-400 bg-rose-50 p-4 text-rose-800 dark:bg-slate-800 dark:text-rose-300">
        <p class="font-bold">Revisa los datos antes de guardar:</p>
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
@php $absenceConflicts = $conflicts ?? session('absence_conflicts', []); @endphp
@if ($absenceConflicts)
    <div role="status" class="mb-6 rounded-xl border border-amber-400 bg-amber-50 p-4 text-amber-900 dark:bg-slate-800 dark:text-amber-200">
        <p class="font-bold">Hay turnos publicados que coinciden con la disponibilidad actual. Revisa sus asignaciones antes de volver a publicar.</p>
        <ul class="list-disc pl-5">
            @foreach ($absenceConflicts as $conflict)
                <li><a class="underline" href="{{ route('admin.horarios.resultado', $conflict['horario_id']) }}">Horario {{ $conflict['horario_id'] }}</a>: {{ $conflict['fecha'] }}</li>
            @endforeach
        </ul>
    </div>
@endif
