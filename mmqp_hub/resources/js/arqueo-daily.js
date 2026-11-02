/** A template keeps labels and bindings; the daily record contains only input values. */
export function templateDefinitionHtml(html, document) {
    const table = document.createElement('table');
    table.innerHTML = `<tbody>${html}</tbody>`;
    table.querySelectorAll('td[data-arqueo-input="true"]').forEach(cell => {
        (cell.querySelector('.cell-content') || cell).replaceChildren();
    });
    return table.tBodies[0].innerHTML;
}

export function dailyValues(html, document) {
    const table = document.createElement('table');
    table.innerHTML = `<tbody>${html}</tbody>`;
    const values = {};
    Array.from(table.rows).forEach((row, r) => Array.from(row.cells).forEach((cell, c) => {
        if (cell.dataset.arqueoInput !== 'true' || cell.hasAttribute('data-bound-emp') || cell.hasAttribute('data-bound-date')) return;
        const content = cell.querySelector('.cell-content') || cell;
        // The editor uses plaintext-only; retain line breaks from legacy pasted markup too.
        const plain = node => node.nodeType === 3 ? node.textContent : node.nodeName === 'BR' ? '\n' : Array.from(node.childNodes).map(plain).join('') + (['DIV', 'P'].includes(node.nodeName) && node !== content ? '\n' : '');
        const value = plain(content).replace(/\n$/, '');
        if (value) values[`${r}-${c}`] = value;
    }));
    return values;
}

export function initializeArqueoDaily(config, document = window.document) {
    const button = document.getElementById('btn-save-daily');
    const api = document.defaultView.arqueoSheet;
    if (!button || !api || button.dataset.dailyReady || !config.dailyMode) return null;
    button.dataset.dailyReady = 'true';
    config.savedTemplateHtml = api.getTemplateHtml();
    let saving = false;
    const update = () => {
        button.disabled = saving || api.editMode || !config.templateHash || config.dailyAvailable === false;
        button.setAttribute('aria-busy', String(saving));
    };
    api.table.addEventListener('arqueo:changed', update);
    button.addEventListener('click', async () => {
        if (saving || api.editMode || !config.templateHash || config.dailyAvailable === false) return;
        const html = api.getHtml();
        if (templateDefinitionHtml(html, document) !== config.savedTemplateHtml) {
            api.status('La plantilla tiene cambios sin guardar. Guárdala desde «Editar documento» antes de rellenar el arqueo.', true);
            return;
        }
        const templateHash = config.templateHash;
        saving = true;
        update();
        api.status('Guardando arqueo del día…');
        try {
            const response = await document.defaultView.fetch(config.dailySaveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
                body: JSON.stringify({ fecha: config.fecha, templateHash, values: dailyValues(html, document) }),
            });
            const result = await response.json().catch(() => null);
            if (!response.ok || result?.success !== true) throw new Error(response.status === 419 ? 'Tu sesión ha caducado. Conserva esta pestaña y vuelve a iniciar sesión antes de guardar.' : result?.message || 'No se ha podido guardar el arqueo. Tu trabajo sigue en esta pestaña; vuelve a intentarlo.');
            api.setSavedHtml(html);
            api.status(api.getHtml() === html ? 'Arqueo guardado para esta fecha. Puedes imprimirlo. El próximo día comenzará con los importes vacíos.' : 'Arqueo guardado. Hay cambios posteriores pendientes de guardar.');
        } catch (error) {
            api.status(error instanceof TypeError ? 'No se pudo conectar para guardar. Tu trabajo sigue en esta pestaña; vuelve a intentarlo.' : error.message, true);
        } finally { saving = false; update(); }
    });
    update();
    return { get saving() { return saving; } };
}

if (typeof window !== 'undefined') {
    const boot = () => { if (window.arqueoConfig) initializeArqueoDaily(window.arqueoConfig); };
    document.addEventListener('arqueo:ready', boot);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
}
