<section aria-labelledby="contract-information-title" class="mt-8">
    <h3 id="contract-information-title" class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4">Información contractual</h3>
    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-5">
            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400">Fecha de inicio del contrato</dt>
            <dd class="mt-2 font-bold text-slate-800 dark:text-slate-100">
                @if($user->fecha_inicio)
                    <time datetime="{{ $user->fecha_inicio->format('Y-m-d') }}">{{ $user->fecha_inicio->format('d/m/Y') }}</time>
                @else
                    Sin fecha de inicio registrada
                @endif
            </dd>
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-5">
            <dt class="text-sm font-medium text-slate-600 dark:text-slate-400">Fecha de finalización del contrato</dt>
            <dd class="mt-2 font-bold text-slate-800 dark:text-slate-100">
                @if($user->fecha_fin_contrato)
                    <time datetime="{{ $user->fecha_fin_contrato->format('Y-m-d') }}">{{ $user->fecha_fin_contrato->format('d/m/Y') }}</time>
                @else
                    Sin fecha de finalización registrada
                @endif
            </dd>
        </div>
    </dl>
</section>
