<x-app-layout>
    <div class="employee-profile employee-profile--personal">
        <header class="employee-profile__heading">
            <div>
                <h1>Datos del empleado</h1>
                <p>Para actualizar los datos de tu ficha, contacta con el administrador.</p>
            </div>
            <a href="{{ route('profile.edit') }}" class="employee-profile__back">
                <svg aria-hidden="true" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6" /></svg>
                Mi perfil
            </a>
        </header>
        <div class="employee-profile__card employee-profile__section-body">
            @include('profile.partials.update-profile-information-form')
        </div>
    </div>
</x-app-layout>
