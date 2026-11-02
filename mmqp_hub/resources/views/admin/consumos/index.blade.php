@php
    $historyActive = request('section') === 'historial'
        || (request('section') !== 'preparar' && (request()->has('month') || request()->has('day')));
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="arqueo-heading consumos-heading"><div><h2 class="font-bold text-2xl text-slate-800 dark:text-white">Consumos</h2></div>
            <button type="button" id="consumos-workflow-back" class="arqueo-button arqueo-button-back print-hidden" hidden><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5m6-6-6 6 6 6"/></svg>Volver a Consumos</button>
        </div>
    </x-slot>
    <div class="arqueo-workspace consumos-workspace" id="consumos-app" data-consumos-mode="entry">
        <nav id="consumos-section-nav" class="consumos-section-nav print-hidden" aria-label="Secciones de Consumos">
            <a id="consumos-prepare-link" href="{{ route('admin.consumos') }}" aria-controls="consumos-prepare-panel" @unless($historyActive) aria-current="page" @endunless>
                <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-4"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="m8 13 2 2 6-6"/></svg>
                <span>Preparar consumo</span>
            </a>
            <a id="consumos-history-link" href="{{ route('admin.consumos', ['section' => 'historial']) }}" aria-controls="consumos-history-panel" @if($historyActive) aria-current="page" @endif>
                <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18m-14 4h2m4 0h2m-8 3h2"/></svg>
                <span>Historial de consumos</span>
            </a>
        </nav>
        <section id="consumos-prepare-panel" aria-label="Preparar consumo" @if($historyActive) hidden @endif>
        <section id="consumos-workflow-entry" class="consumos-entry print-hidden" aria-labelledby="consumos-entry-title">
            <h3 id="consumos-entry-title" class="sr-only">¿Qué necesitas hacer?</h3>
            <div class="consumos-entry-options">
                <button type="button" id="btn-edit-mode" class="consumos-entry-tile consumos-entry-tile-edit" aria-pressed="false">
                    <div class="consumos-tile-icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>
                    </div>
                    <span class="consumos-tile-title" data-edit-label>Editar documento</span>
                </button>

                <button type="button" id="consumos-import-button" class="consumos-entry-tile consumos-entry-tile-import">
                    <div class="consumos-tile-preview" aria-hidden="true">
                        <div class="consumos-tile-preview-paper">
                            <div class="consumos-tile-preview-head"></div>
                            <div class="consumos-tile-preview-lines">
                                <div></div><div></div><div></div><div></div><div></div><div></div>
                            </div>
                        </div>
                    </div>
                    <div class="consumos-tile-cta">
                        <div class="consumos-tile-icon consumos-tile-icon-primary" aria-hidden="true">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m-4-4 4 4 4-4M5 15v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/></svg>
                        </div>
                        <span class="consumos-tile-title">Cargar datos de consumo</span>
                    </div>
                </button>
            </div>
            <p id="consumos-workflow-error" class="consumos-error" role="alert" hidden></p>
            <noscript><p>Activa JavaScript para preparar el documento e importar los consumos.</p></noscript>
        </section>
        <section id="consumos-workflow-linking" class="consumos-linking print-hidden" aria-labelledby="consumos-linking-count" hidden>
            <ol class="consumos-task-steps" aria-label="Pasos para preparar el consumo"><li class="is-complete"><span aria-hidden="true">✓</span> Cargar informe</li><li aria-current="step"><span aria-hidden="true">2</span> Revisar productos</li><li><span aria-hidden="true">3</span> Completar ficha</li></ol>
            <div class="consumos-stage-heading"><div><p class="consumos-stage-eyebrow">Paso 2 de 3 · Revisar productos</p><h3 id="consumos-linking-count" tabindex="-1">Revisa los productos</h3></div><button type="button" id="consumos-linking-back" class="arqueo-button arqueo-button-back"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5m6-6-6 6 6 6"/></svg>Volver a Consumos</button></div>
            <p class="consumos-stage-description">Elige cada producto y busca el que le corresponde en el PDF. Si no aparece, marca «Dejar vacío en esta hoja».</p>
            <div id="consumos-linking-draft-host"></div>
            <div class="consumos-linking-summary"><div><p id="consumos-linking-progress" role="status" aria-live="polite" aria-atomic="true"></p><progress id="consumos-linking-meter" value="0" max="1" aria-label="Productos revisados"></progress></div><span>Confirma cada producto para evitar confundir nombres parecidos.</span></div>
            <div id="consumos-linking-list" class="consumos-linking-list" role="group" aria-label="Productos de tus hojas para revisar"></div>
            <p id="consumos-linking-error" class="consumos-error" role="alert" hidden></p>
            <div class="consumos-linking-footer"><p>Cuando hayas revisado todos los productos, continúa para completar el stock y el pedido.</p><button type="button" id="consumos-linking-save" class="arqueo-button arqueo-button-primary">Crear ficha de consumo</button></div>
        </section>
        <section id="consumos-workflow-workspace" aria-labelledby="consumos-workflow-title" hidden>
        <ol class="consumos-task-steps consumos-sheet-guide print-hidden" aria-label="Pasos para preparar el consumo"><li class="is-complete"><span aria-hidden="true">✓</span> Cargar informe</li><li class="is-complete"><span aria-hidden="true">✓</span> Revisar productos</li><li aria-current="step"><span aria-hidden="true">3</span> Completar ficha</li></ol>
        <div class="consumos-stage-heading consumos-editor-heading print-hidden"><div id="consumos-editor-intro"><h3 id="consumos-workflow-title" tabindex="-1">Editar documento</h3><p id="consumos-workflow-description" class="consumos-stage-description">Selecciona dónde quieres poner una tabla y utiliza <strong>Crear tabla de proveedor</strong>. Sus filas quedarán identificadas como productos. Puedes añadir hojas y cambiar textos, bordes y tamaños.</p></div></div>
        <div id="consumos-workspace-draft-host">
        <div id="consumos-draft-banner" class="arqueo-panel consumos-draft-banner print-hidden" hidden role="status">
            <div class="consumos-draft-heading"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4m0 4h.01"/></svg><strong>Borrador pendiente · Elige cómo continuar</strong></div>
            <p id="consumos-draft-message"></p>
            <button type="button" id="consumos-draft-restore" class="arqueo-button">Recuperar borrador</button>
            <button type="button" id="consumos-draft-discard" class="arqueo-button">Descartar borrador local</button>
        </div>
        <p id="consumos-draft-status" class="print-hidden consumos-search-help" role="status" aria-live="polite"></p>
        </div>
        <div class="consumos-sheet-key consumos-sheet-guide print-hidden" aria-label="Cómo completar la ficha"><p><strong>Consumo</strong><span>Viene del informe</span></p><p><strong>Stock</strong><span>Escribe lo que tienes</span></p><p><strong>Pedido</strong><span>Escribe lo que necesitas pedir</span></p></div>
        <div class="consumos-file-bar print-hidden">
            <input type="hidden" id="consumos-title" value="Ficha de consumos">
            <label for="consumos-sheet-select" class="consumos-history-control">Fichas guardadas <select id="consumos-sheet-select"><option value="">Nueva ficha</option></select></label>
            <label for="consumos-report-select" hidden>PDF <select id="consumos-report-select"><option value="">Sin informe</option></select></label>
            <div id="consumos-sheet-actions" class="consumos-sheet-actions"><button type="button" id="consumos-print-sheet" onclick="printArqueo()" class="arqueo-button">Imprimir A4</button><button type="button" id="consumos-save-sheet" onclick="saveTemplate()" class="arqueo-button arqueo-button-primary">Guardar ficha</button></div>
        </div>
        <div class="consumos-document-controls arqueo-panel print-hidden">
        <div class="consumos-page-bar" role="group" aria-label="Hojas del documento">
            <label for="consumos-page-select">Hoja <select id="consumos-page-select"><option value="">Hoja 1</option></select></label>
            <button type="button" id="consumos-page-add" class="arqueo-button consumos-page-edit-control">+ Añadir hoja</button>
            <label for="consumos-page-title" class="consumos-page-edit-control">Nombre de la hoja <input id="consumos-page-title" type="text" maxlength="150" value="Hoja 1"></label>
            <div class="consumos-page-actions consumos-page-edit-control"><button type="button" id="consumos-page-up" class="arqueo-button">Subir hoja</button><button type="button" id="consumos-page-down" class="arqueo-button">Bajar hoja</button><button type="button" id="consumos-page-delete" class="arqueo-button arqueo-button-danger">Eliminar hoja</button><button type="button" onclick="clearGrid()" class="arqueo-button arqueo-button-danger">Vaciar hoja completa</button></div>
            <div class="consumos-template-actions consumos-page-edit-control" role="group" aria-label="Copias de la plantilla">
                <button type="button" id="consumos-template-import" class="arqueo-button">Importar plantilla</button>
                <button type="button" id="consumos-template-export" class="arqueo-button">Exportar plantilla</button>
                <button type="button" id="consumos-template-undo" class="arqueo-button" hidden>Deshacer importación</button>
                <input type="file" id="consumos-template-file" accept="application/json,.json" hidden aria-label="Archivo de plantilla de Consumos">
            </div>
                <div id="edit-toolbar" class="hidden" role="region" aria-label="Herramientas de diseño">
                    <p id="selection-label" class="sr-only" aria-live="polite">Selecciona una casilla de la hoja.</p>
                    <details class="consumos-tools-menu consumos-control-menu"><summary class="arqueo-button">Herramientas<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 9 6 6 6-6"/></svg></summary>
                    <div class="arqueo-tool-groups consumos-tools-content consumos-control-content">
                        <fieldset class="consumos-main-tools"><legend>Crear y añadir</legend><button type="button" onclick="consumosCellType('side-provider')" class="arqueo-button">Crear tabla de proveedor</button><button type="button" onclick="consumosCellType('order-table')" class="arqueo-button">Crear tabla de pedidos</button><button type="button" onclick="consumosCellType('product')" class="arqueo-button" title="Selecciona las casillas debajo de un proveedor para añadir filas de producto">Añadir filas de producto</button></fieldset>
                        <fieldset><legend>Casillas</legend><button type="button" onclick="cmd('merge')" class="arqueo-button" title="Crea un espacio más ancho para un título o un dato">Unir casillas</button><button type="button" onclick="cmd('unmerge')" class="arqueo-button">Separar</button><button type="button" onclick="cmd('clear-content')" class="arqueo-button">Borrar texto</button></fieldset>
                        <fieldset><legend>Bordes</legend><button type="button" onclick="cmd('b-all')" class="arqueo-button">Todos</button><button type="button" onclick="cmd('b-outer')" class="arqueo-button">Contorno</button><button type="button" onclick="cmd('b-none')" class="arqueo-button">Sin bordes</button>
                            <select aria-label="Añadir o quitar un borde" onchange="if(this.value) cmd(this.value); this.value=''"><option value="">Un solo borde…</option><option value="b-top">Superior</option><option value="b-bottom">Inferior</option><option value="b-left">Izquierdo</option><option value="b-right">Derecho</option></select>
                        </fieldset>
                        <fieldset><legend>Texto</legend><button type="button" onclick="cmd('font-bold')" class="arqueo-button">Negrita</button><button type="button" onclick="cmd('font-down')" class="arqueo-button" aria-label="Reducir tamaño del texto">A−</button><button type="button" onclick="cmd('font-up')" class="arqueo-button" aria-label="Aumentar tamaño del texto">A+</button>
                            <select aria-label="Alineación del texto" onchange="if(this.value) cmd(this.value); this.value=''"><option value="">Alinear…</option><option value="align-left">Izquierda</option><option value="align-center">Centro</option><option value="align-right">Derecha</option></select>
                            <button type="button" id="consumos-fit-text" class="arqueo-button">Ajustar texto a las casillas</button>
                        </fieldset>
                        @include('admin.arqueo.color-picker')
                    </div></details>
                </div>
        </div>
        </div>
        <div class="arqueo-editor-layout">
            <div class="arqueo-editor-main">
                <p id="sheet-overflow-warning" class="arqueo-overflow-warning print-hidden" hidden role="status"></p>
                <p id="editor-status" class="print-hidden" role="status" aria-live="polite" aria-atomic="true"></p>
                <div class="arqueo-paper-heading print-hidden"><span>Hoja A4 · Vertical</span><span id="consumos-coverage">Sin PDF importado</span><div class="consumos-paper-actions" role="group" aria-label="Historial y guardado"><button type="button" id="btn-undo" class="arqueo-button consumos-history-button" aria-label="Deshacer" title="Deshacer (Ctrl+Z)" disabled><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4 4 9l5 5M4 9h10a6 6 0 0 1 0 12"/></svg></button><button type="button" id="btn-redo" class="arqueo-button consumos-history-button" aria-label="Rehacer" title="Rehacer (Ctrl+Y)" disabled><svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 4 5 5-5 5m5-5H10a6 6 0 0 0 0 12"/></svg></button><button type="button" id="btn-save-template" onclick="saveTemplate()" class="arqueo-button arqueo-button-primary"><span>Guardar hoja</span></button></div></div>
                <p class="consumos-mobile-sheet-help print-hidden">Desliza la hoja hacia los lados para ver todas las columnas.</p>
                <div class="arqueo-sheet-scroll" tabindex="0" role="region" aria-label="Hoja de consumos. Desplázate horizontalmente para verla completa en pantallas pequeñas.">
                    <div id="arqueo-print-area" class="hoja-arqueo">
                        <div class="arqueo-column-labels print-hidden" aria-hidden="true">@for($i=0; $i<24; $i++)<span>{{ chr(65 + $i) }}</span>@endfor</div>
                        <div id="sheet-row-labels" class="arqueo-row-labels print-hidden" aria-hidden="true">@for($i=1; $i<=73; $i++)<span>{{ $i }}</span>@endfor</div>
                        <table id="arqueo-grid" aria-label="Casillas de la ficha de consumos"><colgroup>@for($i=0; $i<24; $i++)<col style="width: {{ 100 / 24 }}%;">@endfor</colgroup><tbody></tbody></table>
                    </div>
                </div>
                <details class="consumos-review arqueo-panel print-hidden"><summary>Ver productos del informe <span id="consumos-row-count"></span></summary><label for="consumos-source-search">Buscar <input id="consumos-source-search" type="search" placeholder="Nombre del producto"></label><div id="consumos-source-list"></div></details>
            </div>
        </div>
        </section>
        <div id="consumos-print-pages"></div>
        </section>
        <section id="consumos-history-panel" aria-label="Historial de consumos" @unless($historyActive) hidden @endunless>
            @include('admin.consumos.history-calendar', ['history' => $history])
        </section>
    </div>
    <div id="context-menu" class="consumos-context-menu" role="menu" aria-label="Opciones de Consumos">
        <div class="arqueo-context-heading">
            <div class="arqueo-context-summary"><span>Opciones de Consumos</span></div>
            <button type="button" id="btn-close-context" aria-label="Cerrar opciones">×</button>
        </div>
        <div class="arqueo-context-actions">
            <button type="button" role="menuitem" onclick="consumosCellType('provider')">Proveedor arriba</button>
            <button type="button" role="menuitem" onclick="consumosCellType('product')">Producto y consumo</button>
            <button type="button" role="menuitem" onclick="consumosCellType('side-provider')">Proveedor con productos a la derecha…</button>
            <button type="button" role="menuitem" onclick="consumosCellType('order-table')">Tabla de pedidos y recepción…</button>
            <button type="button" role="menuitem" id="consumos-custom-block">Bloque con columnas y productos…</button>
            <button type="button" role="menuitem" id="consumos-fill-names">Rellenar nombres del proveedor…</button>
            <button type="button" role="menuitem" onclick="consumosCellType('free')">Convertir en casillas normales</button>
        </div>
    </div>
    <div class="arqueo-workspace consumos-popup-host">
        <dialog id="consumos-template-dialog" class="consumos-dialog" aria-labelledby="consumos-template-title" aria-describedby="consumos-template-description">
            <h3 id="consumos-template-title">Importar plantilla de Consumos</h3>
            <p id="consumos-template-summary"></p>
            <p id="consumos-template-description">Sustituirá únicamente la hoja seleccionada. Las demás hojas se conservan. Se importarán los nombres, tablas y formato, con las cantidades y los vínculos vacíos.</p>
            <p>Podrás revisar el resultado y deshacer la importación antes de pulsar Guardar hoja.</p>
            <div class="consumos-popup-actions"><button type="button" id="consumos-template-cancel" class="arqueo-button">Cancelar</button><button type="button" id="consumos-template-apply" class="arqueo-button arqueo-button-primary">Importar plantilla</button></div>
        </dialog>
        <dialog id="consumos-custom-dialog" class="consumos-dialog" aria-labelledby="consumos-custom-title">
            <form id="consumos-custom-form">
                <h3 id="consumos-custom-title">Bloque con columnas y productos</h3>
                <label for="consumos-custom-name">Proveedor o título (opcional en embalajes)</label><input id="consumos-custom-name" maxlength="255">
                <label for="consumos-custom-columns">Columnas</label><select id="consumos-custom-columns"><option value="standard">Producto · Consumo · Stock · Pedido</option><option value="stock">Producto · Consumo · Stock</option><option value="checks">Producto · Consumo · Stock · Pedido · M · V</option><option value="quantity">Cantidad impresa · Producto · Consumo · Stock · Pedido</option><option value="quantity-checks">Cantidad impresa · Producto · Consumo · Stock · Pedido · M · V</option><option value="packaging">Producto · Stock · Pedido (sin consumo)</option></select>
                <div class="consumos-form-grid"><label for="consumos-custom-height">Altura de productos<select id="consumos-custom-height"><option value="1">Compacta · 1 fila</option><option value="2" selected>Normal · 2 filas</option><option value="3">Amplia · 3 filas</option><option value="4">4 filas</option></select></label><label for="consumos-custom-header-height">Altura del título<select id="consumos-custom-header-height"><option value="1">1 fila</option><option value="2" selected>2 filas</option><option value="3">3 filas</option><option value="4">4 filas</option></select></label></div>
                <label for="consumos-custom-widths">Anchos de columnas (opcional)</label><input id="consumos-custom-widths" placeholder="Ej.: 5,3,2,2" aria-describedby="consumos-custom-help">
                <label for="consumos-custom-names">Productos y categorías (uno por línea)</label><textarea id="consumos-custom-names" rows="8" required></textarea>
                <p id="consumos-custom-help">Escribe # antes de una categoría. Para cantidad impresa, escribe 2 | Producto. Las filas quedarán identificadas como productos para importar sus consumos después. El ancho será el rango seleccionado.</p>
                <p id="consumos-custom-error" class="consumos-error" role="alert" hidden></p>
                <div class="consumos-popup-actions"><button type="button" id="consumos-custom-cancel" class="arqueo-button">Cancelar</button><button type="submit" class="arqueo-button arqueo-button-primary">Crear bloque</button></div>
            </form>
        </dialog>
        <dialog id="consumos-names-dialog" class="consumos-dialog" aria-labelledby="consumos-names-title">
            <form id="consumos-names-form"><h3 id="consumos-names-title">Rellenar nombres del proveedor</h3><p>Un nombre por línea, en el orden de las filas. Si cambias un producto, revisaremos su vínculo en la próxima importación.</p><label for="consumos-names-list">Nombres de productos</label><textarea id="consumos-names-list" rows="10" required></textarea><p id="consumos-names-error" class="consumos-error" role="alert" hidden></p><div class="consumos-popup-actions"><button type="button" id="consumos-names-cancel" class="arqueo-button">Cancelar</button><button type="submit" class="arqueo-button arqueo-button-primary">Aplicar nombres</button></div></form>
        </dialog>
        <dialog id="consumos-side-dialog" class="consumos-dialog consumos-side-dialog" aria-labelledby="consumos-side-title" aria-describedby="consumos-side-help">
            <form id="consumos-side-form">
                <h3 id="consumos-side-title">Proveedor con productos</h3>
                <p id="consumos-side-help">Escribe el proveedor y sus productos. Verás la tabla en tu hoja antes de guardarla.</p>
                <label for="consumos-side-name">Nombre del proveedor</label><input id="consumos-side-name" type="text" maxlength="255" placeholder="Ej.: Cerveza o Pascual" required>
                <label for="consumos-side-count">Número de productos</label><input id="consumos-side-count" type="number" min="1" max="36" step="1" value="5" required aria-describedby="consumos-side-preview-count">
                <label for="consumos-side-height">Altura de productos</label><select id="consumos-side-height"><option value="1">Compacta · 1 fila</option><option value="2" selected>Normal · 2 filas</option><option value="3">Amplia · 3 filas</option><option value="4">4 filas</option></select>
                <label for="consumos-side-headers">Cabecera de columnas</label><select id="consumos-side-headers"><option value="1">Mostrar Producto / Consumo / Stock / Pedido</option><option value="0">Continuar sin repetir cabecera</option></select>
                <label for="consumos-side-names">Nombres de productos (opcional, uno por línea)</label><textarea id="consumos-side-names" rows="5"></textarea>
                <div class="consumos-side-example" aria-hidden="true"><div id="consumos-side-preview-name">Proveedor</div><table><thead><tr><th>Producto</th><th>Consumo</th><th>Stock</th><th>Pedido</th></tr></thead><tbody><tr><td></td><td></td><td></td><td></td></tr><tr><td></td><td></td><td></td><td></td></tr></tbody></table></div>
                <p id="consumos-side-preview-count" role="status">5 filas de producto</p>
                <p id="consumos-side-error" class="consumos-error" role="alert" hidden></p>
                <div class="consumos-popup-actions"><button type="button" id="consumos-side-cancel" class="arqueo-button">Cancelar</button><button type="submit" id="consumos-side-submit" class="arqueo-button arqueo-button-primary">Crear bloque</button></div>
            </form>
        </dialog>
        <dialog id="consumos-order-dialog" class="consumos-dialog" aria-labelledby="consumos-order-title" aria-describedby="consumos-order-help">
            <form id="consumos-order-form">
                <h3 id="consumos-order-title">Pedidos y recepción</h3>
                <p id="consumos-order-help">Organiza qué vas a pedir a cada proveedor y cuándo lo recibirás.</p>
                <label for="consumos-order-names">Proveedores (uno por línea)</label><textarea id="consumos-order-names" rows="5" required aria-describedby="consumos-order-count" placeholder="Proveedor 1&#10;Proveedor 2&#10;Proveedor 3"></textarea>
                <label for="consumos-order-request">Texto inicial en Qué pedir</label><input id="consumos-order-request" maxlength="255" type="text" placeholder="Opcional" value="">
                <div class="consumos-form-grid"><label for="consumos-order-height">Altura de proveedores<select id="consumos-order-height"><option value="1">Compacta · 1 fila</option><option value="2" selected>Normal · 2 filas</option><option value="3">3 filas</option><option value="4">4 filas</option></select></label><label for="consumos-order-header-height">Altura de cabecera<select id="consumos-order-header-height"><option value="1">1 fila</option><option value="2" selected>2 filas</option><option value="3">3 filas</option><option value="4">4 filas</option></select></label></div>
                <p id="consumos-order-count" role="status"></p>
                <p>Podrás cambiar todos los textos después de crear la tabla.</p>
                <p id="consumos-order-error" class="consumos-error" role="alert" hidden></p>
                <div class="consumos-popup-actions"><button type="button" id="consumos-order-cancel" class="arqueo-button">Cancelar</button><button type="submit" class="arqueo-button arqueo-button-primary">Crear tabla</button></div>
            </form>
        </dialog>
        <dialog id="consumos-import-dialog" class="consumos-dialog" aria-labelledby="consumos-import-title" aria-describedby="consumos-import-help">
            <form id="consumos-import-form">
                <div class="consumos-import-header">
                    <h3 id="consumos-import-title">Importar datos de consumo</h3>
                    <button type="button" id="consumos-cancel-import" class="consumos-dialog-close" aria-label="Cancelar y cerrar">×</button>
                </div>
                <p id="consumos-import-help" class="sr-only">Elige tus hojas y carga el informe. Después revisaremos los productos, uno a uno.</p>
                <section id="consumos-resume-import-panel" class="consumos-resume-import-panel" aria-labelledby="consumos-resume-import-title" hidden>
                    <h4 id="consumos-resume-import-title">Consumo pendiente</h4>
                    <p id="consumos-resume-import-message">Tienes un informe con productos pendientes de revisar.</p>
                    <div class="consumos-resume-import-actions"><button type="button" id="consumos-resume-import" class="arqueo-button arqueo-button-primary">Continuar revisión pendiente</button><button type="button" id="consumos-resume-discard" class="arqueo-button">Descartar aviso</button></div>
                </section>
                <section class="consumos-import-option consumos-upload-option" aria-label="Cargar un nuevo informe">
                    <label for="consumos-pdf" class="consumos-pdf-dropzone">
                        <span class="consumos-pdf-dropzone-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0-4 4m4-4 4 4M4 16.5v1.25A2.25 2.25 0 0 0 6.25 20h11.5A2.25 2.25 0 0 0 20 17.75V16.5" /></svg>
                        </span>
                        <strong id="consumos-pdf-label">Cargar PDF de consumo</strong>
                        <span id="consumos-upload-limit" class="consumos-pdf-dropzone-hint"></span>
                    </label>
                    <input type="file" id="consumos-pdf" class="sr-only" accept="application/pdf,.pdf" aria-describedby="consumos-upload-limit consumos-import-error" required>
                </section>
                <section id="consumos-existing-import-panel" class="consumos-import-option consumos-existing-panel" aria-labelledby="consumos-existing-import-title">
                    <label id="consumos-existing-import-title" for="consumos-existing-import">Informes ya cargados</label>
                    <div class="consumos-existing-row">
                        <select id="consumos-existing-import"><option value="">Elige un informe cargado…</option></select>
                        <button type="button" id="consumos-use-existing" class="arqueo-button arqueo-button-primary">Abrir</button>
                    </div>
                </section>
                <fieldset id="consumos-import-pages-fieldset" class="consumos-import-pages" aria-describedby="consumos-import-pages-help consumos-import-pages-summary">
                    <legend class="sr-only">¿Qué hojas vas a utilizar?</legend>
                    <details id="consumos-import-pages-disclosure" class="consumos-import-pages-disclosure">
                        <summary><span id="consumos-import-pages-count">Hojas a utilizar</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg></summary>
                        <p id="consumos-import-pages-help">Marca las que necesites. Usaremos la última versión que guardaste de cada hoja.</p>
                        <div id="consumos-import-pages-list" class="consumos-import-pages-list"></div>
                        <p id="consumos-import-pages-summary" role="status" aria-live="polite" aria-atomic="true"></p>
                    </details>
                </fieldset>
                <p id="consumos-import-error" class="consumos-error" role="alert" hidden></p>
                <div class="consumos-popup-actions consumos-import-actions"><button type="submit" id="consumos-import-submit" class="arqueo-button arqueo-button-primary">Cargar PDF y revisar</button></div>
            </form>
        </dialog>
        <div id="consumos-product-dropdown" popover="auto" role="dialog" aria-labelledby="consumos-product-title" class="consumos-product-dropdown">
            <form id="consumos-bind-form"><div class="consumos-popup-heading"><h3 id="consumos-product-title">Revisar producto</h3><button type="button" id="consumos-close-product" aria-label="Cerrar búsqueda de producto">×</button></div>
                <label for="consumos-product-name">Producto de tu hoja</label><input id="consumos-product-name" type="text" maxlength="255" readonly>
                <label for="consumos-bind-search">Busca su nombre en el informe</label><input id="consumos-bind-search" type="search" placeholder="Ej.: mozzarella o salsa barbacoa" autocomplete="off" aria-describedby="consumos-search-help consumos-match-count" aria-controls="consumos-bind-list">
                <p id="consumos-search-help" class="consumos-search-help">Elige el producto del informe que corresponde. Revisarás esta elección en cada importación.</p>
                <div class="consumos-results-bar"><span id="consumos-match-count" role="status" aria-live="polite"></span><label><input id="consumos-available-only" type="checkbox"> Ocultar productos ya elegidos</label></div>
                <div id="consumos-bind-list" class="consumos-bind-list" role="group" aria-label="Filas del PDF para elegir manualmente"></div>
                <ul id="consumos-chosen-list" class="consumos-chosen-list" aria-label="Filas elegidas" hidden></ul>
                <p id="consumos-bind-selection" role="status" aria-live="polite"></p><p id="consumos-bind-error" class="consumos-error" role="alert" hidden></p>
                <div class="consumos-popup-actions"><button type="button" id="consumos-clear-selection" class="arqueo-button">Quitar selección</button><button type="submit" id="consumos-bind-submit" class="arqueo-button arqueo-button-primary">Confirmar producto</button><button type="submit" id="consumos-bind-next" class="arqueo-button">Confirmar y siguiente</button></div>
            </form>
        </div>
    </div>
    <script type="application/json" id="consumos-config">{!! json_encode(['initialData' => $initialData, 'urls' => ['save' => route('admin.consumos.fichas.save'), 'import' => route('admin.consumos.importar'), 'sheet' => url('/admin/consumos/fichas'), 'report' => url('/admin/consumos/importaciones'), 'documentShow' => route('admin.consumos.documento.show'), 'documentSave' => route('admin.consumos.documento.save'), 'prepare' => route('admin.consumos.importaciones.prepare', ['import' => '__IMPORT__']), 'complete' => route('admin.consumos.importaciones.complete', ['import' => '__IMPORT__'])]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
</x-app-layout>
