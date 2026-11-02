<x-app-layout>
    @if($workflowMode === 'entry')
    <x-slot name="header">
        <div class="arqueo-heading">
            <div><h2 class="font-bold text-2xl text-slate-800 dark:text-white">Arqueo</h2></div>
        </div>
        @error('fecha')<p class="text-red-700 dark:text-red-300 mt-2" role="alert">{{ $message }}</p>@enderror
    </x-slot>
    @endif
    <div class="arqueo-workspace" data-arqueo-mode="{{ $workflowMode }}">
        @if($workflowMode !== 'entry')<h2 class="sr-only">{{ $workflowMode === 'design' ? 'Editar documento de arqueo' : 'Rellenar arqueo' }}</h2>@endif
        @if($workflowMode === 'entry')
        <section class="arqueo-entry print-hidden" aria-labelledby="arqueo-entry-title">
            <h3 id="arqueo-entry-title" class="sr-only">¿Qué necesitas hacer?</h3>
            <div class="arqueo-entry-sketch-layout">
                <div class="arqueo-entry-left">
                    <a href="{{ route('admin.arqueo', ['modo' => 'editar', 'fecha' => $dateStr]) }}" class="arqueo-entry-template-box">
                        <span class="arqueo-entry-icon" aria-hidden="true">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>
                        </span>
                        <span class="arqueo-entry-template-title">Editar plantilla<span class="sr-only"> (Editar documento)</span></span>
                    </a>
                </div>

                <div class="arqueo-entry-right">
                    <a href="{{ route('admin.arqueo', ['modo' => 'rellenar', 'fecha' => $dateStr]) }}" class="arqueo-entry-sheet-trigger" aria-describedby="arqueo-fill-description">
                        <div class="arqueo-entry-sheet-preview" aria-hidden="true">
                            <div class="arqueo-entry-sheet-paper">
                                @if(!empty($entryPreviewHtml))
                                    <table class="arqueo-preview-table">
                                        <colgroup>@for($i=0; $i<24; $i++)<col style="width: {{ 100 / 24 }}%;">@endfor</colgroup>
                                        <tbody>{!! $entryPreviewHtml !!}</tbody>
                                    </table>
                                @else
                                    <div class="arqueo-preview-mock">
                                        <div class="arqueo-preview-mock-header">
                                            <div class="arqueo-preview-mock-title">ARQUEO DE CAJA DIARIO</div>
                                            <div class="arqueo-preview-mock-date">FECHA: {{ $date->format('d/m/Y') }}</div>
                                        </div>
                                        <div class="arqueo-preview-mock-section">
                                            <div class="arqueo-preview-mock-bar">PERSONAL Y TURNOS DEL DÍA</div>
                                            <div class="arqueo-preview-mock-rows">
                                                <div class="arqueo-preview-mock-row"><span>ENCARGADO</span><span>11:30 - 16:30</span><span>19:30 - 00:30</span></div>
                                                <div class="arqueo-preview-mock-row"><span>REPARTIDOR 1</span><span>12:00 - 16:00</span><span>20:00 - 00:00</span></div>
                                                <div class="arqueo-preview-mock-row"><span>REPARTIDOR 2</span><span>—</span><span>20:30 - 23:30</span></div>
                                                <div class="arqueo-preview-mock-row"><span>AUXILIAR 1</span><span>13:00 - 16:00</span><span>20:00 - 23:30</span></div>
                                                <div class="arqueo-preview-mock-row"><span>AUXILIAR 2</span><span>—</span><span>21:00 - 00:30</span></div>
                                            </div>
                                        </div>
                                        <div class="arqueo-preview-mock-cols">
                                            <div class="arqueo-preview-mock-box">
                                                <div class="arqueo-preview-mock-bar">INGRESOS Y CAJA</div>
                                                <div class="arqueo-preview-mock-line"><span>Efectivo mañana</span><span>0,00 €</span></div>
                                                <div class="arqueo-preview-mock-line"><span>Efectivo noche</span><span>0,00 €</span></div>
                                                <div class="arqueo-preview-mock-line"><span>Datáfono / Tarjeta</span><span>0,00 €</span></div>
                                                <div class="arqueo-preview-mock-line"><span>Plataformas</span><span>0,00 €</span></div>
                                                <div class="arqueo-preview-mock-line is-total"><span>TOTAL INGRESOS</span><span>0,00 €</span></div>
                                            </div>
                                            <div class="arqueo-preview-mock-box">
                                                <div class="arqueo-preview-mock-bar">GASTOS Y SALIDAS</div>
                                                <div class="arqueo-preview-mock-line"><span>Compras / Proveedores</span><span>0,00 €</span></div>
                                                <div class="arqueo-preview-mock-line"><span>Tickets / Varios</span><span>0,00 €</span></div>
                                                <div class="arqueo-preview-mock-line"><span>Fondo de caja</span><span>0,00 €</span></div>
                                                <div class="arqueo-preview-mock-line"><span>Retirada / Sobre</span><span>0,00 €</span></div>
                                                <div class="arqueo-preview-mock-line is-total"><span>TOTAL CAJA</span><span>0,00 €</span></div>
                                            </div>
                                        </div>
                                        <div class="arqueo-preview-mock-footer">
                                            <div class="arqueo-preview-mock-bar">OBSERVACIONES Y CUADRE</div>
                                            <div class="arqueo-preview-mock-grid-fill"></div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="arqueo-entry-sheet-overlay">
                            <div class="arqueo-entry-sheet-cta">
                                <span class="arqueo-entry-sheet-icon" aria-hidden="true">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 2h6v4H9zM9 12h6m-6 4h6"/></svg>
                                </span>
                                <span class="arqueo-entry-sheet-title">Arqueo<span class="sr-only"> (Rellenar arqueo)</span></span>
                                <span class="arqueo-button arqueo-button-primary arqueo-entry-sheet-pill">
                                    <span>Entrar en el arqueo</span>
                                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </section>
        @else
        @if($workflowMode === 'design')<div class="arqueo-design-controls arqueo-panel print-hidden">@endif
        <div class="arqueo-document-actions print-hidden" role="group" aria-label="Acciones del arqueo">
            @if($canDesign)
            <a href="{{ route('admin.arqueo', ['fecha' => $dateStr]) }}" class="arqueo-button arqueo-button-back"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5m6-6-6 6 6 6"/></svg>Volver</a>
            @endif
            <div class="arqueo-heading-actions">
                @if($workflowMode === 'daily')
                <form method="GET" action="{{ route($canDesign ? 'admin.arqueo' : 'arqueo') }}" id="form-fecha">
                    <input type="hidden" name="modo" value="rellenar">
                    <div class="arqueo-date-field"><label for="arqueo-fecha">Fecha de la hoja</label><input id="arqueo-fecha" type="date" name="fecha" value="{{ $dateStr }}" required></div>
                    <button type="submit" class="arqueo-button">Cambiar fecha</button>
                </form>
                <button type="button" onclick="printArqueo()" class="arqueo-button arqueo-button-primary">Imprimir</button>
                @else
                <button type="button" id="btn-export-template" class="arqueo-button">Exportar plantilla</button>
                <button type="button" id="btn-import-template" class="arqueo-button">Importar plantilla</button>
                <input type="file" id="arqueo-template-file" accept=".json,application/json" hidden>
                <button type="button" id="btn-save-template" onclick="saveTemplate()" class="arqueo-button arqueo-button-primary">Guardar</button>
                @endif
            </div>
        </div>
        @if($workflowMode === 'design')
                <div id="edit-toolbar" class="hidden print-hidden" role="region" aria-label="Herramientas de diseño">
                    <p id="selection-label" class="sr-only" aria-live="polite">Selecciona una casilla de la hoja.</p>
                    <div class="arqueo-tool-groups">
                        <fieldset><legend>Casillas</legend><button type="button" onclick="cmd('merge')" class="arqueo-button" title="Crea un espacio más ancho para un título o un dato">Unir casillas</button><button type="button" onclick="cmd('unmerge')" class="arqueo-button">Separar</button><button type="button" onclick="cmd('clear-content')" class="arqueo-button">Borrar texto</button></fieldset>
                        <fieldset><legend>Bordes</legend><button type="button" onclick="cmd('b-all')" class="arqueo-button">Todos</button><button type="button" onclick="cmd('b-outer')" class="arqueo-button">Contorno</button><button type="button" onclick="cmd('b-none')" class="arqueo-button">Sin bordes</button>
                            <select aria-label="Añadir o quitar un borde" onchange="if(this.value) cmd(this.value); this.value=''"><option value="">Un solo borde…</option><option value="b-top">Superior</option><option value="b-bottom">Inferior</option><option value="b-left">Izquierdo</option><option value="b-right">Derecho</option></select>
                        </fieldset>
                        <fieldset><legend>Texto</legend><button type="button" onclick="cmd('font-bold')" class="arqueo-button">Negrita</button><button type="button" onclick="cmd('font-down')" class="arqueo-button" aria-label="Reducir tamaño del texto">A−</button><button type="button" onclick="cmd('font-up')" class="arqueo-button" aria-label="Aumentar tamaño del texto">A+</button>
                            <select aria-label="Alineación del texto" onchange="if(this.value) cmd(this.value); this.value=''"><option value="">Alinear…</option><option value="align-left">Izquierda</option><option value="align-center">Centro</option><option value="align-right">Derecha</option></select>
                        </fieldset>
                        @include('admin.arqueo.color-picker')
                    </div>
                    <div class="arqueo-toolbar-end">
                        <div class="arqueo-toolbar-actions"><button type="button" id="btn-undo" class="arqueo-button" disabled>Deshacer</button><button type="button" id="btn-redo" class="arqueo-button" disabled>Rehacer</button></div>
                        <button type="button" id="btn-more-options" class="arqueo-button" aria-expanded="false" aria-controls="arqueo-more-options">Más opciones <span aria-hidden="true">▸</span></button>
                    </div>
                </div>
        </div>
        @endif
        @error('fecha')<p class="arqueo-overflow-warning print-hidden" role="alert">{{ $message }}</p>@enderror
        <p id="editor-status" class="arqueo-feedback print-hidden" role="status" aria-live="polite" aria-atomic="true" hidden></p>
        @if($templateWarning ?? null)<p class="arqueo-overflow-warning print-hidden" role="alert">{{ $templateWarning }}</p>@endif
        <noscript><p class="arqueo-overflow-warning print-hidden">Activa JavaScript para editar y preparar la hoja de arqueo.</p></noscript>
        <div class="arqueo-editor-layout">
            <div class="arqueo-editor-main">
                @if($workflowMode === 'design')
                <div id="arqueo-more-options" class="arqueo-panel hidden print-hidden" role="menu" aria-label="Más opciones de diseño" hidden>
                    <button type="button" role="menuitem" onclick="cmd('bind-date')" class="arqueo-button">Fecha automática</button><button type="button" role="menuitem" onclick="openBindModal()" class="arqueo-button">Empleado y turnos</button><button type="button" role="menuitem" onclick="cmd('unbind')" class="arqueo-button">Quitar vínculo</button><button type="button" role="menuitem" onclick="clearGrid()" class="arqueo-button arqueo-button-danger">Vaciar hoja completa</button>
                </div>
                <div id="template-empty-help" class="arqueo-empty-help print-hidden" hidden><strong>Prepara la plantilla una vez.</strong> Edita las casillas y pulsa <strong>Guardar arqueo</strong>. Estará disponible en «Rellenar arqueo», con los importes diarios vacíos.</div>
                @endif
                <p id="sheet-overflow-warning" class="arqueo-overflow-warning print-hidden" hidden role="status"></p>
                <div class="arqueo-sheet-scroll" tabindex="0" role="region" aria-label="Hoja de arqueo. Desplázate horizontalmente para verla completa en pantallas pequeñas.">
                    <div id="arqueo-print-area" class="hoja-arqueo">
                        <div class="arqueo-column-labels print-hidden" aria-hidden="true">@for($i=0; $i<24; $i++)<span>{{ chr(65 + $i) }}</span>@endfor</div>
                        <div id="sheet-row-labels" class="arqueo-row-labels print-hidden" aria-hidden="true">@for($i=1; $i<=73; $i++)<span>{{ $i }}</span>@endfor</div>
                        <table id="arqueo-grid" aria-label="Casillas del arqueo de caja"><colgroup>@for($i=0; $i<24; $i++)<col style="width: {{ 100 / 24 }}%;">@endfor</colgroup><tbody></tbody></table>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
    @if($workflowMode === 'design')
    <div id="context-menu" role="menu" aria-label="Opciones de Arqueo">
        <div class="arqueo-context-heading">
            <div class="arqueo-context-summary"><span>Opciones de Arqueo</span></div>
            <button type="button" id="btn-close-context" aria-label="Cerrar opciones">×</button>
        </div>
        <div class="arqueo-context-actions">
        <button type="button" role="menuitemcheckbox" data-context-action="bind-date" data-format-state="off" aria-checked="false" onclick="cmd('bind-date')"><span class="arqueo-context-marker" data-format-marker aria-hidden="true"></span><span class="arqueo-context-label">Fecha automática</span></button>
        <button type="button" role="menuitem" data-context-action="bind-employee" data-format-state="off" onclick="openBindModal()"><span class="arqueo-context-marker" data-format-marker aria-hidden="true"></span><span class="arqueo-context-label">Empleado y turnos…</span></button>
        <button type="button" role="menuitem" data-context-action="unbind" onclick="cmd('unbind')"><span class="arqueo-context-marker" aria-hidden="true"></span><span class="arqueo-context-label">Quitar vínculo</span></button>
        </div>
    </div>
    <div id="bind-modal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50" role="dialog" aria-modal="true" aria-labelledby="bind-title" aria-describedby="bind-description">
        <div class="arqueo-bind-dialog arqueo-panel"><h3 id="bind-title">Empleado y turnos automáticos</h3><p id="bind-description">Se preparan tres campos: nombre, mañana y noche. Las casillas libres se unen para que los datos quepan, respetando los cuadros existentes.</p><p id="bind-error" class="arqueo-bind-error" role="alert" hidden></p><label for="bind-emp">Empleado</label>
            <select id="bind-emp"><option value="">Selecciona un empleado…</option>@foreach($empleadosList as $emp)<option value="{{ $emp->id }}">{{ $emp->nombre }} {{ $emp->apellidos }}</option>@endforeach</select>
            <p id="bind-preview" hidden>Se reserva espacio para el nombre y los turnos de mañana y noche.</p><div class="arqueo-toolbar-actions"><button type="button" onclick="closeBindModal()" class="arqueo-button">Cancelar</button><button type="button" onclick="applyBind()" class="arqueo-button arqueo-button-primary">Vincular empleado</button></div>
        </div>
    </div>
    @endif
    @if($workflowMode !== 'entry')
    <script>
        window.arqueoConfig = {
            templateData: @json($templateData), empleadosDia: @json($empleadosDia), empleadosList: @json($empleadosList), fecha: @json($dateStr),
            dailyMode: true, canEditDesign: @json($workflowMode === 'design'), startInEditMode: @json($workflowMode === 'design'), quietInitialStatus: true,
            templateHash: @json($templateHash), dailyAvailable: @json($dailyAvailable), dailySaveUrl: @json(route('arqueo.daily.save')),
            savedMessage: 'Plantilla guardada para los próximos arqueos, con los datos diarios vacíos y los vínculos automáticos conservados.',
            saveUrl: @json(route('admin.arqueo.template.save')), enableImportGeometry: true, csrfToken: @json(csrf_token())
        };
    </script>
    @endif
</x-app-layout>
