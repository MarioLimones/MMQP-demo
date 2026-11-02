import { sanitizeTemplate, templateSnapshot, buildGridMap } from './arqueo-editor.js';
import { validateMarkup, validateGrid } from './arqueo-template-file.js';
import { CONSUMO_ATTRIBUTES, readSuppliers } from './consumos-grid.js';

export const MAX_CONSUMO_TEMPLATE_BYTES = 2 * 1024 * 1024;
const FORMAT = 'mmqp.consumos-template';
const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const bytes = value => new TextEncoder().encode(value).length;
const invalid = message => { throw new Error(message); };
const options = { cellAttributes: CONSUMO_ATTRIBUTES, isBound: cell => cell.dataset.consumoField === 'consumo' };

function cleanPage(html, document, identities) {
    if (typeof html !== 'string' || !html.trim() || bytes(html) > 1024 * 1024) invalid('Cada hoja debe contener un diseño válido de hasta 1 MB.');
    try { validateMarkup(html); } catch { invalid('La plantilla contiene una estructura incompleta o contenido no permitido.'); }
    const fragment = document.createElement('template');
    fragment.innerHTML = `<table><tbody>${html}</tbody></table>`;
    const body = fragment.content.querySelector('tbody');
    validateGrid(body);
    const layout = buildGridMap([...body.rows]);
    if (layout.width !== 24 || layout.height !== 73) invalid('Cada hoja debe conservar la cuadrícula A4 de 24 columnas y 73 filas.');
    for (const node of body.querySelectorAll('*')) {
        for (const attribute of node.attributes) {
            if (/^on/i.test(attribute.name) || /^(href|src|srcset|srcdoc|action|formaction|xlink:href)$/i.test(attribute.name)
                || (attribute.name === 'style' && /[\\<>@]|url\s*\(|expression\s*\(|javascript\s*:|\/\*/i.test(attribute.value))) invalid('La plantilla contiene acciones, enlaces o estilos no permitidos.');
            if (attribute.name.startsWith('data-') && (!CONSUMO_ATTRIBUTES.includes(attribute.name) || node.tagName !== 'TD')) invalid('La plantilla contiene datos ajenos al diseño de Consumos.');
        }
    }
    const clear = cell => {
        const content = cell.querySelector('.cell-content');
        if (content) content.textContent = '';
        else { cell.textContent = ''; const div = document.createElement('div'); div.className = 'cell-content'; cell.append(div); }
    };
    for (const cell of body.querySelectorAll('td')) {
        for (const attribute of ['data-consumo-provider', 'data-consumo-product', 'data-consumo-supplier']) {
            if (cell.hasAttribute(attribute)) cell.setAttribute(attribute, cell.getAttribute(attribute).toLowerCase());
        }
        if ((cell.hasAttribute('data-consumo-supplier') || cell.hasAttribute('data-consumo-field')) && !cell.hasAttribute('data-consumo-product')) invalid('Hay celdas sin identificador de producto.');
        if (cell.hasAttribute('data-consumo-sources') && cell.dataset.consumoField !== 'consumo') invalid('Hay vínculos fuera de las casillas de consumo.');
    }
    // Clear operational fields before parsing products. No PDF row identity is portable.
    for (const cell of body.querySelectorAll('[data-consumo-field]')) {
        if (!['product', 'consumo', 'stock', 'pedido'].includes(cell.dataset.consumoField)) invalid('La plantilla contiene un tipo de casilla desconocido.');
        if (cell.dataset.consumoField !== 'product') clear(cell);
        if (cell.dataset.consumoField === 'consumo') cell.dataset.consumoSources = '[]';
    }
    // Untyped stock/order cells in packaging tables are identified by their
    // printed column header and matching geometry, while free notes in
    // receiving/ordering tables are preserved as authored in the template.
    const labels = /^(STOCK|PEDIDO|M|V)$/i;
    const headers = [...body.querySelectorAll('td')].filter(cell => !cell.hasAttribute('data-consumo-product') && labels.test(cell.textContent.trim().replace(/\s+/g, ' ')));
    for (const header of headers) {
        const bounds = layout.bounds.get(header);
        for (let row = bounds.maxR + 1; row < layout.height; row++) {
            const cell = layout.map[row]?.[bounds.minC];
            const position = layout.bounds.get(cell);
            if (!position || !cell.classList.contains('b-bottom')) break;
            const intersecting = new Set(layout.map[row].slice(bounds.minC, bounds.maxC + 1));
            if ([...intersecting].some(part => part !== header && layout.bounds.get(part).minR === row && !part.hasAttribute('data-consumo-product')
                && (part.hasAttribute('data-consumo-provider') || /^(PRODUCTO|CONSUMO|STOCK|PEDIDO|M|V)$/i.test(part.textContent.trim())))) break;
            for (const part of intersecting) {
                const area = layout.bounds.get(part);
                if (area.minC >= bounds.minC && area.maxC <= bounds.maxC && !part.hasAttribute('data-consumo-product')
                    && !headers.includes(part)) clear(part);
            }
        }
    }
    for (const cell of body.querySelectorAll('td:not([data-consumo-product]):not([data-consumo-provider])')) {
        const match = /^(FECHA|CONSUMO)\s*:/i.exec(cell.textContent.trim());
        if (match) { clear(cell); cell.querySelector('.cell-content').textContent = `${match[1].toUpperCase()}:`; }
    }
    const safe = sanitizeTemplate(body.innerHTML, document, options);
    const suppliers = readSuppliers(safe);
    const typedCells = [...safe.querySelectorAll('[data-consumo-product]')];
    const products = new Set(suppliers.flatMap(supplier => supplier.products.map(product => product.id)));
    if (typedCells.some(cell => !products.has(cell.dataset.consumoProduct))) invalid('Hay celdas de producto sin su proveedor o nombre.');
    for (const supplier of suppliers) {
        if (!supplier.name || supplier.name.length > 255) invalid('Cada proveedor debe tener un nombre de hasta 255 caracteres.');
        for (const item of [supplier, ...supplier.products]) {
            if (!uuid.test(item.id) || identities.has(item.id) || item.name.length > 255) invalid('Hay productos o proveedores repetidos o con identificadores no válidos.');
            identities.add(item.id);
        }
    }
    return templateSnapshot(safe, options);
}

function cleanTemplate(template, document) {
    if (!template || typeof template.title !== 'string' || !template.title.trim() || template.title.length > 255) invalid('La plantilla necesita un nombre de hasta 255 caracteres.');
    if (!Array.isArray(template.pages) || template.pages.length !== 1) invalid('La plantilla debe contener una sola hoja. Exporta e importa cada hoja por separado.');
    const identities = new Set();
    const pages = template.pages.map(page => {
        if (!page || !uuid.test(page.id) || identities.has(page.id) || typeof page.title !== 'string' || !page.title.trim() || page.title.length > 150) invalid('Las hojas deben tener nombres e identificadores válidos y únicos.');
        identities.add(page.id);
        return { id: page.id, title: page.title.trim(), grid_html: cleanPage(page.grid_html, document, identities) };
    });
    if (pages.reduce((sum, page) => sum + bytes(page.grid_html), 0) > 5 * 1024 * 1024) invalid('El diseño del documento supera los 5 MB permitidos.');
    return { title: template.title.trim(), pages };
}

export function createConsumoTemplateFile(template, document) {
    const result = JSON.stringify({ format: FORMAT, version: 1, template: cleanTemplate(template, document) });
    if (bytes(result) > MAX_CONSUMO_TEMPLATE_BYTES) invalid('El archivo de plantilla supera los 2 MB permitidos.');
    return result;
}

export function readConsumoTemplateFile(text, document, newId = () => globalThis.crypto.randomUUID()) {
    if (typeof text !== 'string' || text.length > MAX_CONSUMO_TEMPLATE_BYTES || bytes(text) > MAX_CONSUMO_TEMPLATE_BYTES) invalid('El archivo de plantilla supera los 2 MB permitidos.');
    let data;
    try { data = JSON.parse(text.replace(/^\uFEFF/, '')); } catch { invalid('Selecciona un archivo JSON válido de plantilla de Consumos.'); }
    if (data?.format !== FORMAT || data.version !== 1) invalid('Este archivo no es una plantilla de Consumos compatible con MMQP.');
    const template = cleanTemplate(data.template, document);
    const replacements = new Map();
    const replace = id => { if (!replacements.has(id)) replacements.set(id, newId()); return replacements.get(id); };
    // Fresh identities also prevent destination-side remembered links from being reused.
    template.pages = template.pages.map(page => {
        const body = document.createElement('tbody'); body.innerHTML = page.grid_html;
        for (const cell of body.querySelectorAll('[data-consumo-provider],[data-consumo-product],[data-consumo-supplier]')) {
            for (const attribute of ['data-consumo-provider', 'data-consumo-product', 'data-consumo-supplier']) {
                if (cell.hasAttribute(attribute)) cell.setAttribute(attribute, replace(cell.getAttribute(attribute)));
            }
        }
        return { ...page, id: replace(page.id), grid_html: body.innerHTML };
    });
    return template;
}
