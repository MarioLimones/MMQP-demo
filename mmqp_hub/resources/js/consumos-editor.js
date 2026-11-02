import { initializeArqueo, selectionRectangle, sanitizeTemplate, templateSnapshot } from './arqueo-editor.js';
import { sumQuantities, validateBinding, sourceOwners } from './consumos-state.js';
import { CONSUMO_ATTRIBUTES, PRODUCT_FIELDS, fieldWidths, readSources, readSuppliers, planBlocks, planProductRows, applyBlocks, rowRectangles, legacyGrid, blankGrid, appendSupplierProduct } from './consumos-grid.js';
import { searchRows } from './consumos-search.js';
import { planSideBlock, readSideBlock, planSideBlockExtension } from './consumos-side-grid.js';
import { planOrderBlock } from './consumos-order-grid.js';
import { BLOCK_COLUMN_LABELS, planCustomBlock } from './consumos-block-layout.js';
import { createConsumosDraftStore } from './consumos-draft.js';
import { fitConsumoText } from './consumos-text-fit.js';
import { drawPrintBorders, clearPrintBorders } from './arqueo-print-borders.js';
import { createConsumoTemplateFile, readConsumoTemplateFile, MAX_CONSUMO_TEMPLATE_BYTES } from './consumos-template-file.js';
import { downloadTemplate } from './arqueo-template-transfer.js';

