<div class="{{ $compact ? 'navigation-settings-topbar' : 'navigation-settings-footer p-4 border-t border-gray-200 dark:border-slate-700 shrink-0' }}">
    <button type="button" @click="toggleTheme()"
        :aria-label="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        :title="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
        class="navigation-theme-toggle w-full flex items-center justify-between px-4 py-3 rounded-xl text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors mb-2">
        <span class="navigation-theme-label font-medium text-sm hidden dark:block">Modo Claro</span>
        <span class="navigation-theme-label font-medium text-sm block dark:hidden">Modo Oscuro</span>
        <svg class="w-5 h-5 hidden dark:block" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
        <svg class="w-5 h-5 block dark:hidden" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
    </button>

    <div x-data="{ userMenu: false }" class="navigation-account relative"
        @click.outside="userMenu = false"
        @keydown.escape.stop="if (userMenu) { userMenu = false; $refs.accountButton.focus(); }">
        @php $accountLabelId = $compact ? 'navigation-account-compact' : 'navigation-account-sidebar'; @endphp
        <button type="button" x-ref="accountButton" @click="userMenu = !userMenu" aria-expanded="false" :aria-expanded="userMenu"
            aria-labelledby="{{ $accountLabelId }}-prefix {{ $accountLabelId }}-name {{ $accountLabelId }}-role"
            class="navigation-account-toggle w-full flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50 dark:bg-slate-700/30 text-left hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors">
            <span id="{{ $accountLabelId }}-prefix" class="sr-only">Cuenta de </span>
            <span aria-hidden="true" data-initial="{{ substr(Auth::user()->nombre ?? Auth::user()->name, 0, 1) }}" class="navigation-account-avatar h-8 w-8 rounded-full bg-red-500 text-white flex items-center justify-center font-bold flex-shrink-0"></span>
            <span class="navigation-account-details flex-1 min-w-0">
                <span id="{{ $accountLabelId }}-name" class="navigation-account-name block text-sm font-medium text-slate-900 dark:text-white truncate">
                    {{ Auth::user()->nombre ?? Auth::user()->name }}
                </span>
                <span id="{{ $accountLabelId }}-role" class="navigation-account-role block text-xs text-slate-500 dark:text-slate-400 truncate">
                    {{ Auth::user()->rol ?? 'Usuario' }}
                </span>
            </span>
            <svg class="navigation-account-chevron w-4 h-4 text-slate-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </button>

        <div x-show="userMenu" x-cloak x-transition
            class="navigation-account-menu absolute bottom-full left-0 w-full mb-2 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-100 dark:border-slate-700 overflow-hidden z-50">
            <a href="{{ route('profile.edit') }}" class="block px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">Mi Perfil</a>
            @if(auth()->user()->isEmployee())
            <a href="{{ route('profile.disponibilidad') }}" class="block px-4 py-2.5 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">Mis Días Libres</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors border-t border-gray-100 dark:border-slate-700">Cerrar Sesión</button>
            </form>
        </div>
    </div>
</div>
