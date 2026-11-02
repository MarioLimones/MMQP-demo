import { createTemplateFile, readTemplateFile, MAX_TEMPLATE_FILE_BYTES } from './arqueo-template-file.js';

export function downloadTemplate(text, filename, document = window.document) {
    const view = document.defaultView;
    const blob = new view.Blob([text], { type: 'application/json;charset=utf-8' });
    const url = view.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.hidden = true;
    document.body.append(link);
    try { link.click(); }
    finally {
        link.remove();
        view.setTimeout(() => view.URL.revokeObjectURL(url), 1000);
    }
}

export function initializeTemplateTransfer(config, document = window.document) {
    const byId = id => document.getElementById(id);
    const exportButton = byId('btn-export-template');
    const importButton = byId('btn-import-template');
    const fileInput = byId('arqueo-template-file');
    if (!exportButton || !importButton || !fileInput || fileInput.dataset.transferReady) return null;
    fileInput.dataset.transferReady = 'true';
    const view = document.defaultView;
    const options = { enableImportGeometry: config.enableImportGeometry === true };
    let sequence = 0;
    let reading = false;
    const editor = () => view.arqueoSheet;
    const status = (message, error = false) => editor()?.status(message, error);

    exportButton.addEventListener('click', () => {
        const api = editor();
        if (!api) return;
        try {
            const text = createTemplateFile(api.getTemplateHtml?.() ?? api.getHtml(), document, options);
            const date = /^\d{4}-\d{2}-\d{2}$/.test(config.fecha || '') ? `-${config.fecha}` : '';
            downloadTemplate(text, `plantilla-arqueo${date}.json`, document);
            status('Plantilla exportada. Guarda el archivo en tu dispositivo para recuperarla con «Importar plantilla».');
        } catch (error) {
            status(error.message || 'No se ha podido exportar la plantilla. Tu diseño se conserva.', true);
        }
    });
    importButton.addEventListener('click', () => { fileInput.value = ''; fileInput.click(); });
    fileInput.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        const api = editor();
        if (!file || !api) return;
        const current = ++sequence;
        try {
            if (!/\.json$/i.test(file.name)) throw new Error('Selecciona una plantilla JSON exportada desde Arqueos.');
            if (!file.size) throw new Error('El archivo está vacío. Selecciona otra plantilla.');
            if (file.size > MAX_TEMPLATE_FILE_BYTES) throw new Error('La plantilla supera los 2 MB permitidos.');
            const previous = api.getHtml();
            reading = true;
            importButton.setAttribute('aria-busy', 'true');
            status('Leyendo la plantilla…');
            const text = await file.text();
            if (current !== sequence) return;
            const html = readTemplateFile(text, document, options);
            if (api.getHtml() !== previous) throw new Error('La hoja ha cambiado mientras se leía el archivo. Selecciona la plantilla de nuevo para importarla.');
            api.loadHtml(html);
            if (!api.editMode) view.toggleEditMode();
            status('Plantilla importada. Puedes editarla, rellenarla o guardarla. «Deshacer» recupera la hoja anterior.');
            byId('btn-save-template')?.focus({ preventScroll: true });
        } catch (error) {
            if (current !== sequence) return;
            status(error.message || 'No se ha podido leer la plantilla. Tu hoja se conserva.', true);
        } finally {
            if (current === sequence) {
                reading = false;
                importButton.removeAttribute('aria-busy');
            }
        }
    });
    return { get reading() { return reading; } };
}

if (typeof window !== 'undefined') {
    const boot = () => { if (window.arqueoConfig) initializeTemplateTransfer(window.arqueoConfig); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
}
