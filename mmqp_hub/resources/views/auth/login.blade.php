<x-guest-layout>
    <section aria-labelledby="demo-access-title" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
        <h1 id="demo-access-title" class="font-bold">Prueba MMQP con cuentas ficticias</h1>
        <p class="mt-2">Introduce una de estas cuentas:</p>
        <ul class="mt-2 space-y-1 break-all">
            <li><strong>Administración:</strong> admin@mmqp.example</li>
            <li><strong>Oficina:</strong> oficina@mmqp.example</li>
            <li><strong>Empleado:</strong> empleado@mmqp.example</li>
        </ul>
        <p class="mt-3">Contraseña de todas: <code>DemoMMQP!2026</code></p>
        <p class="mt-2">Los cambios se comparten entre visitantes. Usa solo datos de prueba.</p>
    </section>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo Electrónico" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" value="Contraseña" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 dark:bg-slate-900 text-red-600 shadow-sm focus:ring-red-500 dark:focus:ring-red-600 dark:focus:ring-offset-gray-800" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">Recordar sesión</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:focus:ring-offset-gray-800" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif

            <x-primary-button class="ms-3">
                Iniciar Sesión
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
