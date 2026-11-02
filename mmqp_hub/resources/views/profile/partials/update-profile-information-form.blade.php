<section aria-labelledby="personal-information-title">
    <h2 id="personal-information-title" class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-5">{{ $user->isEmployee() ? 'Datos personales y de contacto' : 'Información de la cuenta' }}</h2>
    <dl class="employee-profile__details">
        @foreach([
            'Nombre' => $user->nombre,
            'Apellidos' => $user->apellidos,
            'DNI' => $user->dni,
            'Teléfono' => $user->telefono,
            'Correo electrónico' => $user->email,
        ] as $label => $value)
            <div>
                <dt>{{ $label }}</dt>
                <dd>{{ filled($value) ? $value : 'Sin registrar' }}</dd>
            </div>
        @endforeach
    </dl>
</section>

@if($user->isEmployee())
<section aria-labelledby="employment-information-title" class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700">
    <h2 id="employment-information-title" class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-5">Información laboral</h2>
    <dl class="employee-profile__details">
        <div>
            <dt>Rol</dt>
            <dd class="capitalize">{{ $user->rol ?? 'Empleado' }}</dd>
        </div>
        <div>
            <dt>Jornada de contrato</dt>
            <dd>{{ $user->horas_mensuales ?? 0 }} h / mes</dd>
        </div>
    </dl>
    @include('profile.partials.contract-information')
</section>

@endif