function startConsumos() {
    const element = document.getElementById('consumos-config');
    if (!element) return;
    const { initialData, urls } = JSON.parse(element.textContent);
    const $ = id => document.getElementById(`consumos-${id}`);
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
    const workflowEnabled = Boolean(urls.documentSave);
    const emptyDocument = () => ({ id: null, version: null, title: 'Documento de consumos', suppliers: [], grid_html: null });
    let consumptionDocument = initialData.document || emptyDocument();
    let workflowMode = workflowEnabled ? 'entry' : 'sheet';
    let pendingProducts = [];
    let skippedProducts = new Set();
    const reviewAllBindings = false;
    let importPageChoices = [];
    let selectedImportPageIds = new Set();
    let activeImportPageIds = null;
    let sheet = workflowEnabled ? consumptionDocument : initialData.sheet || { id: null, version: null, title: 'Ficha de consumos', suppliers: [], grid_html: null };
    let report = workflowEnabled ? null : initialData.activeImport || null;
    let sheets = initialData.sheets || [];
    let reports = initialData.imports || [];
    let api;
    let busy = false;
    let productId = null;
    let selectedSources = new Set();
    let savedExtra;
    let savedGrid;
    let popupAnchor = null;
    let sideSelection = null;
    let sideProvider = null;
    let sideCreated = false;
    let orderSelection = null;
    let orderCreated = false;
    let customSelection;
    let namesSupplier;
    let draftStore;
    let lastDraftRevision = null;
    let pendingDraft;
    let changeTimer;
    let changingInternally = false;
    let workflowFocus = null;
    let pages = normalizePages(sheet);
    let activePageId = pages[0].id;
    let savedPages = null;
    let templateUndo = null;
    let templatePreview = null;
    const pageBodyCache = new Map();
    const pendingImportKey = `mmqp:consumos:${initialData.draft_namespace || 'local'}:pending-import`;
    const rows = () => report?.rows || [];
    const extra = () => JSON.stringify({ title: $('title').value, import_id: report?.id || null, ...(workflowEnabled && workflowMode === 'linking' ? { skipped_product_ids: [...skippedProducts].sort() } : {}) });
    const dirty = () => api?.dirty || savedExtra !== extra() || (savedPages !== null && savedPages !== JSON.stringify(capturePages()));
    const text = cell => cell?.querySelector('.cell-content')?.innerText || cell?.querySelector('.cell-content')?.textContent || '';
    const productCells = id => pageBodies().flatMap(body => [...body.querySelectorAll('[data-consumo-product]')]).filter(cell => cell.dataset.consumoProduct === id);
    const amount = item => `${String(item.amount ?? item.quantity).replace('.', ',')} ${item.unit}`;
    const reportLabel = item => {
        const date = new Date(item.created_at);
        return `${item.file_name}${Number.isNaN(date.getTime()) ? '' : ` · ${date.toLocaleString('es-ES')}`} · ${item.row_count ?? item.rows?.length ?? ''} filas`;
    };
    const field = (id, name) => productCells(id).find(cell => cell.dataset.consumoField === name);
    function canonicalHtml(html) {
        const options = { cellAttributes: CONSUMO_ATTRIBUTES, cellAttributeLimits: { 'data-consumo-sources': 1000000 }, isBound: cell => cell.dataset.consumoField === 'consumo' };
        return templateSnapshot(sanitizeTemplate(html, document, options), options);
    }
    function normalizePages(value) {
        return Array.isArray(value.pages) && value.pages.length ? value.pages.map(page => ({ id: page.id, title: page.title, grid_html: canonicalHtml(page.grid_html || blankGrid(document)) }))
            : [{ id: crypto.randomUUID(), title: 'Hoja 1', grid_html: value.grid_html || legacyGrid(value.suppliers || [], document) || blankGrid(document) }];
    }
    function capturePages(html) {
        if (api) {
            const current = pages.find(page => page.id === activePageId);
            if (current) current.grid_html = html ?? api.getHtml();
        }
        return pages.map(page => ({ ...page }));
    }
    function pageBodies() {
        return pages.map(page => {
            if (page.id === activePageId && api) return api.tbody;
            let cached = pageBodyCache.get(page.id);
            if (!cached || cached.html !== page.grid_html) {
                const body = document.createElement('tbody'); body.innerHTML = page.grid_html || '';
                cached = { html: page.grid_html, body }; pageBodyCache.set(page.id, cached);
            }
            return cached.body;
        });
    }
    function allSuppliers() { capturePages(); return pageBodies().flatMap(body => readSuppliers(body)); }
    function productPage(id) {
        const bodies = pageBodies();
        return pages.find((page, index) => [...bodies[index].querySelectorAll('[data-consumo-product]')].some(cell => cell.dataset.consumoProduct === id));
    }
    function renderPages() {
        if (!$('page-select')) return;
        const editing = !workflowEnabled || workflowMode === 'document';
        $('page-select').innerHTML = pages.map((page, index) => `<option value="${escape(page.id)}">${index + 1}. ${escape(page.title)}</option>`).join('');
        $('page-select').value = activePageId;
        $('page-title').value = pages.find(page => page.id === activePageId)?.title || '';
        for (const name of ['page-title', 'page-add', 'page-up', 'page-down', 'page-delete']) {
            const control = $(name); if (control) { control.hidden = !editing; control.disabled = busy || Boolean(pendingDraft); }
        }
        $('page-select').disabled = busy || Boolean(pendingDraft);
        if ($('page-up')) $('page-up').disabled ||= pages[0].id === activePageId;
        if ($('page-down')) $('page-down').disabled ||= pages.at(-1).id === activePageId;
        if ($('page-add')) $('page-add').disabled ||= pages.length >= 20;
        if ($('page-delete')) {
            $('page-delete').disabled ||= pages.length <= 1;
            $('page-delete').title = pages.length <= 1 ? 'El documento debe conservar al menos una hoja.' : 'Eliminar la hoja seleccionada';
        }
        for (const name of ['template-import', 'template-export', 'template-undo']) {
            if ($(name)) $(name).disabled = !editing || busy || Boolean(pendingDraft);
        }
        if ($('template-undo')) $('template-undo').hidden = !templateUndo;
    }
    function switchPage(id) {
        if (id === activePageId || !pages.some(page => page.id === id)) return;
        capturePages();
        if ($('product-dropdown').matches(':popover-open')) $('product-dropdown').hidePopover();
        changingInternally = true;
        try {
            activePageId = id;
            api.loadHtml(pages.find(page => page.id === id).grid_html || blankGrid(document), { resetHistory: true });
            const original = savedPages ? JSON.parse(savedPages).find(page => page.id === id) : null;
            api.setSavedHtml(original?.grid_html || '');
        } finally { changingInternally = false; }
        renderPages(); updateReview();
    }
    function activateProductPage(id) { const page = productPage(id); if (page) switchPage(page.id); }
    function replacePage(html) {
        capturePages();
        const occupied = new Set(pageBodies().filter(body => body !== api.tbody).flatMap(body => [...body.querySelectorAll('[data-consumo-provider],[data-consumo-product]')].map(cell => cell.dataset.consumoProvider || cell.dataset.consumoProduct)));
        const body = document.createElement('tbody'); body.innerHTML = html;
        const replacements = new Map();
        for (const cell of body.querySelectorAll('[data-consumo-provider],[data-consumo-product],[data-consumo-supplier]')) {
            for (const attribute of ['data-consumo-provider', 'data-consumo-product', 'data-consumo-supplier']) {
                const id = cell.getAttribute(attribute);
                if (id && occupied.has(id)) { if (!replacements.has(id)) replacements.set(id, crypto.randomUUID()); cell.setAttribute(attribute, replacements.get(id)); }
            }
        }
        api.loadHtml(body.innerHTML);
        capturePages(); renderPages(); changed();
    }
    function error(message) {
        api?.status(message, true);
        const node = workflowMode === 'linking' ? $('linking-error') : workflowMode === 'entry' ? $('workflow-error') : null;
        if (node) { node.textContent = message; node.hidden = false; }
    }
    function clearError() { if (document.getElementById('editor-status').dataset.state === 'error') api?.status(''); }
    function setText(cell, value) { cell.querySelector('.cell-content').textContent = value; }
    function draftSnapshot() {
        return { id: sheet.id || null, version: sheet.version || null, title: $('title').value, import_id: report?.id || null, grid_html: capturePages()[0].grid_html, pages: capturePages(), active_page_id: activePageId, ...(workflowEnabled && workflowMode === 'linking' ? { skipped_product_ids: [...skippedProducts] } : {}) };
    }
    function writeDraft() {
        if (!draftStore || pendingDraft || changingInternally || !api || !dirty() || (workflowEnabled && workflowMode === 'entry')) return null;
        changingInternally = true;
        try {
            const result = draftStore.write(draftSnapshot());
            if (result.ok) lastDraftRevision = result.draft.revision;
            return result;
        }
        finally { changingInternally = false; }
    }
    function changed() {
        updateReview();
        if (!api || changingInternally) return;
        clearTimeout(changeTimer);
        changeTimer = setTimeout(() => {
            if (!busy && !pendingDraft && dirty()) writeDraft();
        }, 500);
    }
    function lockDraftDecision() {
        for (const selector of ['#edit-toolbar', '.consumos-paper-actions', '.arqueo-sheet-scroll', '.consumos-review', '.consumos-entry-options', '.consumos-file-bar', '.consumos-page-bar', '.consumos-popup-host', '#context-menu', '#consumos-linking-list', '#consumos-linking-save']) {
            const region = document.querySelector(selector);
            if (region) region.inert = busy || Boolean(pendingDraft);
        }
        if (api) api.table.inert = busy || Boolean(pendingDraft);
        if (workflowEnabled) $('app').setAttribute('aria-busy', String(busy));
        renderPages();
    }
    function associateDraft() {
        clearTimeout(changeTimer);
        let storage;
        try { storage = window.localStorage; } catch { /* A restricted browser must still allow editing and saving. */ }
        const scope = workflowEnabled && workflowMode === 'document' ? `document:${sheet.id || 'new'}` : workflowEnabled && workflowMode === 'linking' ? `mapping:${consumptionDocument.id}:${consumptionDocument.version}:${report?.id}${reviewAllBindings ? ':manual-review' : ''}${importSelectionSuffix()}` : sheet.id || 'new';
        draftStore = createConsumosDraftStore({ storage, key: `mmqp:consumos:${initialData.draft_namespace || 'local'}:${scope}`, onStatus: state => {
            $('draft-status').textContent = workflowEnabled && ['discarded', 'cleared'].includes(state.reason) ? '' : state.message || '';
        } });
        pendingDraft = draftStore.read();
        $('draft-banner').hidden = !pendingDraft;
        if (pendingDraft) {
            const compatible = Number(pendingDraft.base_sheet_id || 0) === Number(sheet.id || 0) && Number(pendingDraft.base_version || 0) === Number(sheet.version || 0);
            $('draft-message').textContent = compatible ? 'Hay un borrador local sin guardar. Recupéralo o descártalo para continuar editando.' : 'Hay un borrador de una versión anterior. Se conserva para evitar sobrescribir cambios más recientes. Descártalo para continuar.';
            $('draft-restore').disabled = !compatible;
        }
        lockDraftDecision();
    }

    function populateCell(cell) {
        cell.querySelector('.consumos-add-product')?.remove();
        if (cell.dataset.consumoProvider) {
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'consumos-add-product print-hidden'; button.textContent = '+';
            button.setAttribute('aria-label', `Añadir producto a ${text(cell) || 'este proveedor'}`);
            button.title = 'Añadir un producto';
            button.addEventListener('pointerdown', event => event.stopPropagation());
            button.addEventListener('dblclick', event => event.stopPropagation());
            button.addEventListener('keydown', event => { if (['Enter', ' ', 'Tab'].includes(event.key)) event.stopPropagation(); });
            button.addEventListener('click', event => {
                event.stopPropagation();
                if (!api?.editMode || busy || pendingDraft || (workflowEnabled && workflowMode !== 'document')) return;
                try {
                    clearError();
                    let product;
                    api.runTransaction(() => { product = appendSupplierProduct(api.tbody, cell, crypto.randomUUID()); });
                    api.selectCell(product, false, true);
                    product.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
                    api.status('Producto añadido. Escribe su nombre en la nueva fila.');
                } catch (exception) { error(exception.message); }
            });
            cell.append(button);
        }
        if (cell.dataset.consumoField === 'consumo') {
            try {
                const chosen = readSources(cell).map(id => rows().find(row => row.id === id));
                if (chosen.some(row => !row)) { setText(cell, '[Revisar vínculo]'); return; }
                const result = sumQuantities(chosen);
                setText(cell, result ? amount(result) : '');
            } catch { setText(cell, '[Revisar vínculo]'); }
        }
        cell.querySelector('.consumos-cell-dropdown')?.remove();
        if (cell.dataset.consumoField === 'product' && !workflowEnabled) {
            const button = document.createElement('button');
            button.type = 'button'; button.className = 'consumos-cell-dropdown'; button.textContent = '▾';
            button.setAttribute('aria-label', `Buscar consumo de ${text(cell) || 'este producto'}`);
            button.setAttribute('aria-haspopup', 'dialog'); button.title = 'Buscar y vincular consumo (Alt + flecha abajo)';
            button.addEventListener('click', event => { event.stopPropagation(); openProduct(cell.dataset.consumoProduct); });
            cell.append(button);
            button.addEventListener('pointerdown', event => event.stopPropagation());
            const consumption = [...cell.closest('tbody').querySelectorAll('[data-consumo-field="consumo"]')].find(part => part.dataset.consumoProduct === cell.dataset.consumoProduct);
            let linked = false;
            try { linked = readSources(consumption).length > 0; } catch { /* Invalid links are flagged in the consumption cell. */ }
            button.classList.toggle('is-linked', linked);
            button.title = linked ? 'Revisar vínculo del PDF (Alt + flecha abajo)' : 'Sin vínculo: buscar consumo en el PDF (Alt + flecha abajo)';
        }
    }

    async function refreshHistory() {
        const current = $('history');
        if (!current?.dataset.historyUrl) return;
        try {
            const response = await fetch(current.dataset.historyUrl, { headers: { Accept: 'text/html' } });
            if (!response.ok || response.redirected) throw new Error('History unavailable');
            const template = document.createElement('template');
            template.innerHTML = await response.text();
            const next = template.content.querySelector('#consumos-history');
            if (!next) throw new Error('Invalid history response');
            current.replaceWith(next);
        } catch {
            // The ficha is already saved. A failed calendar refresh must not
            // turn a successful save into an apparent failure or lose a draft.
            let notice = current.querySelector('[data-history-refresh-error]');
            if (!notice) {
                notice = document.createElement('p');
                notice.dataset.historyRefreshError = '';
                notice.setAttribute('role', 'status');
                current.append(notice);
            }
            notice.textContent = 'El consumo está guardado. Recarga la página para actualizar el calendario.';
        }
    }

    function renderSelects() {
        $('sheet-select').innerHTML = `<option value="">${workflowEnabled ? 'Elegir ficha guardada' : 'Nueva ficha'}</option>` + sheets.map(item => `<option value="${escape(item.id)}">${escape(item.title)}</option>`).join('');
        $('sheet-select').value = workflowEnabled && workflowMode !== 'sheet' ? '' : sheet.id || '';
        $('report-select').innerHTML = '<option value="">Sin informe</option>' + reports.map(item => `<option value="${escape(item.id)}">${escape(reportLabel(item))}</option>`).join('');
        $('report-select').value = report?.id || '';
        if ($('existing-import')) {
            $('existing-import').innerHTML = '<option value="">Elige un consumo importado</option>' + reports.map(item => `<option value="${escape(item.id)}">${escape(reportLabel(item))}</option>`).join('');
            $('existing-import').value = report?.id || '';
            $('use-existing').disabled = !reports.length || busy;
        }
    }
    function updateReview() {
        if (!api) return;
        let owners = new Map();
        try { for (const body of pageBodies()) for (const [id, owner] of sourceOwners(readSuppliers(body))) owners.set(id, owner); } catch { /* Save reports malformed typed groups explicitly. */ }
        const all = rows();
        $('coverage').textContent = report ? `${all.length} filas importadas · ${all.filter(row => !owners.has(row.id)).length} sin vincular` : 'Sin PDF importado';
        $('row-count').textContent = report ? `· ${all.length} filas` : '';
        $('source-list').innerHTML = searchRows(all, $('source-search').value).map(row => `<div class="consumos-source"><strong>${escape(row.name)}</strong><span>${escape(amount(row))}</span><small>${escape(row.category)} · ${escape(row.group)} · Pág. ${escape(row.page)} · ${owners.has(row.id) ? `Vinculado: ${escape(owners.get(row.id).label)}` : 'Sin vincular'}</small></div>`).join('') || '<p>No hay filas para mostrar.</p>';
        if (workflowEnabled && workflowMode === 'linking') renderPendingProducts();
    }

    async function request(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, ...options.headers } });
        let result;
        try { result = await response.json(); } catch {
            throw new Error('El servidor no ha devuelto una respuesta válida. Tu ficha se conserva; recarga para comprobar si la operación llegó a guardarse.');
        }
        if (!response.ok) throw new Error(response.status === 419 ? 'La sesión ha caducado. Tu trabajo sigue aquí; vuelve a iniciar sesión antes de guardar.' : Object.values(result.errors || {}).flat().join(' ') || result.message || 'No se ha podido completar la operación. Tu ficha se conserva.');
        if (!result || typeof result !== 'object' || (!result.sheet && !result.import && !Object.hasOwn(result, 'document'))) throw new Error('No se ha podido confirmar la operación. Tu ficha se conserva; recarga antes de volver a intentarlo.');
        return result;
    }
    async function operation(task, errorNode = null) {
        if (busy) { renderSelects(); return; }
        busy = true; clearError();
        lockDraftDecision();
        const controls = [...document.querySelectorAll('.consumos-file-bar input,.consumos-file-bar select,.consumos-page-bar input,.consumos-page-bar select,.consumos-page-bar button,#consumos-import-dialog input,#consumos-import-dialog select,#consumos-import-dialog button,#consumos-import-button,#consumos-linking-save,#consumos-linking-back,#consumos-workflow-back')];
        controls.forEach(control => { control.disabled = true; });
        try { await task(); } catch (exception) {
            renderSelects();
            if (errorNode) {
                errorNode.textContent = exception.message; errorNode.hidden = false;
                if (errorNode.id === 'consumos-import-error') errorNode.scrollIntoView?.({ block: 'nearest' });
            }
            else error(exception.message);
            if (workflowEnabled && errorNode) error(exception.message);
        } finally {
            controls.forEach(control => { control.disabled = false; }); busy = false; lockDraftDecision();
            if (workflowEnabled) {
                renderWorkflow();
                if ($('import-dialog').open) { renderResumeImport(); updateImportPageSummary(); }
                if (workflowFocus) {
                    const firstPending = pendingProducts.find(product => !pendingState(product).resolved);
                    const pendingButton = [...$('linking-list').querySelectorAll('[data-map-product]')].find(button => button.dataset.mapProduct === firstPending?.product_id);
                    const focus = pendingDraft ? (!$('draft-restore').disabled ? $('draft-restore') : $('draft-discard')) : workflowFocus === 'pending' ? pendingButton || $('linking-save') : $('workflow-title');
                    workflowFocus = null;
                    focus?.focus({ preventScroll: true });
                }
            }
        }
    }
    async function save(html) {
        if (busy) throw new Error('Espera a que termine la operación actual antes de guardar.');
        const savingDocument = workflowEnabled && workflowMode === 'document';
        const localPages = capturePages(html);
        const persistedPages = new Map(sheet.id && savedPages ? JSON.parse(savedPages).map(page => [page.id, page]) : []);
        // Persist the active page; other edits and new pages stay in the local draft.
        // Explicit page removal and ordering still apply to the collection.
        const savedPageList = savingDocument ? localPages.filter(page => page.id === activePageId || persistedPages.has(page.id))
            .map(page => page.id === activePageId ? page : persistedPages.get(page.id)) : localPages;
        const suppliers = savingDocument ? savedPageList.flatMap(page => {
            const body = document.createElement('tbody'); body.innerHTML = page.grid_html; return readSuppliers(body);
        }) : allSuppliers();
        if (!$('title').value.trim()) throw new Error('Escribe un nombre para la ficha.');
        if (suppliers.some(supplier => !supplier.name)) throw new Error('Escribe el nombre del proveedor antes de guardar.');
        if (suppliers.some(supplier => supplier.products.some(product => !product.name && (product.source_ids.length || product.stock.trim() || product.pedido.trim())))) throw new Error('Escribe el nombre de los productos que tengan un vínculo, stock o pedido. Puedes guardar las filas vacías para rellenarlas después.');
        if (workflowEnabled && workflowMode === 'linking') throw new Error('Revisa los productos y pulsa «Crear ficha de consumo».');
        const payload = { ...(sheet.id ? { id: sheet.id, version: sheet.version } : {}), title: $('title').value, ...(savingDocument ? {} : { import_id: report?.id || null }), suppliers, grid_html: savedPageList[0].grid_html, pages: savedPageList };
        const draftResult = writeDraft();
        const savingStore = draftStore;
        clearTimeout(changeTimer);
        busy = true;
        lockDraftDecision();
        try {
            const result = await request(savingDocument ? urls.documentSave : urls.save, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            sheet = savingDocument ? result.document : result.sheet;
            if (!sheet?.id || !sheet?.version) throw new Error('El servidor no confirmó el documento guardado. Tu trabajo se conserva.');
            if (savingDocument) consumptionDocument = sheet;
            if (savingDocument && templateUndo?.id === activePageId) templateUndo = null;
            savedExtra = JSON.stringify({ title: payload.title, import_id: payload.import_id || null });
            savedGrid = html;
            savedPages = JSON.stringify(savedPageList);
            if (!savingDocument) sheets = [sheet, ...sheets.filter(item => String(item.id) !== String(sheet.id))];
            renderSelects();
            if (draftResult?.ok) savingStore.clearAfterSave(sheet, { revision: draftResult.draft.revision });
            associateDraft();
            if (savingDocument && JSON.stringify(capturePages(html)) !== savedPages) writeDraft();
            if (!savingDocument) await refreshHistory();
        } finally { busy = false; lockDraftDecision(); if (workflowEnabled) renderWorkflow(); }
    }

    $('title').value = sheet.title;
    savedExtra = extra();
    api = initializeArqueo({
        templateData: { html: pages[0].grid_html },
        cellAttributes: CONSUMO_ATTRIBUTES,
        cellAttributeLimits: { 'data-consumo-sources': 1000000 },
        isBound: cell => cell.dataset.consumoField === 'consumo',
        populateCell,
        removeBinding: cell => { if (cell.dataset.consumoField === 'consumo') cell.dataset.consumoSources = '[]'; },
        canMerge: cells => cells.some(cell => CONSUMO_ATTRIBUTES.some(attribute => cell.hasAttribute(attribute))) ? 'Quita primero el tipo especial antes de unir estas casillas. Los productos y sus vínculos se conservan.' : true,
        canUnmerge: cells => cells.some(cell => CONSUMO_ATTRIBUTES.some(attribute => cell.hasAttribute(attribute))) ? 'Quita primero el tipo especial antes de separar estas casillas.' : true,
        canPaste: writes => writes.some(write => write.cell.dataset.consumoField === 'consumo') ? 'Consumo se rellena desde el PDF. Pega en Producto, Stock, Pedido o en casillas libres.' : true,
        onChanged: changed,
        onSave: save,
        confirmClear: false,
        savedMessage: () => workflowEnabled && workflowMode === 'document'
            ? `Hoja «${pages.find(page => page.id === activePageId)?.title}» guardada.${JSON.stringify(capturePages()) !== savedPages ? ' Hay cambios en otras hojas conservados en el borrador local.' : ''}`
            : 'Ficha guardada. Se conservan las casillas, el formato y los vínculos elegidos.',
        gridLabel: 'Hoja de consumos. Escribe en casillas. Usa Mayúsculas y las flechas para seleccionar rangos, y Alt más flecha abajo para buscar el consumo de un producto.',
    });
    api.table.addEventListener('keydown', event => {
        if (event.key === 'Tab' && !event.shiftKey && api.editMode && event.target.matches('.cell-content')
            && event.target.closest('[data-consumo-provider]')?.querySelector('.consumos-add-product')) event.stopPropagation();
    }, true);
    if (sheet.id && !sheet.grid_html && !sheet.suppliers?.length) window.toggleEditMode();
    savedGrid = api.getHtml();
    savedPages = JSON.stringify(capturePages());
    if (!sheet.id && !workflowEnabled) { savedGrid = ''; api.setSavedHtml(''); }
    api.status('Hoja lista. Pulsa un producto para buscar su consumo en el PDF. Puedes cambiar los nombres desde «Editar diseño».');
    renderSelects(); updateReview();
    if (workflowEnabled) renderWorkflow();
    else associateDraft();
    $('page-select')?.addEventListener('change', event => {
        if (busy || pendingDraft) { renderPages(); return; }
        switchPage(event.target.value); writeDraft();
    });
    $('page-title')?.addEventListener('input', event => {
        if (busy || pendingDraft || (workflowEnabled && workflowMode !== 'document')) return;
        pages.find(page => page.id === activePageId).title = event.target.value;
        changed();
        // Keep the focused input stable while updating the option's accessible name.
        const option = [...$('page-select').options].find(option => option.value === activePageId);
        if (option) option.textContent = `${pages.findIndex(page => page.id === activePageId) + 1}. ${event.target.value}`;
    });
    $('page-add')?.addEventListener('click', () => {
        if (busy || pendingDraft || pages.length >= 20 || (workflowEnabled && workflowMode !== 'document')) return;
        capturePages();
        const next = { id: crypto.randomUUID(), title: `Hoja ${pages.length + 1}`, grid_html: blankGrid(document) };
        pages.push(next); switchPage(next.id); changed(); $('page-title').focus();
        api.status('Hoja añadida. Prepara su plantilla; las otras hojas se conservan.');
    });
    $('page-delete')?.addEventListener('click', () => {
        if (busy || pendingDraft || pages.length <= 1 || (workflowEnabled && workflowMode !== 'document')) return;
        const index = pages.findIndex(page => page.id === activePageId);
        const removed = pages[index];
        if (!window.confirm(`¿Eliminar la hoja «${removed.title}» y todo su contenido? La eliminación se aplicará al guardar el documento.`)) return;
        const next = pages[index + 1] || pages[index - 1];
        switchPage(next.id);
        pages.splice(index, 1);
        pageBodyCache.delete(removed.id);
        renderPages(); changed(); writeDraft(); $('page-select').focus();
        api.status('Hoja eliminada. Pulsa Guardar hoja para conservar el cambio.');
    });
    for (const [name, direction] of [['page-up', -1], ['page-down', 1]]) $(name)?.addEventListener('click', () => {
        if (busy || pendingDraft || (workflowEnabled && workflowMode !== 'document')) return;
        capturePages(); const index = pages.findIndex(page => page.id === activePageId); const target = index + direction;
        if (target < 0 || target >= pages.length) return;
        [pages[index], pages[target]] = [pages[target], pages[index]];
        renderPages(); changed();
    });
    const canTransfer = () => !busy && !pendingDraft && (!workflowEnabled || workflowMode === 'document');
    const templateState = () => ({ title: $('title').value, pages: capturePages(), activePageId });
    function applyTemplatePage(page) {
        capturePages();
        clearTimeout(changeTimer); changingInternally = true;
        try {
            pages = pages.map(current => current.id === page.id ? { ...page } : current);
            activePageId = page.id; pageBodyCache.delete(page.id);
            api.loadHtml(page.grid_html, { resetHistory: true });
            const original = savedPages ? JSON.parse(savedPages).find(page => page.id === activePageId) : null;
            api.setSavedHtml(original?.grid_html || '');
        } finally { changingInternally = false; }
        renderPages(); changed(); writeDraft();
    }
    $('template-export')?.addEventListener('click', () => {
        if (!canTransfer()) return;
        try {
            const page = capturePages().find(page => page.id === activePageId);
            const slug = page.title.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 90) || 'hoja';
            downloadTemplate(createConsumoTemplateFile({ title: page.title, pages: [page] }, document), `plantilla-consumos-${slug}.json`, document);
            api.status(`Plantilla de «${page.title}» exportada.`);
        } catch (exception) { error(exception.message); }
    });
    $('template-import')?.addEventListener('click', () => {
        if (!canTransfer()) return;
        $('template-file').value = ''; $('template-file').click();
    });
    $('template-file')?.addEventListener('change', () => {
        const file = $('template-file').files?.[0];
        if (!file || !canTransfer()) return;
        operation(async () => {
            if (!/\.json$/i.test(file.name) || !file.size) throw new Error('Selecciona un archivo JSON de plantilla de Consumos.');
            if (file.size > MAX_CONSUMO_TEMPLATE_BYTES) throw new Error('El archivo supera los 2 MB permitidos.');
            const previous = templateState();
            api.status('Leyendo la plantilla…');
            const value = readConsumoTemplateFile(await file.text(), document);
            if (JSON.stringify(previous) !== JSON.stringify(templateState())) throw new Error('El documento ha cambiado. Selecciona de nuevo la plantilla.');
            templatePreview = { value, previous };
            const bodies = value.pages.map(page => { const body = document.createElement('tbody'); body.innerHTML = page.grid_html; return body; });
            const suppliers = bodies.flatMap(body => readSuppliers(body));
            const target = pages.find(page => page.id === activePageId);
            $('template-summary').textContent = `${value.pages[0].title} · ${suppliers.length} proveedores · ${suppliers.reduce((sum, supplier) => sum + supplier.products.length, 0)} productos. Hoja de destino: «${target.title}».`;
            api.status('Plantilla lista para revisar. Confirma la importación o cancela para conservar tu documento.');
            $('template-dialog').showModal();
        });
    });
    $('template-cancel')?.addEventListener('click', () => { templatePreview = null; $('template-dialog').close(); $('template-import').focus(); api.status('Importación cancelada. Tu documento se conserva.'); });
    $('template-dialog')?.addEventListener('cancel', () => { templatePreview = null; api.status('Importación cancelada. Tu documento se conserva.'); });
    $('template-apply')?.addEventListener('click', () => {
        if (!canTransfer() || !templatePreview) return;
        if (JSON.stringify(templatePreview.previous) !== JSON.stringify(templateState())) { $('template-dialog').close(); templatePreview = null; error('El documento ha cambiado. Selecciona de nuevo la plantilla.'); return; }
        templateUndo = { ...pages.find(page => page.id === activePageId) };
        const value = templatePreview.value; templatePreview = null; $('template-dialog').close();
        applyTemplatePage({ ...value.pages[0], id: activePageId });
        api.status('Hoja importada. Pulsa Guardar hoja para conservarla o Deshacer importación para recuperar la anterior.');
        document.getElementById('btn-save-template')?.focus({ preventScroll: true });
    });
    $('template-undo')?.addEventListener('click', () => {
        if (!canTransfer() || !templateUndo) return;
        const previous = templateUndo; templateUndo = null; applyTemplatePage(previous);
        if (!dirty() && lastDraftRevision) draftStore.discard({ revision: lastDraftRevision });
        api.status('Importación deshecha. Se ha recuperado la hoja anterior.'); $('template-import').focus();
    });
    function buildPrintPages() {
        const host = $('print-pages'); if (!host) return;
        host.replaceChildren();
        for (const page of capturePages()) {
            const outer = document.createElement('div'); outer.className = 'consumos-print-sheet';
            const area = document.createElement('div'); area.className = 'consumos-print-page'; area.setAttribute('aria-label', page.title);
            const table = document.createElement('table'); table.className = 'consumos-print-grid';
            const columns = api.table.querySelector('colgroup'); if (columns) table.append(columns.cloneNode(true));
            const body = document.createElement('tbody'); body.innerHTML = page.grid_html || '';
            for (const cell of body.querySelectorAll('td')) {
                cell.style.setProperty('--cell-rows', String(cell.rowSpan));
                const content = cell.querySelector('.cell-content'); if (content) { content.removeAttribute('contenteditable'); content.removeAttribute('tabindex'); }
                if (cell.dataset.consumoField === 'consumo') {
                    const chosen = readSources(cell).map(id => rows().find(row => row.id === id));
                    const sum = chosen.every(Boolean) ? sumQuantities(chosen) : null;
                    setText(cell, sum ? amount(sum) : '');
                }
            }
            body.querySelectorAll('button').forEach(button => button.remove());
            table.append(body); area.append(table); outer.append(area); host.append(outer);
            drawPrintBorders(table, { id: `consumos-print-borders-${page.id}`, className: 'consumos-print-borders' });
        }
    }
    window.addEventListener('beforeprint', buildPrintPages);
    window.addEventListener('afterprint', () => {
        $('print-pages')?.querySelectorAll('table').forEach(table => clearPrintBorders(table, { id: table.parentElement.querySelector('svg')?.id }));
        $('print-pages')?.replaceChildren();
    });
    $('draft-discard').addEventListener('click', () => {
        const result = pendingDraft ? draftStore.discard({ revision: pendingDraft.revision }) : { ok: true };
        if (!result.ok) { associateDraft(); error('El borrador ha cambiado en otra pestaña. Revisa la copia actual antes de descartarla.'); return; }
        pendingDraft = null; $('draft-banner').hidden = true; lockDraftDecision(); writeDraft();
    });
    $('draft-restore').addEventListener('click', () => operation(async () => {
        const record = pendingDraft; const draft = record?.snapshot;
        const current = draftStore.read();
        if (!current || current.revision !== record?.revision) { associateDraft(); throw new Error('El borrador ha cambiado en otra pestaña. Revisa la copia actual antes de recuperarla.'); }
        if (!draft || Number(record.base_sheet_id || 0) !== Number(sheet.id || 0) || Number(record.base_version || 0) !== Number(sheet.version || 0)) throw new Error('La ficha del servidor ha cambiado. Este borrador no se aplicará sobre otra versión.');
        const restoredReport = draft.import_id ? (await request(`${urls.report}/${encodeURIComponent(draft.import_id)}`)).import : null;
        if (draftStore.read()?.revision !== record.revision) { associateDraft(); throw new Error('El borrador ha cambiado mientras se cargaba el informe. Revisa la copia actual antes de recuperarla.'); }
        changingInternally = true;
        try {
            report = restoredReport; $('title').value = draft.title;
            if (workflowEnabled && workflowMode === 'linking') skippedProducts = new Set((draft.skipped_product_ids || []).filter(id => pendingProducts.some(product => product.product_id === id)));
            pages = normalizePages(draft); activePageId = pages.some(page => page.id === draft.active_page_id) ? draft.active_page_id : pages[0].id;
            api.loadHtml(pages.find(page => page.id === activePageId).grid_html, { resetHistory: true }); api.setSavedHtml(savedGrid);
        }
        finally { changingInternally = false; }
        pendingDraft = null; $('draft-banner').hidden = true; lockDraftDecision(); renderSelects(); renderPages(); updateReview();
        if (workflowEnabled) workflowFocus = workflowMode === 'linking' ? 'pending' : 'workspace';
        api.status(workflowEnabled && workflowMode === 'document' ? 'Borrador recuperado. Revisa y guarda cada hoja por separado.' : 'Borrador recuperado. Revisa la ficha y guarda los cambios.');
    }));

    function hideMenus() {
        const context = document.getElementById('context-menu'); if (context) context.style.display = 'none';
        const more = document.getElementById('arqueo-more-options'); if (more) { more.hidden = true; more.classList.add('hidden'); }
    }
    function selection() {
        if (!api.editMode) throw new Error('Activa «Editar diseño» para cambiar el tipo de las casillas.');
        api.getHtml();
        const current = api.getSelection();
        const rectangle = selectionRectangle(current.layout, current.cells);
        if (!rectangle) throw new Error('Selecciona una casilla de la hoja.');
        return { ...current, rectangle, first: current.layout.map[rectangle.minR][rectangle.minC] };
    }
    function providerFor(cell, layout) {
        const position = layout.bounds.get(cell);
        return [...api.tbody.querySelectorAll('[data-consumo-provider]')].filter(header => {
            const b = layout.bounds.get(header);
            return b.maxR < position.minR && b.minC <= position.minC && b.maxC >= position.maxC;
        }).sort((a, b) => layout.bounds.get(b).minR - layout.bounds.get(a).minR)[0];
    }
    function makeProvider() {
        const current = selection(); const start = current.rectangle;
        const width = Math.max(12, start.maxC - start.minC + 1);
        const height = Math.max(2, start.maxR - start.minR + 1);
        const plans = planBlocks(current.layout, [{ minR: start.minR, maxR: start.minR + height - 1, minC: start.minC, maxC: start.minC + width - 1 }, ...rowRectangles(start.minR + height, start.minC, fieldWidths(width), 1)], new Set([current.first]));
        const name = text(current.first);
        let header;
        api.runTransaction(() => {
            const cells = applyBlocks(plans); header = cells[0]; header.dataset.consumoProvider = crypto.randomUUID();
            setText(header, name); header.style.backgroundColor = '#d4d4d4'; header.style.fontWeight = 'bold'; header.style.textAlign = 'center'; header.style.fontSize = '14px';
            cells.slice(1).forEach((cell, index) => { setText(cell, ['PRODUCTO', 'CONSUMO', 'STOCK', 'PEDIDO'][index]); cell.style.textAlign = 'center'; cell.style.fontWeight = 'bold'; cell.style.fontSize = '10px'; });
        });
        api.selectCell(header, false, true); api.status('Proveedor preparado. Escribe su nombre y pulsa una casilla hasta donde quieras llegar: «Producto y consumo» preparará todas las filas del bloque.');
    }
    function makeProduct() {
        const current = selection();
        const existingProduct = current.first.dataset.consumoProduct;
        const header = existingProduct
            ? [...api.tbody.querySelectorAll('[data-consumo-provider]')].find(cell => cell.dataset.consumoProvider === current.first.dataset.consumoSupplier)
            : providerFor(current.first, current.layout);
        if (!header) throw new Error('Selecciona cualquier casilla debajo del encabezado de un proveedor, dentro del ancho de su cuadro.');
        const providerBounds = current.layout.bounds.get(header);
        if (existingProduct && current.rectangle.minC > providerBounds.maxC && current.rectangle.minR >= providerBounds.minR && current.rectangle.maxR <= providerBounds.maxR) {
            openProduct(existingProduct); return;
        }
        const block = planProductRows(current.layout, header, current.rectangle, { namedCell: current.first, productHeight: Number($('side-height').value) });
        if (!block.rows.length) { if (existingProduct && !workflowEnabled) openProduct(existingProduct); else api.status('Esta fila ya está preparada como producto. Puedes editar su nombre o añadir otra fila debajo.'); return; }
        let firstProduct;
        api.runTransaction(() => {
            for (const row of block.rows) {
                const id = crypto.randomUUID();
                const cells = applyBlocks(row.plans);
                cells.forEach((cell, index) => { cell.dataset.consumoProduct = id; cell.dataset.consumoSupplier = header.dataset.consumoProvider; cell.dataset.consumoField = PRODUCT_FIELDS[index]; setText(cell, index === 0 ? row.name : ''); });
                cells[0].querySelector('.cell-content').style.fontSize = row.height === 1 ? '10px' : '12px';
                cells[1].dataset.consumoSources = '[]';
                firstProduct ||= cells[0];
            }
        });
        if (firstProduct) api.selectCell(firstProduct, false, true);
        api.status(workflowEnabled ? `Bloque preparado: ${block.rows.length} filas nuevas. Escribe sus nombres o deja las filas vacías para elegir sus productos al importar el PDF.` : `Bloque preparado: ${block.rows.length} filas nuevas. Rellena los nombres y pulsa ▾ en cada producto para elegir su consumo. Puedes guardar las filas vacías para completarlas después.`);
    }
    function removeType() {
        const current = selection();
        const cells = new Set(current.cells);
        for (const cell of [...cells]) {
            if (cell.dataset.consumoProvider) api.tbody.querySelectorAll('[data-consumo-supplier]').forEach(part => { if (part.dataset.consumoSupplier === cell.dataset.consumoProvider) cells.add(part); });
            if (cell.dataset.consumoProduct) productCells(cell.dataset.consumoProduct).forEach(part => cells.add(part));
        }
        api.runTransaction(() => cells.forEach(cell => { CONSUMO_ATTRIBUTES.forEach(attribute => cell.removeAttribute(attribute)); cell.querySelector('.consumos-cell-dropdown')?.remove(); }));
        api.status('Tipo especial quitado. Los textos, bordes y casillas siguen en la hoja y puedes editarlos libremente.');
    }
    window.consumosCellType = type => {
        hideMenus(); clearError();
        try { if (type === 'provider') makeProvider(); else if (type === 'product') makeProduct(); else if (type === 'free') removeType(); else if (type === 'side-provider') openSideBlock(); else if (type === 'order-table') openOrderBlock(); }
        catch (exception) { error(exception.message); }
    };

    const columnProfiles = {
        standard: ['product', 'consumo', 'stock', 'pedido'], stock: ['product', 'consumo', 'stock'],
        checks: ['product', 'consumo', 'stock', 'pedido', 'm', 'v'], quantity: ['quantity', 'product', 'consumo', 'stock', 'pedido'],
        'quantity-checks': ['quantity', 'product', 'consumo', 'stock', 'pedido', 'm', 'v'], packaging: ['product', 'stock', 'pedido'],
    };
    const columnLabels = BLOCK_COLUMN_LABELS;
    $('custom-block').addEventListener('click', () => {
        try { customSelection = selection(); hideMenus(); $('custom-error').hidden = true; $('custom-names').value = ''; $('custom-name').value = ''; $('custom-dialog').showModal(); $('custom-name').focus(); }
        catch (exception) { error(exception.message); }
    });
    $('custom-cancel').addEventListener('click', () => $('custom-dialog').close());
    $('custom-form').addEventListener('submit', event => {
        event.preventDefault();
        if (busy) return;
        try {
            const columns = columnProfiles[$('custom-columns').value];
            const title = $('custom-name').value.trim();
            if (columns.includes('consumo') && !title) throw new Error('Escribe el nombre del proveedor.');
            const entries = $('custom-names').value.split(/\r?\n/).map(line => line.trim()).filter(Boolean).map(line => {
                if (line.startsWith('#')) return { kind: 'category', name: line.slice(1).trim() };
                const split = columns.includes('quantity') ? line.indexOf('|') : -1;
                return split >= 0 ? { name: line.slice(split + 1).trim(), quantity: line.slice(0, split).trim() } : { name: line };
            });
            const width = customSelection.rectangle.maxC - customSelection.rectangle.minC + 1;
            const supplied = $('custom-widths').value.trim();
            const block = planCustomBlock(api.getLayout(), customSelection.rectangle, entries, { width: width > 1 ? width : 12, columns,
                title: title || false, rowHeight: Number($('custom-height').value), headerHeight: Number($('custom-header-height').value),
                ...(supplied ? { columnWidths: supplied.split(',').map(value => Number(value.trim())) } : {}) });
            let firstProduct;
            api.runTransaction(() => {
                const supplierId = crypto.randomUUID();
                if (block.providerPlan) {
                    const provider = applyBlocks([block.providerPlan])[0]; setText(provider, title);
                    provider.style.backgroundColor = '#d4d4d4'; provider.style.fontWeight = 'bold'; provider.style.textAlign = 'center'; provider.style.fontSize = '14px';
                    if (block.typed) { provider.dataset.consumoProvider = supplierId; provider.dataset.consumoColumns = columns.join(','); }
                }
                block.headerPlans.forEach(plan => {
                    const cell = applyBlocks([plan])[0]; setText(cell, columnLabels[plan.column]); cell.style.fontWeight = 'bold'; cell.style.textAlign = 'center'; cell.style.fontSize = '10px';
                });
                for (const row of block.rows) {
                    const id = crypto.randomUUID();
                    for (const plan of row.plans) {
                        const cell = applyBlocks([plan])[0]; setText(cell, plan.column === 'product' || plan.column === 'category' ? row.name : plan.column === 'quantity' ? row.quantity : '');
                        cell.style.fontSize = row.height === 1 ? '9px' : '11px'; cell.style.textAlign = plan.column === 'product' ? 'left' : 'center';
                        if (row.kind === 'category') { cell.style.backgroundColor = '#d4d4d4'; cell.style.fontWeight = 'bold'; }
                        else if (block.typed && PRODUCT_FIELDS.includes(plan.column)) {
                            cell.dataset.consumoProduct = id; cell.dataset.consumoSupplier = supplierId; cell.dataset.consumoField = plan.column;
                            if (plan.column === 'consumo') cell.dataset.consumoSources = '[]';
                            if (plan.column === 'product') firstProduct ||= cell;
                        }
                    }
                }
            });
            $('custom-dialog').close(); if (firstProduct) api.selectCell(firstProduct, false, true);
            api.status('Bloque preparado. Los nombres se han escrito y todos los consumos permanecen sin vínculo.');
        } catch (exception) { $('custom-error').textContent = exception.message; $('custom-error').hidden = false; }
    });
    $('fill-names').addEventListener('click', () => {
        try {
            const current = selection(); namesSupplier = current.first.dataset.consumoProvider || current.first.dataset.consumoSupplier || providerFor(current.first, current.layout)?.dataset.consumoProvider;
            const cells = [...api.tbody.querySelectorAll('[data-consumo-field="product"]')].filter(cell => cell.dataset.consumoSupplier === namesSupplier);
            if (!cells.length) throw new Error('Selecciona un proveedor que ya tenga filas de producto.');
            $('names-list').value = cells.map(text).join('\n'); $('names-error').hidden = true; hideMenus(); $('names-dialog').showModal(); $('names-list').focus();
        } catch (exception) { error(exception.message); }
    });
    $('names-cancel').addEventListener('click', () => $('names-dialog').close());
    $('names-form').addEventListener('submit', event => {
        event.preventDefault();
        try {
            const cells = [...api.tbody.querySelectorAll('[data-consumo-field="product"]')].filter(cell => cell.dataset.consumoSupplier === namesSupplier);
            const names = $('names-list').value.split(/\r?\n/);
            if (names.length > cells.length || names.some(name => name.trim().length > 255)) throw new Error('Los nombres deben caber en las filas disponibles y tener hasta 255 caracteres.');
            if (names.some((name, index) => !name.trim() && readSources(field(cells[index].dataset.consumoProduct, 'consumo')).length)) throw new Error('Los productos vinculados necesitan conservar un nombre.');
            api.runTransaction(() => names.forEach((name, index) => setText(cells[index], name.trim()))); $('names-dialog').close(); api.status('Nombres actualizados. Se conservan los consumos y vínculos.');
        } catch (exception) { $('names-error').textContent = exception.message; $('names-error').hidden = false; }
    });
    $('fit-text').addEventListener('click', () => {
        try { const current = selection(); let result; hideMenus(); api.runTransaction(() => { result = fitConsumoText(current.cells); }); api.status(result.unresolved ? `${result.unresolved} textos necesitan más espacio en la casilla.` : 'Texto ajustado sin cambiar datos ni vínculos.'); }
        catch (exception) { error(exception.message); }
    });

    function updateSidePreview() {
        $('side-preview-name').textContent = $('side-name').value.trim() || 'Proveedor';
        const count = Number($('side-count').value);
        $('side-preview-count').textContent = Number.isInteger(count) && count > 0 ? `${count} filas de producto` : 'Elige el número de productos';
    }
    function openSideBlock() {
        if (busy) return;
        const current = selection();
        const supplierId = current.first.dataset.consumoProvider || current.first.dataset.consumoSupplier;
        const provider = supplierId ? [...api.tbody.querySelectorAll('[data-consumo-provider]')].find(cell => cell.dataset.consumoProvider === supplierId) : null;
        let info = null;
        if (provider) {
            try { info = readSideBlock(current.layout, provider); } catch {
                throw new Error('Este proveedor ya usa otro diseño. Elige una zona libre para crear un proveedor a la izquierda.');
            }
        }
        const startRow = info ? current.layout.bounds.get(provider).minR : current.rectangle.minR + 1;
        const available = Math.floor((current.layout.height - startRow) / (info?.productHeight || 2));
        if (available < 1) throw new Error('Elige una casilla con espacio debajo para preparar el bloque de productos.');
        sideSelection = current; sideProvider = info ? provider : null; sideCreated = false;
        $('side-name').value = sideProvider ? text(sideProvider) : text(current.first);
        $('side-count').min = info?.productCount || 1;
        $('side-count').max = available;
        $('side-count').value = info?.productCount || Math.min(5, available);
        $('side-title').textContent = sideProvider ? 'Editar proveedor con productos' : 'Proveedor con productos';
        $('side-submit').textContent = sideProvider ? 'Guardar cambios' : 'Crear bloque';
        $('side-help').textContent = sideProvider ? 'Aumenta el número para añadir productos debajo. Se conservan los productos y sus consumos.' : 'Escribe el proveedor y sus productos. Verás la tabla en tu hoja antes de guardarla.';
        $('side-error').hidden = true; updateSidePreview();
        $('side-dialog').showModal(); $('side-name').focus(); $('side-name').select();
        $('side-names').value = sideProvider ? readSideBlock(api.getLayout(), sideProvider).rows.map(row => text(row.cells[0])).join('\n') : '';
        $('side-height').disabled = Boolean(sideProvider); $('side-headers').disabled = Boolean(sideProvider);
    }
    $('side-name').addEventListener('input', updateSidePreview);
    $('side-count').addEventListener('input', updateSidePreview);
    $('side-cancel').addEventListener('click', () => $('side-dialog').close());
    $('side-dialog').addEventListener('close', () => {
        if (!sideCreated && sideSelection?.first?.isConnected) api.selectCell(sideSelection.first, false, true);
    });
    $('side-form').addEventListener('submit', event => {
        event.preventDefault();
        if (busy) return;
        try {
            const name = $('side-name').value.trim();
            if (!name) throw new Error('Escribe el nombre del proveedor.');
            const count = Number($('side-count').value);
            const names = $('side-names').value.split(/\r?\n/).map(name => name.trim());
            if ($('side-names').value.trim() && (names.length > count || names.some(name => name.length > 255))) throw new Error('Los nombres deben caber en las filas indicadas y tener hasta 255 caracteres.');
            const layout = api.getLayout();
            const block = sideProvider
                ? planSideBlockExtension(layout, sideProvider, count)
                : planSideBlock(layout, sideSelection.rectangle, count, { namedCell: sideSelection.first, productHeight: Number($('side-height').value), headerHeight: Number($('side-headers').value) });
            const supplierId = sideProvider?.dataset.consumoProvider || crypto.randomUUID();
            const providerBorders = sideProvider ? ['b-top', 'b-bottom', 'b-left', 'b-right'].filter(border => sideProvider.classList.contains(border)) : null;
            let provider; let firstProduct;
            api.runTransaction(() => {
                if (block.cornerPlan) {
                    const corner = applyBlocks([block.cornerPlan])[0]; setText(corner, '');
                    corner.style.backgroundColor = '#d4d4d4'; corner.classList.remove('b-bottom');
                }
                provider = applyBlocks([block.providerPlan])[0];
                if (providerBorders) {
                    provider.classList.remove('b-top', 'b-bottom', 'b-left', 'b-right');
                    provider.classList.add(...providerBorders);
                }
                provider.dataset.consumoProvider = supplierId; setText(provider, name);
                if (!sideProvider) {
                    provider.style.backgroundColor = '#d4d4d4'; provider.style.fontWeight = 'bold'; provider.style.fontSize = '14px'; provider.style.textAlign = 'center'; provider.classList.remove('b-top');
                    applyBlocks(block.headerPlans).forEach((cell, index) => {
                        setText(cell, ['PRODUCTO', 'CONSUMO', 'STOCK', 'PEDIDO'][index]);
                        cell.style.backgroundColor = '#d4d4d4'; cell.style.fontWeight = 'bold'; cell.style.fontSize = '10px'; cell.style.textAlign = 'center';
                    });
                }
                for (const row of block.rows) {
                    const id = crypto.randomUUID(); const cells = applyBlocks(row.plans);
                    const nameIndex = (block.existingCount || 0) + block.rows.indexOf(row);
                    cells.forEach((cell, index) => { cell.dataset.consumoProduct = id; cell.dataset.consumoSupplier = supplierId; cell.dataset.consumoField = PRODUCT_FIELDS[index]; setText(cell, index === 0 ? names[nameIndex] || row.name : ''); });
                    cells[0].querySelector('.cell-content').style.fontSize = row.height === 1 ? '10px' : '12px';
                    cells[1].dataset.consumoSources = '[]'; firstProduct ||= cells[0];
                }
            });
            sideCreated = true; $('side-dialog').close();
            api.selectCell(firstProduct || provider, false, true);
            api.status(workflowEnabled ? `«${name}» preparado con ${count} productos. Escribe sus nombres; elegirás sus consumos al importar el PDF.` : `«${name}» preparado con ${count} productos. Escribe sus nombres y pulsa ▾ para elegir los consumos del PDF.`);
        } catch (exception) { $('side-error').textContent = exception.message; $('side-error').hidden = false; }
    });

    function orderNames() { return $('order-names').value.split(/\r?\n/).map(name => name.trim()).filter(Boolean); }
    function updateOrderPreview() {
        const count = orderNames().length;
        $('order-count').textContent = `${count} proveedores · Qué pedir · Hecho · Cuándo recibimos`;
    }
    function openOrderBlock() {
        if (busy) return;
        orderSelection = selection(); orderCreated = false;
        const defaultSuppliers = allSuppliers().map(supplier => supplier.name.split(/\r?\n/)[0].trim()).filter(Boolean);
        $('order-names').value = text(orderSelection.first).trim() || [...new Set(defaultSuppliers)].join('\n');
        $('order-request').value = '';
        $('order-error').hidden = true; updateOrderPreview();
        $('order-dialog').showModal(); $('order-names').focus(); $('order-names').setSelectionRange(0, 0); $('order-names').scrollTop = 0;
    }
    $('order-names').addEventListener('input', updateOrderPreview);
    $('order-cancel').addEventListener('click', () => $('order-dialog').close());
    $('order-dialog').addEventListener('close', () => {
        if (!orderCreated && orderSelection?.first?.isConnected) api.selectCell(orderSelection.first, false, true);
    });
    $('order-form').addEventListener('submit', event => {
        event.preventDefault();
        if (busy) return;
        try {
            const block = planOrderBlock(api.getLayout(), orderSelection.rectangle, orderNames(), { namedCell: orderSelection.first, rowHeight: Number($('order-height').value), headerHeight: Number($('order-header-height').value) });
            let firstRequest;
            api.runTransaction(() => {
                applyBlocks(block.headerPlans).forEach((cell, index) => {
                    setText(cell, ['PROVEEDOR', '¿QUÉ PEDIR?', '¿HECHO?', '¿CUÁNDO RECIBIMOS?'][index]);
                    cell.style.backgroundColor = '#d4d4d4'; cell.style.fontWeight = 'bold'; cell.style.fontSize = '10px'; cell.style.textAlign = 'center';
                });
                for (const row of block.rows) {
                    const cells = applyBlocks(row.plans);
                    cells.forEach((cell, index) => { setText(cell, index === 0 ? row.name : index === 1 ? $('order-request').value.trim() : ''); cell.style.textAlign = 'center'; cell.style.fontSize = '12px'; });
                    cells[0].style.backgroundColor = '#d4d4d4'; cells[0].style.fontWeight = 'bold';
                    firstRequest ||= cells[1];
                }
            });
            orderCreated = true; $('order-dialog').close(); api.selectCell(firstRequest, false, true);
            api.status('Tabla de pedidos y recepción preparada. Escribe los textos de Qué pedir, Hecho y Cuándo recibimos directamente en sus casillas.');
        } catch (exception) { $('order-error').textContent = exception.message; $('order-error').hidden = false; }
    });

    function renderMatches() {
        const suppliers = readSuppliers(api.tbody); const owners = sourceOwners(suppliers);
        const matches = searchRows(rows(), $('bind-search').value);
        const visible = matches.filter(row => !$('available-only').checked || !owners.has(row.id) || owners.get(row.id).productId === productId);
        $('match-count').textContent = `${visible.length} resultados de ${rows().length} filas del PDF`;
        $('bind-list').innerHTML = visible.map(row => {
            const owner = owners.get(row.id); const disabled = owner && owner.productId !== productId;
            return `<label class="consumos-bind-row"><input type="checkbox" value="${escape(row.id)}" ${selectedSources.has(row.id) ? 'checked' : ''} ${disabled ? 'disabled' : ''}><span><span class="consumos-match-heading"><strong>${escape(row.name)}</strong><b>${escape(amount(row))}</b></span><small>${escape(row.category)} · ${escape(row.group)} · Pág. ${escape(row.page)}${disabled ? ` · Ya elegido para ${escape(owner.label)}` : ''}</small></span></label>`;
        }).join('') || `<div class="consumos-no-matches"><strong>${report ? 'No hay coincidencias con esta búsqueda.' : 'Primero importa el PDF.'}</strong><p>${report ? 'Prueba otro nombre o abreviatura, como BBQ o barbacoa. El nombre de tu ficha se conserva.' : 'Después podrás elegir el consumo de este producto.'}</p>${report ? '<button type="button" id="consumos-show-all" class="arqueo-button">Ver todas las filas</button>' : ''}</div>`;
        $('show-all')?.addEventListener('click', () => { $('bind-search').value = ''; $('available-only').checked = false; renderMatches(); $('bind-search').focus(); });
        showSelection();
    }
    function showSelection() {
        const chosen = rows().filter(row => selectedSources.has(row.id));
        $('chosen-list').innerHTML = chosen.map(row => `<li><span><strong>${escape(row.name)}</strong> · ${escape(amount(row))}<small>${escape(row.group)} · Pág. ${escape(row.page)}</small></span><button type="button" data-remove-source="${escape(row.id)}" aria-label="Quitar ${escape(row.name)} de la selección">×</button></li>`).join('');
        $('chosen-list').hidden = !selectedSources.size;
        $('bind-submit').textContent = workflowEnabled ? 'Confirmar producto' : selectedSources.size ? 'Confirmar selección' : 'Guardar sin consumo';
        $('bind-next').textContent = 'Confirmar y siguiente';
        if (workflowEnabled) {
            $('bind-submit').disabled = !selectedSources.size;
            $('bind-next').disabled = !selectedSources.size;
        }
        try {
            const sum = validateBinding([...selectedSources], rows(), readSuppliers(api.tbody), productId);
            $('bind-selection').textContent = sum ? `${selectedSources.size} filas elegidas · Consumo: ${amount(sum)}` : 'Elige el producto del informe para añadir su consumo.';
            $('bind-error').hidden = true;
        } catch (exception) {
            $('bind-selection').textContent = 'Revisa las filas elegidas antes de confirmar.'; $('bind-error').textContent = exception.message; $('bind-error').hidden = false;
            if (workflowEnabled) { $('bind-submit').disabled = true; $('bind-next').disabled = true; }
        }
        positionPopup();
    }
    function positionPopup() {
        const popup = $('product-dropdown');
        if (!popup.matches(':popover-open') || !popupAnchor?.isConnected) return;
        const viewport = window.visualViewport;
        const left = viewport?.offsetLeft || 0; const top = viewport?.offsetTop || 0;
        const viewportWidth = viewport?.width || window.innerWidth; const viewportHeight = viewport?.height || window.innerHeight;
        popup.style.maxHeight = `${Math.max(120, viewportHeight - 24)}px`;
        popup.style.width = `${Math.min(480, viewportWidth - 24)}px`;
        const cellBounds = popupAnchor.getBoundingClientRect();
        const beside = workflowEnabled && workflowMode === 'linking' && cellBounds.right + popup.offsetWidth + 18 <= left + viewportWidth;
        popup.style.left = `${Math.max(left + 12, Math.min(beside ? cellBounds.right + 6 : cellBounds.left, left + viewportWidth - popup.offsetWidth - 12))}px`;
        popup.style.top = `${Math.max(top + 12, Math.min(beside ? cellBounds.top : cellBounds.bottom + 6, top + viewportHeight - popup.offsetHeight - 12))}px`;
    }
    function openProduct(id) {
        if (workflowEnabled && (workflowMode !== 'linking' || busy || pendingDraft || !pendingProducts.some(product => product.product_id === id))) return;
        hideMenus();
        activateProductPage(id);
        const nameCell = field(id, 'product'); if (!nameCell) return;
        api.getHtml();
        productId = id; selectedSources = new Set(readSources(field(id, 'consumo')));
        popupAnchor = workflowEnabled ? [...$('linking-list').querySelectorAll('[data-map-product]')].find(button => button.dataset.mapProduct === id) : nameCell;
        $('product-name').value = text(nameCell); $('bind-search').value = text(nameCell).trim();
        $('available-only').checked = false;
        const currentItem = pendingProducts.find(product => product.product_id === id);
        const supplierLabel = currentItem?.supplier_name?.trim() || '';
        $('product-title').textContent = `Revisar ${text(nameCell).trim() || 'nuevo producto'}${supplierLabel ? ` · ${supplierLabel}` : ''}`;
        $('bind-error').hidden = true;
        renderMatches();
        const popup = $('product-dropdown');
        if (!popup.matches(':popover-open')) popup.showPopover();
        if (workflowEnabled) popupAnchor?.setAttribute('aria-expanded', 'true');
        positionPopup();
        const input = workflowEnabled || text(nameCell).trim() ? $('bind-search') : $('product-name');
        input.focus({ preventScroll: true }); input.select();
    }
    window.consumosOpenProduct = () => {
        const current = api.getSelection(); const id = current.active?.dataset.consumoProduct;
        if (id) openProduct(id); else error('Marca primero una casilla como «Producto y consumo» para abrir su buscador.');
    };
    $('close-product').addEventListener('click', () => $('product-dropdown').hidePopover());
    $('product-dropdown').addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); $('product-dropdown').hidePopover(); return; }
        if (event.target === $('bind-search') && event.key === 'Enter') { event.preventDefault(); return; }
        if (['ArrowDown', 'ArrowUp'].includes(event.key) && (event.target === $('bind-search') || event.target.closest('#consumos-bind-list'))) {
            const inputs = [...$('bind-list').querySelectorAll('input:not(:disabled)')];
            const current = inputs.indexOf(document.activeElement);
            const next = current === -1 ? (event.key === 'ArrowDown' ? 0 : inputs.length - 1) : current + (event.key === 'ArrowDown' ? 1 : -1);
            if (inputs[next]) { event.preventDefault(); inputs[next].focus(); }
            else if (next < 0) { event.preventDefault(); $('bind-search').focus(); }
        }
    });
    $('product-dropdown').addEventListener('toggle', event => {
        if (event.newState === 'closed' && productId && !busy) {
            if (workflowEnabled) { popupAnchor?.setAttribute('aria-expanded', 'false'); popupAnchor?.focus({ preventScroll: true }); }
            else field(productId, 'product')?.querySelector('.cell-content').focus({ preventScroll: true });
        }
    });
    $('bind-search').addEventListener('input', () => { renderMatches(); positionPopup(); });
    $('available-only').addEventListener('change', renderMatches);
    $('chosen-list').addEventListener('click', event => { const button = event.target.closest('[data-remove-source]'); if (button) { selectedSources.delete(button.dataset.removeSource); renderMatches(); } });
    $('bind-list').addEventListener('change', event => { if (event.target.checked) selectedSources.add(event.target.value); else selectedSources.delete(event.target.value); showSelection(); });
    $('clear-selection').addEventListener('click', () => { selectedSources.clear(); renderMatches(); });
    $('bind-form').addEventListener('submit', event => {
        event.preventDefault();
        try {
            if (workflowEnabled && !selectedSources.size) throw new Error('Elige al menos una fila del PDF. Para dejar este producto sin consumo en esta hoja, cierra el buscador y marca «Dejar vacío en esta hoja».');
            validateBinding([...selectedSources], rows(), readSuppliers(api.tbody), productId);
            const originalName = pendingProducts.find(product => product.product_id === productId)?.product_name?.trim();
            const name = workflowEnabled && !originalName ? rows().find(row => selectedSources.has(row.id))?.name : $('product-name').value.trim() || rows().find(row => selectedSources.has(row.id))?.name;
            if (!name) throw new Error('Escribe un nombre para el producto.');
            skippedProducts.delete(productId);
            api.runTransaction(() => { setText(field(productId, 'product'), name); field(productId, 'consumo').dataset.consumoSources = JSON.stringify([...selectedSources]); });
            const sameSupplier = [...api.tbody.querySelectorAll('[data-consumo-field="product"]')].filter(cell => cell.dataset.consumoSupplier === field(productId, 'product').dataset.consumoSupplier);
            const next = workflowEnabled ? pendingProducts.find(product => !pendingState(product).resolved) : sameSupplier[sameSupplier.findIndex(cell => cell.dataset.consumoProduct === productId) + 1];
            const linked = selectedSources.size > 0;
            if (event.submitter?.id === 'consumos-bind-next' && next) openProduct(workflowEnabled ? next.product_id : next.dataset.consumoProduct);
            else $('product-dropdown').hidePopover();
            api.status(linked ? `«${name}» confirmado. Su consumo se ha añadido a la ficha.` : `«${name}» guardado sin consumo. La casilla queda vacía.`);
        } catch (exception) { $('bind-error').textContent = exception.message; $('bind-error').hidden = false; }
    });
    api.table.addEventListener('keydown', event => { if (!workflowEnabled && event.altKey && event.key === 'ArrowDown') { event.preventDefault(); event.stopPropagation(); window.consumosOpenProduct(); } });
    api.table.addEventListener('click', event => {
        const cell = event.target.closest('[data-consumo-field="product"]');
        if (!workflowEnabled && cell && !api.editMode && !event.target.closest('button')) openProduct(cell.dataset.consumoProduct);
    });
    window.addEventListener('resize', positionPopup);
    window.visualViewport?.addEventListener('resize', positionPopup);
    window.visualViewport?.addEventListener('scroll', positionPopup);
    $('source-search').addEventListener('input', updateReview);

    function confirmLeave() { return workflowEnabled || !dirty() || window.confirm('Hay cambios sin guardar en esta ficha. ¿Quieres descartarlos y abrir otra?'); }
    function load(nextSheet, nextReport) {
        templateUndo = null; templatePreview = null;
        writeDraft(); clearTimeout(changeTimer); changingInternally = true;
        try {
        if ($('product-dropdown').matches(':popover-open')) $('product-dropdown').hidePopover();
        productId = null; sheet = nextSheet; report = nextReport;
        pages = normalizePages(sheet); activePageId = pages[0].id; pageBodyCache.clear();
        $('title').value = sheet.title; savedExtra = extra();
        api.loadHtml(pages[0].grid_html, { markSaved: true, resetHistory: true });
        savedGrid = api.getHtml();
        savedPages = JSON.stringify(capturePages());
        } finally { changingInternally = false; }
        associateDraft();
        renderSelects(); renderPages(); updateReview(); api.status('Ficha cargada. Puedes escribir y organizar las casillas como en Arqueo.');
    }
    const controlMenus = [...document.querySelectorAll('.consumos-control-menu')];
    for (const menu of controlMenus) {
        menu.addEventListener('toggle', () => {
            if (menu.open) for (const other of controlMenus) if (other !== menu) other.open = false;
        });
        menu.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || !menu.open) return;
            event.preventDefault(); event.stopPropagation();
            menu.open = false;
            menu.querySelector('summary').focus({ preventScroll: true });
        });
    }
    document.addEventListener('click', event => {
        for (const menu of controlMenus) if (menu.open && !menu.contains(event.target)) menu.open = false;
    });
    function renderSectionNavigation() {
        if (!workflowEnabled) return;
        $('section-nav').hidden = workflowMode !== 'entry' || $('import-dialog').open;
    }
    function renderWorkflow() {
        if (!workflowEnabled) return;
        const documentMode = workflowMode === 'document';
        const sheetMode = workflowMode === 'sheet';
        $('app').dataset.consumosMode = workflowMode;
        renderSectionNavigation();
        $('workflow-entry').hidden = workflowMode !== 'entry';
        $('workflow-workspace').hidden = !documentMode && !sheetMode;
        $('workflow-back').hidden = !documentMode && !sheetMode;
        $('workflow-linking').hidden = workflowMode !== 'linking';
        // Recovery must stay in the active stage; the editor is hidden while
        // reviewing products, including when a pending import is resumed.
        const draftHost = $(workflowMode === 'linking' ? 'linking-draft-host' : 'workspace-draft-host');
        if (draftHost && $('draft-banner').parentElement !== draftHost) {
            draftHost.append($('draft-banner'), $('draft-status'));
        }
        document.getElementById('btn-edit-mode').hidden = workflowMode !== 'entry';
        $('import-button').hidden = workflowMode !== 'entry';
        const editLabel = document.getElementById('btn-edit-mode').querySelector('span');
        if (editLabel) editLabel.textContent = 'Editar documento';
        $('workflow-title').textContent = documentMode ? 'Editar documento' : 'Ficha de consumo';
        $('editor-intro')?.classList.toggle('sr-only', documentMode);
        $('workflow-description').innerHTML = documentMode ? 'Elige un espacio de la hoja y pulsa <strong>Crear tabla de proveedor</strong>. Añade sus productos y guarda cada hoja al terminar.' : 'Completa el stock y lo que necesitas pedir. Después, guarda o imprime la ficha.';
        $('draft-banner').hidden = workflowMode === 'entry' || !pendingDraft;
        $('sheet-select').disabled = documentMode;
        const saveButton = document.getElementById('btn-save-template');
        const saveLabel = saveButton?.querySelector('span') || saveButton;
        if (saveLabel) saveLabel.textContent = documentMode ? 'Guardar hoja' : 'Guardar ficha';
        if ($('save-sheet')) $('save-sheet').textContent = documentMode ? 'Guardar hoja' : 'Guardar ficha';
        if (workflowMode === 'linking') renderPendingProducts();
        renderPages();
    }
    function setWorkflow(mode) {
        for (const menu of controlMenus) menu.open = false;
        workflowMode = mode;
        if (api.editMode !== (mode === 'document')) window.toggleEditMode();
        renderWorkflow();
        api.refreshBindings();
    }
    function workflowUrl(name, importId) {
        const url = urls[name];
        if (!url) throw new Error('No está disponible la preparación de consumos. Recarga la página.');
        return url.replace('__IMPORT__', encodeURIComponent(importId)).replace('{import}', encodeURIComponent(importId));
    }
    function savedImportPageIds() {
        return consumptionDocument.pages?.length ? consumptionDocument.pages.map(page => page.id) : null;
    }
    function isImportSubset(ids = activeImportPageIds) {
        const all = savedImportPageIds();
        return Array.isArray(ids) && Array.isArray(all) && (ids.length !== all.length || ids.some(id => !all.includes(id)));
    }
    function importSelectionSuffix() {
        // The document ID and version already scope the draft. Compact saved-page
        // positions keep keys below the recovery store limit even with 20 pages.
        return isImportSubset() ? `:pages:${savedImportPageIds().flatMap((id, index) => activeImportPageIds.includes(id) ? [index + 1] : []).join(',')}` : '';
    }
    function pendingImportRecord() {
        return { document_id: consumptionDocument.id, document_version: consumptionDocument.version, import_id: report.id,
            ...(reviewAllBindings ? { review_all: true } : {}), ...(isImportSubset() ? { page_ids: [...activeImportPageIds] } : {}) };
    }
    function updateImportPageSummary() {
        if (!workflowEnabled || !$('import-pages-summary')) return;
        const selected = importPageChoices.filter(page => selectedImportPageIds.has(page.id));
        const count = selected.reduce((sum, page) => sum + page.products, 0);
        $('import-pages-count').textContent = `${selected.length} de ${importPageChoices.length} hojas a utilizar`;
        $('import-pages-summary').textContent = selected.length ? `${selected.length} de ${importPageChoices.length} hojas seleccionadas · ${count} productos para revisar.` : 'Selecciona al menos una hoja para continuar.';
        $('import-submit').disabled = busy || !selected.length;
        $('use-existing').disabled = busy || !selected.length || !$('existing-import').value;
    }
    function renderImportPages() {
        if (!$('import-pages-fieldset')) return;
        $('import-pages-fieldset').hidden = !workflowEnabled;
        if (!workflowEnabled) return;
        $('import-pages-disclosure').open = false;
        importPageChoices = normalizePages(consumptionDocument).map(page => {
            const body = document.createElement('tbody'); body.innerHTML = page.grid_html;
            return { id: page.id, title: page.title, products: body.querySelectorAll('[data-consumo-field="product"]').length };
        });
        selectedImportPageIds = new Set(importPageChoices.map(page => page.id));
        $('import-pages-list').innerHTML = importPageChoices.map(page => `<label class="consumos-import-page"><input type="checkbox" value="${escape(page.id)}" checked><span><strong>${escape(page.title)}</strong><small>${page.products} productos</small></span></label>`).join('');
        updateImportPageSummary();
    }
    function chosenImportPageIds() {
        if (!workflowEnabled) return null;
        const selected = importPageChoices.filter(page => selectedImportPageIds.has(page.id)).map(page => page.id);
        if (!selected.length) throw new Error('Selecciona al menos una hoja para importar el consumo.');
        // Legacy single-sheet documents did not expose stable page IDs.
        return savedImportPageIds() ? selected : null;
    }
    $('import-pages-list')?.addEventListener('change', event => {
        const input = event.target.closest('input[type=checkbox]');
        if (!input || busy || !importPageChoices.some(page => page.id === input.value)) return;
        if (input.checked) selectedImportPageIds.add(input.value); else selectedImportPageIds.delete(input.value);
        updateImportPageSummary();
    });
    function readPendingImport() {
        try {
            const raw = window.localStorage.getItem(pendingImportKey);
            if (!raw || raw.length > 2048) return null;
            const value = JSON.parse(raw);
            if (!value || !['document_id', 'document_version', 'import_id'].every(key => Number.isSafeInteger(value[key]) && value[key] > 0)) return null;
            if (value.page_ids !== undefined && (!Array.isArray(value.page_ids) || !value.page_ids.length || value.page_ids.length > 20
                || new Set(value.page_ids).size !== value.page_ids.length || value.page_ids.some(id => typeof id !== 'string' || !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(id)))) return null;
            return { document_id: value.document_id, document_version: value.document_version, import_id: value.import_id, ...(value.review_all === true ? { review_all: true } : {}), ...(value.page_ids ? { page_ids: value.page_ids } : {}) };
        } catch { return null; }
    }
    function rememberPendingImport() {
        try { window.localStorage.setItem(pendingImportKey, JSON.stringify(pendingImportRecord())); }
        catch { /* The active import and its links remain usable if browser storage is unavailable. */ }
    }
    function forgetPendingImport(expected = null) {
        try {
            if (expected && JSON.stringify(readPendingImport()) !== JSON.stringify(expected)) return;
            window.localStorage.removeItem(pendingImportKey);
        } catch { /* Storage failure must not undo a confirmed server save. */ }
    }
    function renderResumeImport() {
        updateImportPageSummary();
        const pending = readPendingImport();
        $('resume-import-panel').hidden = !pending;
        if (!pending) return;
        const stale = pending.document_id !== consumptionDocument.id || pending.document_version !== consumptionDocument.version;
        const titles = pending.page_ids ? (consumptionDocument.pages || []).filter(page => pending.page_ids.includes(page.id)).map(page => page.title) : [];
        $('resume-import-message').textContent = stale ? 'Las hojas han cambiado desde que empezaste esta revisión. Su borrador se conserva; descarta este aviso para importar con las hojas actuales.' : `Tienes un informe con productos pendientes de revisar.${titles.length ? ` Hojas: ${titles.join(', ')}.` : ''} Continúa desde donde lo dejaste, sin subir el PDF otra vez.`;
        $('resume-import').disabled = stale;
    }
    function pendingState(product) {
        const sourceIds = readSources(field(product.product_id, 'consumo'));
        return { sourceIds, skipped: skippedProducts.has(product.product_id), resolved: sourceIds.length > 0 || skippedProducts.has(product.product_id) };
    }
    function renderPendingProducts() {
        if (!workflowEnabled || !$('linking-list')) return;
        let resolved = 0;
        const openId = productId;
        const groups = [];
        for (const [index, product] of pendingProducts.entries()) {
            const page = pages.find(p => p.id === product.page_id) || productPage(product.product_id);
            const supplierName = product.supplier_name || 'Proveedor';
            const pageTitle = page?.title || 'Hoja';
            const groupKey = `${page?.id || product.page_id || ''}::${product.supplier_id || supplierName}`;
            let group = groups.at(-1);
            if (!group || group.key !== groupKey) {
                group = { key: groupKey, supplierName, pageTitle, items: [] };
                groups.push(group);
            }
            group.items.push({ product, index });
        }
        $('linking-list').innerHTML = groups.map(group => {
            let groupResolved = 0;
            const rowsHtml = group.items.map(({ product }) => {
                const state = pendingState(product);
                if (state.resolved) { resolved++; groupResolved++; }
                const name = text(field(product.product_id, 'product')).trim() || 'Nueva fila de producto';
                const chosen = rows().filter(row => state.sourceIds.includes(row.id));
                const status = state.skipped ? 'Revisado · Sin consumo en esta hoja' : chosen.length ? `Revisado · ${chosen.map(row => `${row.name} · ${amount(row)}`).join(' + ')}` : 'Pendiente · Elegir producto del informe';
                return `<div class="consumos-linking-item"><button type="button" data-map-product="${escape(product.product_id)}" class="consumos-linking-product ${state.resolved ? 'is-linked' : ''}" aria-haspopup="dialog" aria-expanded="${openId === product.product_id && $('product-dropdown').matches(':popover-open')}"><strong>${escape(name)}</strong><span>${escape(status)}</span></button><label class="consumos-linking-skip"><input type="checkbox" data-skip-product="${escape(product.product_id)}" ${state.skipped ? 'checked' : ''}> Dejar vacío en esta hoja</label></div>`;
            }).join('');
            const allDone = groupResolved === group.items.length;
            return `<section class="consumos-supplier-group ${allDone ? 'is-group-complete' : ''}" aria-label="Proveedor ${escape(group.supplierName)}"><header class="consumos-supplier-group-header"><div class="consumos-supplier-group-title"><span class="consumos-supplier-badge">Proveedor</span><h4>${escape(group.supplierName)}</h4><small>${escape(group.pageTitle)}</small></div><span class="consumos-supplier-progress ${allDone ? 'is-complete' : ''}">${groupResolved} de ${group.items.length} revisados</span></header><div class="consumos-supplier-group-items">${rowsHtml}</div></section>`;
        }).join('');
        $('linking-count').textContent = 'Revisa los productos';
        $('linking-progress').textContent = `${resolved} de ${pendingProducts.length} revisados. ${reviewAllBindings ? 'Revisa cada producto de las hojas seleccionadas.' : 'Las elecciones guardadas ya se han aplicado.'}`;
        const meter = $('linking-meter');
        if (meter) { meter.max = Math.max(1, pendingProducts.length); meter.value = resolved; }
        $('linking-save').disabled = resolved < pendingProducts.length;
        if (openId) popupAnchor = [...$('linking-list').querySelectorAll('[data-map-product]')].find(button => button.dataset.mapProduct === openId) || popupAnchor;
    }
    async function enterDocument() {
        if (busy) return;
        await operation(async () => {
            const next = (urls.documentShow ? (await request(urls.documentShow)).document : consumptionDocument) || emptyDocument();
            consumptionDocument = next;
            setWorkflow('document');
            load(next, null);
            renderWorkflow();
            workflowFocus = 'workspace';
            api.status('');
        }, $('workflow-error'));
    }
    async function completeImport() {
        const links = pendingProducts.map(product => {
            const state = pendingState(product);
            return { product_id: product.product_id, source_ids: state.sourceIds, ...(state.skipped ? { skip: true } : {}), ...(state.sourceIds.length ? { product_name: text(field(product.product_id, 'product')).trim() } : {}) };
        });
        if (links.some(link => !link.source_ids.length && !link.skip)) throw new Error('Elige el producto del informe para cada fila pendiente o marca «Dejar vacío en esta hoja».');
        const currentStore = draftStore;
        const draftResult = writeDraft();
        const completedPointer = pendingImportRecord();
        const result = await request(workflowUrl('complete', report.id), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ document_id: consumptionDocument.id, document_version: consumptionDocument.version, title: $('title').value, links, ...(reviewAllBindings ? { review_all: true } : {}), ...(activeImportPageIds ? { page_ids: activeImportPageIds } : {}) }) });
        if (!result.sheet?.id || !result.document?.id) throw new Error('No se ha podido confirmar la ficha generada. Revisa el historial antes de repetir.');
        consumptionDocument = result.document;
        forgetPendingImport(completedPointer);
        if (draftResult?.ok) currentStore?.discard({ revision: draftResult.draft.revision });
        // The saved operational sheet owns its own recovery store, separate from the reusable document.
        draftStore = null;
        sheets = [result.sheet, ...sheets.filter(item => String(item.id) !== String(result.sheet.id))];
        await refreshHistory();
        pendingProducts = []; skippedProducts.clear();
        setWorkflow('sheet');
        load(result.sheet, report);
        renderWorkflow();
        workflowFocus = 'workspace';
        api.status('Ficha de consumo preparada. Completa el stock y lo que necesitas pedir; después, guarda o imprime.');
    }
    async function prepareImport(nextReport, selectedPageIds = null) {
        const requestedPages = selectedPageIds || chosenImportPageIds();
        const result = await request(workflowUrl('prepare', nextReport.id), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ document_id: consumptionDocument.id, document_version: consumptionDocument.version, ...(reviewAllBindings ? { review_all: true } : {}), ...(requestedPages ? { page_ids: requestedPages } : {}) }) });
        if (!result.sheet || !result.document || !Array.isArray(result.pending)) throw new Error('No se han podido preparar los productos para revisar. Vuelve a intentarlo.');
        consumptionDocument = result.document;
        activeImportPageIds = result.page_ids || result.sheet.page_ids || requestedPages;
        pendingProducts = result.pending; skippedProducts = new Set();
        setWorkflow('linking');
        load(result.sheet, result.import || nextReport);
        rememberPendingImport();
        renderWorkflow();
        workflowFocus = 'pending';
        $('linking-error').hidden = true;
        if (!pendingProducts.length && !pendingDraft) await completeImport();
    }
    if (workflowEnabled) {
        document.getElementById('btn-edit-mode').addEventListener('click', enterDocument);
        $('workflow-back').addEventListener('click', () => {
            if (busy) return;
            writeDraft();
            if ($('product-dropdown').matches(':popover-open')) $('product-dropdown').hidePopover();
            pendingDraft = null;
            setWorkflow('entry');
            lockDraftDecision();
            $('workflow-error').hidden = true;
            document.getElementById('btn-edit-mode').focus();
        });
        $('linking-back').addEventListener('click', () => {
            if (busy || pendingDraft) return;
            writeDraft();
            if ($('product-dropdown').matches(':popover-open')) $('product-dropdown').hidePopover();
            setWorkflow('entry');
            $('import-button').focus();
        });
        $('linking-list').addEventListener('click', event => {
            const button = event.target.closest('[data-map-product]');
            if (button && !busy && !pendingDraft) openProduct(button.dataset.mapProduct);
        });
        $('linking-list').addEventListener('change', event => {
            const id = event.target.dataset.skipProduct;
            if (!id || busy || pendingDraft) return;
            activateProductPage(id);
            if (event.target.checked) {
                skippedProducts.add(id);
                api.runTransaction(() => { field(id, 'consumo').dataset.consumoSources = '[]'; });
            } else skippedProducts.delete(id);
            changed();
        });
        $('linking-save').addEventListener('click', () => operation(completeImport, $('linking-error')));
        $('resume-import').addEventListener('click', () => operation(async () => {
            const pending = readPendingImport();
            if (!pending) throw new Error('No hay una importación pendiente en este navegador.');
            const current = (await request(urls.documentShow)).document;
            if (!current || current.id !== pending.document_id || current.version !== pending.document_version) {
                if (current) { consumptionDocument = current; renderImportPages(); }
                renderResumeImport();
                throw new Error('El documento ha cambiado. El borrador anterior se conserva y no se aplicará sobre otra versión. Descarta el aviso para importar con el documento actual.');
            }
            consumptionDocument = current;
            const restored = (await request(`${urls.report}/${encodeURIComponent(pending.import_id)}`)).import;
            if (!restored?.rows?.length) throw new Error('No se ha podido recuperar ese PDF. Su borrador se conserva.');
            $('import-dialog').close();
            await prepareImport(restored, pending.page_ids || savedImportPageIds());
        }, $('import-error')));
        $('resume-discard').addEventListener('click', () => { if (!busy) { forgetPendingImport(readPendingImport()); renderResumeImport(); $('pdf').focus(); } });
    }
    $('title').addEventListener('input', () => { api.status('Cambios sin guardar en el nombre de la ficha.'); changed(); });
    $('sheet-select').addEventListener('change', event => {
        const id = event.target.value;
        if (busy || !confirmLeave()) { renderSelects(); return; }
        operation(async () => {
            if (!id) { if (workflowEnabled) setWorkflow('entry'); else load({ id: null, version: null, title: 'Ficha de consumos', suppliers: [], grid_html: null }, null); return; }
            const result = await request(`${urls.sheet}/${encodeURIComponent(id)}`);
            const nextReport = result.sheet.import_id ? (await request(`${urls.report}/${encodeURIComponent(result.sheet.import_id)}`)).import : null;
            if (workflowEnabled) setWorkflow('sheet');
            load(result.sheet, nextReport);
        });
    });
    function applyReport(nextReport) {
        if (api.tbody.querySelector('[data-consumo-sources]:not([data-consumo-sources="[]"])') && !window.confirm('Cambiar de PDF quitará los vínculos actuales. El diseño, los productos, stock y pedido se conservan. Tendrás que elegir los nuevos consumos manualmente. ¿Continuar?')) return false;
        report = nextReport;
        api.runTransaction(() => api.tbody.querySelectorAll('[data-consumo-field="consumo"]').forEach(cell => { cell.dataset.consumoSources = '[]'; }));
        const cleared = api.getHtml();
        api.loadHtml(cleared, { resetHistory: true });
        api.setSavedHtml(savedGrid);
        api.refreshBindings(); renderSelects(); updateReview(); return true;
    }
    $('report-select').addEventListener('change', event => {
        const id = event.target.value;
        if (busy) { renderSelects(); return; }
        operation(async () => { const next = id ? (await request(`${urls.report}/${encodeURIComponent(id)}`)).import : null; if (!applyReport(next)) renderSelects(); });
    });
    $('use-existing')?.addEventListener('click', () => operation(async () => {
        const selectedPages = chosenImportPageIds();
        const id = $('existing-import').value;
        if (!id) throw new Error('Elige un consumo ya importado.');
        const next = (await request(`${urls.report}/${encodeURIComponent(id)}`)).import;
        if (!next?.rows?.length) throw new Error('Este consumo no contiene filas. Elige otro informe.');
        if (workflowEnabled) {
            consumptionDocument = (await request(urls.documentShow)).document || consumptionDocument;
            $('import-dialog').close(); await prepareImport(next, selectedPages);
        } else { $('import-dialog').close(); applyReport(next); }
    }, $('import-error')));
    $('existing-import')?.addEventListener('change', () => { if (workflowEnabled) updateImportPageSummary(); else $('use-existing').disabled = busy || !$('existing-import').value; });
    const uploadLimit = initialData.upload_limit_bytes || 20 * 1024 * 1024;
    const uploadMb = Number((uploadLimit / 1024 / 1024).toFixed(2));
    $('upload-limit').textContent = `PDF de hasta ${uploadMb} MB. Debe poder leerse completo.`;
    $('import-button').addEventListener('click', async () => {
        if (workflowEnabled && !consumptionDocument.id) {
            await enterDocument();
            api.status('Primero prepara y guarda una hoja con tus productos. Después podrás importar el PDF y revisar sus consumos.');
            return;
        }
        $('import-error').hidden = true;
        renderImportPages();
        if (workflowEnabled) renderResumeImport();
        updateImportPageSummary();
        $('import-dialog').showModal();
        renderSectionNavigation();
    });
    $('cancel-import').addEventListener('click', () => { if (!busy) $('import-dialog').close(); });
    $('import-dialog').addEventListener('close', () => { if (!busy) renderSectionNavigation(); });
    $('import-dialog').addEventListener('cancel', event => { if (busy) event.preventDefault(); });
    $('pdf')?.addEventListener('change', () => {
        const file = $('pdf').files?.[0];
        if ($('pdf-label')) $('pdf-label').textContent = file ? file.name : 'Cargar PDF de consumo';
        if (file && !busy && !$('import-submit').disabled) {
            $('import-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        }
    });
    $('import-form').addEventListener('submit', event => {
        event.preventDefault(); const file = $('pdf').files[0]; if (!file) return;
        let selectedPages;
        try { selectedPages = chosenImportPageIds(); } catch (exception) { $('import-error').textContent = exception.message; $('import-error').hidden = false; return; }
        if (file.size > uploadLimit) { $('import-error').textContent = `El PDF supera los ${uploadMb} MB que permite este servidor.`; $('import-error').hidden = false; return; }
        const data = new FormData(); data.append('pdf', file);
        operation(async () => {
            $('import-submit').textContent = 'Leyendo todas las filas…';
            let result; try { result = await request(urls.import, { method: 'POST', body: data }); } finally { $('import-submit').textContent = workflowEnabled ? 'Cargar PDF y revisar' : 'Importar todas las filas'; }
            if (!Array.isArray(result.import?.rows) || !result.import.rows.length) throw new Error('La respuesta del importador no incluye sus filas. Tu ficha conserva sus vínculos; revisa las importaciones antes de repetir la operación.');
            reports = [result.import, ...reports]; $('import-dialog').close(); $('import-form').reset();
            if ($('pdf-label')) $('pdf-label').textContent = 'Cargar PDF de consumo';
            if (workflowEnabled) await prepareImport(result.import, selectedPages);
            else {
                if (!applyReport(result.import)) renderSelects();
                api.status(`PDF importado: ${result.import.rows.length} filas completas. Busca y elige los vínculos desde cada producto.`);
            }
        }, $('import-error'));
    });
    if (workflowEnabled && initialData.sheet && new URL(window.location.href).searchParams.has('ficha')) {
        setWorkflow('sheet'); load(initialData.sheet, initialData.activeImport || null);
    }
    window.addEventListener('beforeunload', event => { if (dirty()) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('pagehide', writeDraft);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startConsumos);
else startConsumos();
