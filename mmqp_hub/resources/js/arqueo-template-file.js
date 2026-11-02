import { buildGridMap, sanitizeTemplate, templateGeometry, templateSnapshot } from './arqueo-editor.js';

export const MAX_TEMPLATE_FILE_BYTES = 2 * 1024 * 1024;
export const MAX_TEMPLATE_HTML_LENGTH = 1_000_000;
const FORMAT = 'mmqp.arqueo-template';
const VERSION = 1;
const TAGS = new Set(['tr', 'td', 'div', 'span', 'b', 'strong', 'i', 'em', 'u', 'br', 'p']);
const PROPS = ['nombre', 'manana', 'noche', 'completo'];
const byteLength = text => new TextEncoder().encode(text).byteLength;
const invalid = message => { throw new Error(message); };

// A browser repairs incomplete table markup while parsing it. Check its nesting
// first so a damaged file cannot silently change the user's cells or text.
export function validateMarkup(html) {
    const stack = [];
    let position = 0;
    while (position < html.length) {
        if (html[position] !== '<') {
            const next = html.indexOf('<', position);
            const end = next < 0 ? html.length : next;
            if (!stack.includes('td') && html.slice(position, end).trim()) invalid('La plantilla contiene texto fuera de sus casillas.');
            position = end;
            continue;
        }
        let end = position + 1;
        let quote = null;
        for (; end < html.length; end++) {
            const character = html[end];
            if (quote) { if (character === quote) quote = null; }
            else if (character === '"' || character === "'") quote = character;
            else if (character === '>') break;
            else if (character === '<') invalid('La estructura de la plantilla está dañada.');
        }
        if (end === html.length) invalid('La estructura de la plantilla está incompleta.');
        const token = html.slice(position, end + 1);
        const match = /^<\s*(\/?)\s*([a-z][a-z0-9-]*)(?=[\s/>])/i.exec(token);
        const name = match?.[2].toLowerCase();
        if (!match || !TAGS.has(name)) invalid('El archivo contiene contenido que no pertenece a una plantilla de arqueo.');
        const closing = !!match[1];
        const parent = stack.at(-1);
        if (closing) {
            if (name === 'br' || !/^<\s*\/\s*[a-z][a-z0-9-]*\s*>$/i.test(token) || stack.pop() !== name) invalid('La estructura de la plantilla está dañada.');
        } else {
            if ((name === 'tr' && stack.length) || (name === 'td' && parent !== 'tr') || (!['tr', 'td'].includes(name) && !stack.includes('td'))) invalid('Las filas y las casillas de la plantilla no están bien organizadas.');
            if (/\/\s*>$/.test(token) && name !== 'br') invalid('La estructura de la plantilla está dañada.');
            if (name !== 'br') {
                if (stack.length >= 64) invalid('La plantilla contiene demasiados niveles de formato.');
                stack.push(name);
            }
        }
        position = end + 1;
    }
    if (stack.length) invalid('La estructura de la plantilla está incompleta.');
}

