<x-app-layout>
    <div class="employee-profile employee-profile--personal">
        <div class="employee-profile__card">
            <header class="employee-profile__identity">
                <div class="employee-profile__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->nombre ?? $user->name, 0, 1)) }}</div>
                <div class="employee-profile__identity-text">
                    <h1>{{ trim(($user->nombre ?? $user->name).' '.($user->apellidos ?? '')) }}</h1>
                    <p>{{ $user->email }}</p>
                    <span class="employee-profile__role">{{ $user->rol ?? 'Empleado' }}</span>
                </div>
            </header>

            <nav class="employee-profile__menu" aria-label="Opciones del perfil">
                @foreach([
                    ['profile.datos', 'Datos personales y de contacto'],
                    ['profile.vacaciones', 'Vacaciones'],
                    ['profile.disponibilidad', 'Mis solicitudes'],
                    ['profile.notificaciones', 'Notificaciones'],
                    ['profile.seguridad', 'Seguridad y contraseña'],
                ] as [$profileRoute, $profileLabel])
                    @if($user->isEmployee() || !in_array($profileRoute, ['profile.vacaciones', 'profile.disponibilidad']))
                    <a href="{{ route($profileRoute) }}" class="employee-profile__menu-link">
                        <span>{{ $profileLabel }}</span>
                        <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" /></svg>
                    </a>
                    @endif
                @endforeach

                @if($user->es_admin)
                    <a href="{{ route('admin.solicitudes') }}" class="employee-profile__menu-link">
                        <span>Solicitudes de empleados</span>
                        <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" /></svg>
                    </a>
                @endif

                <button type="button" disabled class="employee-profile__menu-link">
                    <span>Documentos</span>
                    <span class="text-sm font-normal">Próximamente</span>
                </button>

                <form method="POST" action="{{ route('logout') }}" class="pt-2">
                    @csrf
                    <button type="submit" class="employee-profile__menu-link employee-profile__menu-link--logout">
                        <span>Cerrar sesión</span>
                        <svg aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m17 16 4-4-4-4M21 12H7m6 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1" /></svg>
                    </button>
                </form>
            </nav>
        </div>
    </div>
</x-app-layout>
