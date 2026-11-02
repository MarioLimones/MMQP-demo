<!-- Regla para que x-cloak funcione (la puedes mover a tu app.css si prefieres) -->
<style>
    [x-cloak] { display: none !important; }
</style>

<div id="pwa-install-container" 
     class="fixed bottom-24 sm:bottom-6 right-6 z-50" 
     x-data="pwaInstaller()" 
     x-show="showInstaller" 
     x-cloak
     x-transition:enter="transition ease-out duration-300" 
     x-transition:enter-start="opacity-0 translate-y-4" 
     x-transition:enter-end="opacity-100 translate-y-0" 
     x-transition:leave="transition ease-in duration-200" 
     x-transition:leave-start="opacity-100 translate-y-0" 
     x-transition:leave-end="opacity-0 translate-y-4">
    
    <!-- Botón Flotante -->
    <button @click="installPwa()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-5 sm:px-6 rounded-full shadow-2xl shadow-red-900/50 flex items-center gap-2 sm:gap-3 transition-transform hover:scale-105 border-2 border-red-400/30">
        <svg class="w-6 h-6 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
        <span class="text-sm sm:text-base">Instalar App</span>
    </button>

    <!-- Modal para iOS -->
    <div x-show="showIosModal" class="fixed inset-0 bg-slate-900/80 z-[100] flex items-end sm:items-center justify-center backdrop-blur-sm print:hidden" @click.self="showIosModal = false" x-transition.opacity>
        <div class="bg-white dark:bg-slate-800 w-full sm:w-96 p-6 rounded-t-3xl sm:rounded-3xl shadow-2xl pb-10 sm:pb-6 relative"
             x-show="showIosModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full sm:translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full sm:translate-y-4 opacity-0">
            <button @click="showIosModal = false" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-red-100 dark:bg-red-900/50 rounded-2xl flex items-center justify-center shrink-0">
                    <img src="{{ asset('icon-192.png') }}" alt="Icon" class="w-8 h-8 rounded-lg">
                </div>
                <div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white leading-tight">Instalar en iOS</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Añade MMQP HUB a tu inicio</p>
                </div>
            </div>
            
            <p class="text-sm text-slate-600 dark:text-slate-300 mb-5">Por motivos de seguridad, Apple requiere que lo añadas manualmente:</p>
            <ol class="space-y-4 text-sm text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-900/50 p-4 rounded-2xl border border-slate-100 dark:border-slate-700">
                <li class="flex gap-3 items-center">
                    <span class="bg-red-600 text-white font-bold w-6 h-6 flex items-center justify-center rounded-full shrink-0 text-xs shadow-md">1</span>
                    <span class="leading-tight">Toca el botón <strong>Compartir</strong> en la barra inferior <br><span class="text-xs text-slate-500 dark:text-slate-400 block mt-1">(el cuadrado con la flecha hacia arriba)</span></span>
                </li>
                <li class="flex gap-3 items-center">
                    <span class="bg-red-600 text-white font-bold w-6 h-6 flex items-center justify-center rounded-full shrink-0 text-xs shadow-md">2</span>
                    <span class="leading-tight">Desliza hacia abajo y selecciona <strong>Añadir a la pantalla de inicio</strong>.</span>
                </li>
            </ol>
            <button @click="showIosModal = false" class="w-full mt-6 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-800 dark:text-white font-bold py-3 px-4 rounded-xl transition-colors">
                Entendido
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener("alpine:init", () => {
        Alpine.data("pwaInstaller", () => ({
            showInstaller: false,
            showIosModal: false,
            deferredPrompt: null,
            
            init() {
                if (window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true) {
                    return; 
                }

                const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

                if (isIos) {
                    this.showInstaller = true;
                } else {
                    if (window.deferredPwaPrompt) {
                        this.deferredPrompt = window.deferredPwaPrompt;
                        this.showInstaller = true;
                    }

                    // Por si salta más tarde (fallback de Chrome)
                    window.addEventListener("beforeinstallprompt", (e) => {
                        this.deferredPrompt = e;
                        this.showInstaller = true;
                    });

                    // Por si saltó antes de Alpine pero queremos asegurarnos
                    window.addEventListener("pwa-ready", () => {
                        if (window.deferredPwaPrompt) {
                            this.deferredPrompt = window.deferredPwaPrompt;
                            this.showInstaller = true;
                        }
                    });
                }
            },

            async installPwa() {
                if (this.deferredPrompt) {
                    this.deferredPrompt.prompt();
                    const { outcome } = await this.deferredPrompt.userChoice;
                    if (outcome === "accepted") {
                        this.showInstaller = false;
                    }
                    this.deferredPrompt = null;
                } else {
                    this.showIosModal = true;
                }
            }
        }));
    });
</script>