function validateAttributes(body, options) {
    for (const node of body.querySelectorAll('*')) {
        for (const attribute of node.attributes) {
            const name = attribute.name.toLowerCase();
            if (/^on/.test(name) || ['href', 'src', 'srcset', 'srcdoc', 'xlink:href', 'action', 'formaction', 'poster', 'background'].includes(name)) invalid('La plantilla contiene acciones o enlaces no permitidos.');
            if (name === 'style' && /[\\<>@]|url\s*\(|expression\s*\(|javascript\s*:|\/\*/i.test(attribute.value)) invalid('La plantilla contiene estilos no permitidos.');
            if (['data-arqueo-columns', 'data-arqueo-rows'].includes(name) && node !== body.rows[0]) invalid('Las medidas de la plantilla deben estar en su primera fila.');
        }
        if (node.tagName !== 'TD') continue;
        const employee = node.getAttribute('data-bound-emp');
        const prop = node.getAttribute('data-bound-prop');
        const date = node.hasAttribute('data-bound-date');
        if ((employee !== null || prop !== null) && (!/^\d+$/.test(employee || '') || !PROPS.includes(prop) || date)) invalid('La plantilla contiene un vínculo de empleado no válido.');
        const contents = node.querySelectorAll('.cell-content');
        if (contents.length && (contents.length !== 1 || contents[0].parentElement !== node || [...node.childNodes].some(child => child !== contents[0] && (child.nodeType !== 3 || child.textContent.trim())))) invalid('Una casilla de la plantilla contiene una estructura no válida.');
    }
    const first = body.rows[0];
    const hasGeometry = first?.hasAttribute('data-arqueo-columns') || first?.hasAttribute('data-arqueo-rows');
    if (hasGeometry && (!options.enableImportGeometry || !templateGeometry(body, options))) invalid('Las medidas de la plantilla no son válidas o no coinciden con sus casillas.');
}

export function validateGrid(body) {
    const rows = [...body.rows];
    if (!rows.length || rows.length > 400 || !body.querySelector('td')) invalid('La plantilla debe contener filas y casillas válidas.');
    const map = [];
    let width = 0;
    for (const [r, row] of rows.entries()) {
        map[r] ||= [];
        let column = 0;
        for (const cell of row.cells) {
            while (map[r][column]) column++;
            const span = name => {
                const value = cell.getAttribute(name);
                if (value !== null && !/^[1-9]\d*$/.test(value)) invalid('La plantilla contiene una combinación de casillas no válida.');
                return value === null ? 1 : Number(value);
            };
            const rowSpan = span('rowspan');
            const colSpan = span('colspan');
            if (!Number.isSafeInteger(rowSpan) || !Number.isSafeInteger(colSpan) || r + rowSpan > rows.length || column + colSpan > 100) invalid('Una casilla supera los límites de la plantilla.');
            for (let rr = r; rr < r + rowSpan; rr++) {
                map[rr] ||= [];
                for (let cc = column; cc < column + colSpan; cc++) {
                    if (map[rr][cc]) invalid('La plantilla contiene casillas superpuestas.');
                    map[rr][cc] = true;
                }
            }
            column += colSpan;
            width = Math.max(width, column);
        }
    }
    for (const row of map) {
        for (let c = 0; c < width; c++) if (!row[c]) invalid('La plantilla tiene huecos o filas con un número distinto de casillas.');
    }
    const layout = buildGridMap(rows);
    if (!width || layout.width !== width || layout.height !== rows.length) invalid('La estructura de la plantilla no es válida.');
}

function cleanHtml(html, document, options) {
    if (typeof html !== 'string' || !html.trim()) invalid('El archivo no contiene un diseño de arqueo.');
    if (html.length > MAX_TEMPLATE_HTML_LENGTH) invalid('El diseño es demasiado grande. El máximo es de 1.000.000 de caracteres.');
    validateMarkup(html);
    const fragment = document.createElement('template');
    fragment.innerHTML = `<table><tbody>${html}</tbody></table>`;
    const body = fragment.content.querySelector('tbody');
    validateGrid(body);
    validateAttributes(body, options);
    const safe = sanitizeTemplate(body.innerHTML, document, options);
    const result = templateSnapshot(safe, options);
    if (result.length > MAX_TEMPLATE_HTML_LENGTH) invalid('El diseño es demasiado grande para guardarlo.');
    return result;
}

export function createTemplateFile(html, document, options = {}) {
    const text = JSON.stringify({ format: FORMAT, version: VERSION, template: { html: cleanHtml(html, document, options) } });
    if (byteLength(text) > MAX_TEMPLATE_FILE_BYTES) invalid('La plantilla supera los 2 MB permitidos.');
    return text;
}

export function readTemplateFile(text, document, options = {}) {
    if (typeof text !== 'string') invalid('No se ha podido leer el archivo de plantilla.');
    if (text.length > MAX_TEMPLATE_FILE_BYTES || byteLength(text) > MAX_TEMPLATE_FILE_BYTES) invalid('El archivo supera los 2 MB permitidos.');
    let data;
    try { data = JSON.parse(text.replace(/^\uFEFF/, '')); }
    catch { invalid('El archivo no es un JSON válido de plantilla de arqueo.'); }
    if (!data || typeof data !== 'object' || Array.isArray(data) || data.format !== FORMAT) invalid('Este archivo no es una plantilla de arqueo de MMQP.');
    if (data.version !== VERSION) invalid('La versión de esta plantilla no es compatible.');
    if (!data.template || typeof data.template !== 'object' || Array.isArray(data.template)) invalid('El archivo no contiene un diseño de arqueo válido.');
    return cleanHtml(data.template.html, document, options);
}
