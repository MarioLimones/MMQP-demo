import { cellAppearance, selectionFormatState, updateContextFormatFeedback } from './arqueo-format-state.js';
import { drawPrintBorders, clearPrintBorders } from './arqueo-print-borders.js';
import { templateDefinitionHtml } from './arqueo-daily.js';
import { initializeColorPicker } from './arqueo-color-picker.js';

const ROWS = 73;
const COLS = 24;
const BORDER_CLASSES = ['solid-border', 'b-top', 'b-bottom', 'b-left', 'b-right'];
const BOUND_PROPS = ['nombre', 'manana', 'noche', 'completo'];

// Imported forms retain their own grid proportions. Keep this inert metadata in
// the HTML snapshot so undo, redo and saving use the same atomic document.
export function validateImportGeometry(value, dimensions = null) {
    if (!value || typeof value !== 'object') return null;
    const validAxis = (values, maximum, total) => Array.isArray(values)
        && values.length > 0 && values.length <= maximum
        && values.every(number => typeof number === 'number' && Number.isFinite(number) && number > 0 && number <= total)
        && Math.abs(values.reduce((sum, number) => sum + number, 0) - total) <= .05;
    if (!validAxis(value.columns, 100, 100) || !validAxis(value.rows, 400, 276)) return null;
    if (dimensions && (value.columns.length !== dimensions.width || value.rows.length !== dimensions.height)) return null;
    return { columns: [...value.columns], rows: [...value.rows] };
}

export function templateGeometry(tbody, options = {}) {
    if (!options.enableImportGeometry || !tbody.rows[0]) return null;
    try {
        return validateImportGeometry({
            columns: JSON.parse(tbody.rows[0].getAttribute('data-arqueo-columns')),
            rows: JSON.parse(tbody.rows[0].getAttribute('data-arqueo-rows')),
        }, buildGridMap(tbody.rows));
    } catch { return null; }
}

function setTemplateGeometry(tbody, geometry) {
    if (!tbody.rows[0]) return;
    tbody.rows[0].setAttribute('data-arqueo-columns', JSON.stringify(geometry.columns));
    tbody.rows[0].setAttribute('data-arqueo-rows', JSON.stringify(geometry.rows));
}

export function contextMenuPosition(point, menu, viewport) {
    const gap = 8;
    const left = viewport.offsetLeft || 0;
    const top = viewport.offsetTop || 0;
    return {
        left: Math.max(left + gap, Math.min(point.x, left + viewport.width - menu.width - gap)),
        top: Math.max(top + gap, Math.min(point.y, top + viewport.height - menu.height - gap)),
    };
}

export function moreOptionsPosition(trigger, popup, viewport) {
    const gap = 8;
    const minLeft = (viewport.offsetLeft || 0) + gap;
    const minTop = (viewport.offsetTop || 0) + gap;
    const maxRight = (viewport.offsetLeft || 0) + viewport.width - gap;
    const maxBottom = (viewport.offsetTop || 0) + viewport.height - gap;
    const rightFits = trigger.right + gap + popup.width <= maxRight;
    const left = rightFits ? trigger.right + gap : trigger.right - popup.width;
    let top = rightFits ? trigger.top : trigger.bottom + gap;
    if (!rightFits && top + popup.height > maxBottom) top = trigger.top - popup.height - gap;
    return {
        left: Math.max(minLeft, Math.min(left, maxRight - popup.width)),
        top: Math.max(minTop, Math.min(top, maxBottom - popup.height)),
    };
}

// Logical coordinates are independent of a cell's position in its DOM row.
export function buildGridMap(rows) {
    const map = [];
    const bounds = new Map();
    let width = 0;
    Array.from(rows).forEach((row, r) => {
        map[r] ??= [];
        let c = 0;
        Array.from(row.cells).forEach(cell => {
            while (map[r][c]) c++;
            const rs = Math.max(1, Number(cell.rowSpan) || 1);
            const cs = Math.max(1, Number(cell.colSpan) || 1);
            bounds.set(cell, { minR: r, maxR: r + rs - 1, minC: c, maxC: c + cs - 1 });
            for (let rr = r; rr < r + rs; rr++) {
                map[rr] ??= [];
                for (let cc = c; cc < c + cs; cc++) map[rr][cc] = cell;
            }
            c += cs;
            width = Math.max(width, c);
        });
    });
    return { map, bounds, width, height: map.length };
}

export function selectionRectangle(layout, cells) {
    const selected = Array.from(cells).filter(cell => layout.bounds.has(cell));
    if (!selected.length) return null;
    const rectangle = { minR: Infinity, maxR: -1, minC: Infinity, maxC: -1 };
    const expand = b => {
        const previous = [rectangle.minR, rectangle.maxR, rectangle.minC, rectangle.maxC].join(',');
        rectangle.minR = Math.min(rectangle.minR, b.minR);
        rectangle.maxR = Math.max(rectangle.maxR, b.maxR);
        rectangle.minC = Math.min(rectangle.minC, b.minC);
        rectangle.maxC = Math.max(rectangle.maxC, b.maxC);
        return previous !== [rectangle.minR, rectangle.maxR, rectangle.minC, rectangle.maxC].join(',');
    };
    selected.forEach(cell => expand(layout.bounds.get(cell)));
    let changed;
    do {
        changed = false;
        for (let r = rectangle.minR; r <= rectangle.maxR; r++) {
            for (let c = rectangle.minC; c <= rectangle.maxC; c++) {
                const cell = layout.map[r]?.[c];
                if (cell && expand(layout.bounds.get(cell))) changed = true;
            }
        }
    } while (changed);
    const enclosed = new Set();
    for (let r = rectangle.minR; r <= rectangle.maxR; r++) {
        for (let c = rectangle.minC; c <= rectangle.maxC; c++) {
            if (layout.map[r]?.[c]) enclosed.add(layout.map[r][c]);
        }
    }
    return { ...rectangle, cells: enclosed };
}

export function employeeBindingTargets(layout, anchor) {
    const start = layout.bounds.get(anchor);
    if (!start) return [];
    const targets = [anchor];
    let next = start.maxC + 1;
    while (targets.length < 3) {
        const cell = layout.map[start.minR]?.[next];
        const b = layout.bounds.get(cell);
        if (!cell || !b || b.minR !== start.minR || targets.includes(cell)) return [];
        targets.push(cell);
        next = b.maxC + 1;
    }
    return targets;
}

export function employeeBindingAnchor(layout, cell) {
    const props = ['nombre', 'manana', 'noche'];
    const id = cell?.dataset?.boundEmp;
    let index = props.indexOf(cell?.dataset?.boundProp);
    if (!id || index < 1) return cell;
    let anchor = cell;
    while (index > 0) {
        const b = layout.bounds.get(anchor);
        const previous = layout.map[b?.minR]?.[b?.minC - 1];
        const p = layout.bounds.get(previous);
        if (!p || p.minR !== b.minR || p.maxR !== b.maxR || previous.dataset?.boundEmp !== id || previous.dataset?.boundProp !== props[index - 1]) return cell;
        anchor = previous;
        index--;
    }
    const group = employeeBindingTargets(layout, anchor);
    return group.length === 3 && group.every((part, i) => part.dataset?.boundEmp === id && part.dataset?.boundProp === props[i]) ? anchor : cell;
}

// Plan all three fields before changing the DOM. A merged cell is indivisible.
export function planEmployeeBinding(layout, anchor, options = {}) {
    const start = layout.bounds.get(anchor);
    if (!start) return { error: 'outside' };
    const hasBorder = (cell, side) => cell.classList?.contains('solid-border') || cell.classList?.contains(`b-${side}`);
    const fields = [];
    let next = start.minC;
    let removedDividers = 0;
    for (const [index, prop] of ['nombre', 'manana', 'noche'].entries()) {
        const first = layout.map[start.minR]?.[next];
        const needed = Number(options.requiredWidth?.(prop, first) ?? options.widths?.[index] ?? 3);
        const minimum = Number.isFinite(needed) ? Math.max(1, Math.ceil(needed)) : layout.width + 1;
        const cells = [];
        let width = 0;
        while (width < minimum) {
            const cell = layout.map[start.minR]?.[next];
            const b = layout.bounds.get(cell);
            if (!cell || !b || next >= layout.width) return { error: 'outside', prop };
            if (b.minC !== next || b.minR !== start.minR || b.maxR !== start.maxR) return { error: 'shape', prop, cell };
            if (options.canUse && !options.canUse(cell)) return { error: 'occupied', prop, cell };
            const previous = cells.at(-1);
            if (previous) {
                const divided = hasBorder(previous, 'right') || hasBorder(cell, 'left');
                if (divided && !options.canRemoveDivider?.(previous, cell)) return { error: 'border', prop, cell };
                if (!!hasBorder(previous, 'top') !== !!hasBorder(cell, 'top') || !!hasBorder(previous, 'bottom') !== !!hasBorder(cell, 'bottom') || (previous.style?.backgroundColor || '') !== (cell.style?.backgroundColor || '')) return { error: 'format', prop, cell };
                if (divided) removedDividers++;
            }
            cells.push(cell);
            width += b.maxC - b.minC + 1;
            next = b.maxC + 1;
        }
        fields.push({ prop, cells, first, width, rowSpan: start.maxR - start.minR + 1 });
    }
    return { fields, removedDividers };
}

// Excel escapes tabs, newlines and quotes by quoting the complete field.
export function parseTabularText(text) {
    const source = String(text).replace(/\r\n?/g, '\n');
    const rows = [[]];
    let value = '';
    let quoted = false;
    let atStart = true;
    for (let i = 0; i < source.length; i++) {
        const character = source[i];
        if (quoted) {
            if (character === '"' && source[i + 1] === '"') { value += '"'; i++; }
            else if (character === '"') quoted = false;
            else value += character;
        } else if (character === '"' && atStart) { quoted = true; atStart = false; }
        else if (character === '\t') { rows.at(-1).push(value); value = ''; atStart = true; }
        else if (character === '\n') { rows.at(-1).push(value); rows.push([]); value = ''; atStart = true; }
        else { value += character; atStart = false; }
    }
    if (quoted) throw new Error('Hay comillas sin cerrar en el texto copiado. Revisa el rango de origen antes de pegar.');
    rows.at(-1).push(value);
    if (source.endsWith('\n') && rows.at(-1).length === 1 && rows.at(-1)[0] === '') rows.pop();
    return rows.length ? rows : [['']];
}

export function clipboardValues(text, encodedGrid = false) {
    return encodedGrid || /[\t\r\n]/.test(text) ? parseTabularText(text) : [[String(text)]];
}

export function planGridPaste(layout, start, values) {
    const height = values.length;
    const width = Math.max(0, ...values.map(row => row.length));
    if (!start || !height || !width || start.minR + height > layout.height || start.minC + width > layout.width) return { error: 'outside' };
    const writes = new Map();
    for (let r = 0; r < height; r++) {
        for (let c = 0; c < width; c++) {
            const cell = layout.map[start.minR + r]?.[start.minC + c];
            const bounds = layout.bounds.get(cell);
            if (!cell || !bounds) return { error: 'outside' };
            if (bounds.minR < start.minR || bounds.minC < start.minC) return { error: 'partial-merge' };
            const text = String(values[r][c] ?? '');
            if (writes.has(cell) && text !== '' && writes.get(cell) !== '') return { error: 'merged-values' };
            if (!writes.has(cell) || text !== '') writes.set(cell, text);
        }
    }
    return { writes: Array.from(writes, ([cell, text]) => ({ cell, text })), height, width };
}

export function gridSelectionText(layout, cells, readText) {
    const rectangle = selectionRectangle(layout, cells);
    if (!rectangle) return '';
    const escape = value => /[\t\n\r"]/.test(value) ? `"${value.replaceAll('"', '""')}"` : value;
    const rows = [];
    for (let r = rectangle.minR; r <= rectangle.maxR; r++) {
        const values = [];
        for (let c = rectangle.minC; c <= rectangle.maxC; c++) {
            const cell = layout.map[r]?.[c];
            const bounds = layout.bounds.get(cell);
            values.push(bounds && bounds.minR === r && bounds.minC === c ? escape(String(readText(cell))) : '');
        }
        rows.push(values.join('\t'));
    }
    return rows.join('\r\n');
}

export class ArqueoHistory {
    constructor(snapshot, limit = 35) {
        this.entries = [snapshot];
        this.index = 0;
        this.limit = limit;
        this.saved = snapshot;
    }
    get current() { return this.entries[this.index]; }
    get dirty() { return this.current !== this.saved; }
    commit(snapshot) {
        if (snapshot === this.current) return false;
        this.entries.splice(this.index + 1);
        this.entries.push(snapshot);
        if (this.entries.length > this.limit) this.entries.shift();
        this.index = this.entries.length - 1;
        return true;
    }
    undo() { if (this.index > 0) return this.entries[--this.index]; return null; }
    redo() { if (this.index < this.entries.length - 1) return this.entries[++this.index]; return null; }
    markSaved(snapshot) { this.saved = snapshot; }
}

function copySafeStyle(source, target, options = {}) {
    for (const property of ['backgroundColor', 'color', 'fontWeight', 'fontStyle', 'textAlign', 'textDecoration']) {
        const value = source.style?.[property];
        if (value && !/url\s*\(|expression\s*\(|var\s*\(/i.test(value)) target.style[property] = value;
    }
    const fontSize = /^([\d.]+)px$/.exec(source.style?.fontSize || '');
    const pixels = fontSize ? Number(fontSize[1]) : 0;
    if (Number.isFinite(pixels) && pixels > 0) target.style.fontSize = `${Math.min(512, pixels)}px`;
    if (options.enableImportGeometry) {
        for (const property of ['paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft']) {
            const match = /^(\d+(?:\.\d+)?|\.\d+)px$/.exec(source.style?.[property] || '');
            const value = match ? Number(match[1]) : -1;
            if (Number.isFinite(value) && value >= 0 && value <= 1024) target.style[property] = `${value}px`;
        }
        const match = /^(\d+(?:\.\d+)?|\.\d+)$/.exec(source.style?.lineHeight || '');
        const value = match ? Number(match[1]) : -1;
        if (Number.isFinite(value) && value >= .5 && value <= 4) target.style.lineHeight = String(value);
    }
}

function copySafeContent(source, target, document, options = {}) {
    const nodes = Array.from(source.childNodes);
    const endsInLineBreak = node => node?.nodeType === 3
        ? /\n$/.test(node.textContent)
        : node?.tagName === 'BR' || (node?.lastChild ? endsInLineBreak(node.lastChild) : false);
    const separateLine = () => {
        if (target.lastChild && !endsInLineBreak(target.lastChild)) target.append(document.createElement('br'));
    };
    nodes.forEach((node, index) => {
        if (node.nodeType === 3) {
            target.append(document.createTextNode(node.textContent));
            return;
        }
        if (node.nodeType !== 1 || ['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'SVG', 'MATH', 'TEMPLATE'].includes(node.tagName)) return;
        if (['B', 'STRONG', 'I', 'EM', 'U', 'BR', 'SPAN'].includes(node.tagName)) {
            const safe = document.createElement(node.tagName.toLowerCase());
            copySafeStyle(node, safe, options);
            copySafeContent(node, safe, document, options);
            target.append(safe);
        } else {
            // Keep words from old pasted markup, never its handlers, URLs or images.
            const block = ['DIV', 'P'].includes(node.tagName);
            if (block) separateLine();
            copySafeContent(node, target, document, options);
            if (block && index < nodes.length - 1) separateLine();
        }
    });
}

// Extensions opt in to specific inert data attributes; ordinary Arqueo keeps its
// existing allow-list. Never copy arbitrary attributes from a saved sheet.
function extensionAttributes(options) {
    return Array.from(new Set(options.cellAttributes || [])).filter(name => /^data-[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/.test(name));
}

export function sanitizeTemplate(html, document, options = {}) {
    const template = document.createElement('template');
    template.innerHTML = `<table><tbody>${typeof html === 'string' ? html : ''}</tbody></table>`;
    const body = document.createElement('tbody');
    const source = template.content.querySelector('tbody');
    const attributes = extensionAttributes(options);
    if (!source) return body;
    Array.from(source.rows).forEach(sourceRow => {
        const row = body.insertRow();
        Array.from(sourceRow.cells).forEach(sourceCell => {
            const cell = row.insertCell();
            const rowspan = Math.max(1, Math.min(Math.max(ROWS, source.rows.length), Number(sourceCell.rowSpan) || 1));
            const colspan = Math.max(1, Math.min(100, Number(sourceCell.colSpan) || 1));
            if (rowspan > 1) cell.rowSpan = rowspan;
            if (colspan > 1) cell.colSpan = colspan;
            BORDER_CLASSES.forEach(name => { if (sourceCell.classList.contains(name)) cell.classList.add(name); });
            copySafeStyle(sourceCell, cell, options);
            const emp = sourceCell.getAttribute('data-bound-emp');
            const prop = sourceCell.getAttribute('data-bound-prop');
            if (/^\d+$/.test(emp || '') && BOUND_PROPS.includes(prop)) {
                cell.dataset.boundEmp = emp;
                cell.dataset.boundProp = prop;
            } else if (sourceCell.hasAttribute('data-bound-date')) cell.dataset.boundDate = 'true';
            if (sourceCell.getAttribute('data-arqueo-input') === 'true' && !cell.hasAttribute('data-bound-emp') && !cell.hasAttribute('data-bound-date')) cell.dataset.arqueoInput = 'true';
            attributes.forEach(name => {
                const value = sourceCell.getAttribute(name);
                const configuredLimit = Number(options.cellAttributeLimits?.[name] ?? 8192);
                const limit = Number.isSafeInteger(configuredLimit) && configuredLimit >= 0 ? Math.min(1000000, configuredLimit) : 8192;
                if (value !== null && value.length <= limit) cell.setAttribute(name, value);
            });
            const content = document.createElement('div');
            content.className = 'cell-content';
            const original = sourceCell.querySelector('.cell-content') || sourceCell;
            copySafeStyle(original, content, options);
            copySafeContent(original, content, document, options);
            cell.append(content);
        });
    });
    if (options.enableImportGeometry && source.rows[0] && body.rows[0]) {
        for (const name of ['data-arqueo-columns', 'data-arqueo-rows']) {
            const value = source.rows[0].getAttribute(name);
            if (value !== null && value.length <= 8192) body.rows[0].setAttribute(name, value);
        }
        const geometry = templateGeometry(body, options);
        body.rows[0].removeAttribute('data-arqueo-columns');
        body.rows[0].removeAttribute('data-arqueo-rows');
        if (geometry) setTemplateGeometry(body, geometry);
    }
    return body;
}

export function templateSnapshot(tbody, options = {}) {
    const clone = tbody.cloneNode(true);
    clone.querySelectorAll('td').forEach(cell => {
        cell.classList.remove('multi-selected', 'cell-overflow');
        if (!cell.className) cell.removeAttribute('class');
        ['role', 'aria-selected', 'aria-label', 'tabindex'].forEach(name => cell.removeAttribute(name));
        cell.style.removeProperty('--cell-rows');
        cell.style.removeProperty('--cell-height');
        if (!cell.getAttribute('style')) cell.removeAttribute('style');
        const content = cell.querySelector('.cell-content');
        if (!content) return;
        ['contenteditable', 'tabindex', 'role', 'aria-label', 'aria-readonly', 'title', 'spellcheck'].forEach(name => content.removeAttribute(name));
        if (cell.hasAttribute('data-bound-date') || cell.hasAttribute('data-bound-emp') || options.isBound?.(cell)) content.replaceChildren();
    });
    clone.querySelectorAll('tr').forEach(row => row.removeAttribute('role'));
    // Restore uses this same serializer; attribute/class order must not create
    // phantom changes after undo/redo or leave a successful save marked dirty.
    return sanitizeTemplate(clone.innerHTML, tbody.ownerDocument, options).innerHTML;
}

export async function postTemplate(config, html, fetcher) {
    const response = await fetcher(config.saveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
        body: JSON.stringify({ template: { html } }),
    });
    let data;
    try { data = await response.json(); } catch { data = null; }
    if (!response.ok || data?.success !== true) {
        if (response.status === 419) throw new Error('Tu sesión ha caducado. Conserva esta pestaña y vuelve a iniciar sesión antes de guardar.');
        if (response.status === 422) throw new Error('No se pudo validar el diseño. Revisa la hoja y vuelve a guardar.');
        throw new Error('No se pudo guardar el diseño. Tu trabajo sigue en esta pestaña; vuelve a intentarlo.');
    }
    return data;
}

export function initializeArqueo(config, document = window.document) {
    const table = document.getElementById('arqueo-grid');
    if (!table || table.dataset.editorReady) return null;
    table.dataset.editorReady = 'true';
    let tbody = table.querySelector('tbody');
    let editMode = false;
    let selected = new Set();
    let anchor = null;
    let active = null;
    let dragging = false;
    let dragged = false;
    let editingText = false;
    let pendingText = false;
    let saving = false;
    let modalReturnFocus = null;
    let modalAnchor = null;
    let modalSelection = new Set();
    let contextReturnFocus = null;
    let navigationApproved = false;
    let overflowFrame = null;
    let overflowCount = 0;
    let history;
    let publicApi;
    let initializing = true;
    const byId = id => document.getElementById(id);
    const colorPicker = initializeColorPicker(document, color => cmd('bg-color', color));
    const layout = () => buildGridMap(tbody.rows);
    const snapshot = () => templateSnapshot(tbody, config);
    const bound = cell => cell.hasAttribute('data-bound-emp') || cell.hasAttribute('data-bound-date') || !!config.isBound?.(cell);
    const readonly = cell => bound(cell) || (config.dailyMode && !editMode && cell.dataset.arqueoInput !== 'true');
    const notify = (name, detail) => {
        if (publicApi) table.dispatchEvent(new document.defaultView.CustomEvent(`arqueo:${name}`, { bubbles: true, detail: { api: publicApi, ...detail } }));
    };
    const cellContent = cell => cell?.querySelector('.cell-content');
    const plainText = node => {
        if (!node) return '';
        if (node.nodeType === 3) return node.textContent;
        if (node.nodeName === 'BR') return '\n';
        let result = '';
        const children = Array.from(node.childNodes);
        children.forEach((child, index) => {
            const block = ['DIV', 'P'].includes(child.nodeName);
            if (block && result && !result.endsWith('\n')) result += '\n';
            result += plainText(child);
            if (block && index < children.length - 1 && !result.endsWith('\n')) result += '\n';
        });
        return result;
    };
    const readCellText = cell => plainText(cellContent(cell));
    const cellText = cell => (cellContent(cell)?.textContent || '').trim();
    function preserveWholeCellStyle(cell) {
        const content = cellContent(cell);
        let current = content;
        while (current) {
            const children = Array.from(current.childNodes).filter(node => node.nodeType === 1 || (node.nodeType === 3 && node.textContent.trim()));
            if (children.length !== 1 || children[0].nodeType !== 1 || !['B', 'STRONG', 'I', 'EM', 'U', 'SPAN'].includes(children[0].nodeName)) break;
            current = children[0];
            if (['B', 'STRONG'].includes(current.nodeName)) content.style.fontWeight = 'bold';
            if (['I', 'EM'].includes(current.nodeName)) content.style.fontStyle = 'italic';
            if (current.nodeName === 'U') content.style.textDecoration = 'underline';
            copySafeStyle(current, content, config);
        }
    }
    const gridCell = target => {
        const cell = target?.closest?.('td');
        return cell && table.contains(cell) ? cell : null;
    };
    const status = (message, error = false) => {
        if (config.quietInitialStatus === true && initializing) return;
        const node = byId('editor-status');
        if (node) {
            node.hidden = false;
            node.textContent = message;
            node.dataset.state = error ? 'error' : 'success';
            node.setAttribute('aria-live', error ? 'assertive' : 'polite');
        }
    };
    const makeCell = () => {
        const cell = document.createElement('td');
        const content = document.createElement('div');
        content.className = 'cell-content';
        cell.append(content);
        return cell;
    };
    function scanOverflow() {
        overflowFrame = null;
        overflowCount = 0;
        tbody.querySelectorAll('.cell-content').forEach(content => {
            const tooLarge = !!content.textContent.trim() && (content.scrollHeight > content.clientHeight + 1 || content.scrollWidth > content.clientWidth + 1);
            content.parentElement.classList.toggle('cell-overflow', tooLarge);
            if (tooLarge) overflowCount++;
            content.title = content.textContent || '';
        });
        const current = layout();
        const warning = byId('sheet-overflow-warning');
        if (warning) {
            const messages = [];
            if (!templateGeometry(tbody, config) && (tbody.rows.length > ROWS || current.width > COLS)) messages.push('Este diseño tiene más filas o columnas que una hoja A4. Se han conservado todas. Revisa la vista de impresión.');
            if (overflowCount) messages.push(`Hay texto que no cabe en ${overflowCount} ${overflowCount === 1 ? 'casilla' : 'casillas'}. Une casillas o reduce el tamaño antes de imprimir.`);
            warning.hidden = messages.length === 0;
            warning.textContent = messages.join(' ');
        }
        return overflowCount;
    }
    function scheduleOverflow() {
        if (overflowFrame !== null) return;
        const schedule = document.defaultView.requestAnimationFrame || (callback => document.defaultView.setTimeout(callback, 0));
        overflowFrame = schedule.call(document.defaultView, scanOverflow);
    }
    const blankGrid = () => {
        const body = document.createElement('tbody');
        for (let r = 0; r < ROWS; r++) {
            const row = body.insertRow();
            for (let c = 0; c < COLS; c++) row.append(makeCell());
        }
        return body;
    };
    const columnName = index => {
        let value = index + 1;
        let result = '';
        do { result = String.fromCharCode(65 + ((value - 1) % 26)) + result; value = Math.floor((value - 1) / 26); } while (value);
        return result;
    };
    const cellName = b => `${columnName(b.minC)}${b.minR + 1}`;
    function updateButtons() {
        if (byId('btn-undo')) byId('btn-undo').disabled = history.index === 0 && !pendingText;
        if (byId('btn-redo')) byId('btn-redo').disabled = history.index >= history.entries.length - 1 || pendingText;
        if (byId('btn-save-template')) byId('btn-save-template').disabled = saving;
        table.dataset.dirty = String(pendingText || history.dirty);
        if (publicApi) {
            notify('changed', { dirty: pendingText || history.dirty });
            config.onChanged?.(publicApi);
        }
    }
    function updateSelection(focus = false) {
        colorPicker.sync(currentFormat().color);
        tbody.querySelectorAll('td').forEach(cell => {
            cell.classList.toggle('multi-selected', editMode && selected.has(cell));
            cell.setAttribute('aria-selected', String(selected.has(cell)));
            const content = cellContent(cell);
            if (content) content.tabIndex = cell === active ? 0 : -1;
        });
        if (focus && active) cellContent(active)?.focus({ preventScroll: true });
        const menu = byId('context-menu');
        if (menu && !menu.hidden && menu.style.display !== 'none') refreshContextFormat();
        if (publicApi) {
            notify('selection', { selection: publicApi.getSelection() });
            config.onSelection?.(publicApi);
        }
    }
    function currentFormat(cells = selected) {
        return selectionFormatState(layout(), cells, cell => cellAppearance(cell, document.defaultView));
    }
    function refreshContextFormat() { updateContextFormatFeedback(byId('context-menu'), currentFormat()); }
    function selectCell(cell, extend = false, focus = false) {
        if (!cell) return;
        active = cell;
        if (extend && anchor) selected = selectionRectangle(layout(), [anchor, cell]).cells;
        else { anchor = cell; selected = new Set([cell]); }
        updateSelection(focus);
    }
    function employeeText(id, prop) {
        const employee = (config.empleadosDia || {})[id];
        if (prop === 'nombre') {
            const names = Array.isArray(config.empleadosList) ? config.empleadosList : Object.values(config.empleadosList || {});
            const listed = names.find(person => String(person.id) === String(id));
            const option = Array.from(byId('bind-emp')?.options || []).find(item => item.value === String(id));
            return String(employee?.user?.nombre || listed?.nombre || option?.text.trim() || `[EMP-${id}]`).toUpperCase();
        }
        if (prop === 'completo') return [employee?.manana, employee?.noche].filter(Boolean).join(' / ');
        return String(employee?.[prop] || '');
    }
    let textMeasureContext;
    function measureText(text, content, size, bold = false) {
        if (textMeasureContext === undefined) {
            textMeasureContext = typeof document.defaultView.CanvasRenderingContext2D === 'function' ? document.createElement('canvas').getContext('2d') : null;
        }
        const style = document.defaultView.getComputedStyle(content);
        if (textMeasureContext) {
            textMeasureContext.font = `${style.fontStyle || 'normal'} ${bold ? 'bold' : style.fontWeight || 'normal'} ${size}px ${style.fontFamily || 'Arial, sans-serif'}`;
            return textMeasureContext.measureText(String(text)).width;
        }
        return Array.from(String(text)).reduce((width, character) => width + (/[MW@]/i.test(character) ? 1 : /[il1 .,:]/.test(character) ? .32 : .62), 0) * size * (bold ? 1.07 : 1);
    }
    function bindingFontSize(cell) {
        const content = cellContent(cell);
        if (!content) return 10;
        const style = document.defaultView.getComputedStyle(content);
        const height = content.clientHeight || cell.rowSpan * (276 * 96 / 25.4 / ROWS);
        const verticalPadding = (Number.parseFloat(style.paddingTop) || 1) + (Number.parseFloat(style.paddingBottom) || 1);
        return Math.max(8, Math.min(Number.parseFloat(style.fontSize) || 10, (height - verticalPadding) / 1.2));
    }
    function bindingWidth(id, prop, cell) {
        const content = cellContent(cell || modalAnchor);
        const style = document.defaultView.getComputedStyle(content);
        const tableWidth = table.getBoundingClientRect().width || 190 * 96 / 25.4;
        const current = layout();
        const columnWidth = tableWidth / current.width;
        const padding = (Number.parseFloat(style.paddingLeft) || 2) + (Number.parseFloat(style.paddingRight) || 2) + 2;
        const size = bindingFontSize(cell || modalAnchor);
        const future = prop === 'nombre' ? 'NOMBRE' : '00:00-00:00';
        const bold = prop === 'nombre' && !content.style.fontWeight && !(cell || modalAnchor).style.fontWeight;
        const width = Math.max(measureText(employeeText(id, prop), content, size, bold), measureText(future, content, size, bold));
        const geometry = templateGeometry(tbody, config);
        const start = current.bounds.get(cell || modalAnchor);
        if (geometry && start) {
            let available = 0;
            let columns = 0;
            while (start.minC + columns < geometry.columns.length && available < width + padding) {
                available += tableWidth * geometry.columns[start.minC + columns] / 100;
                columns++;
            }
            return Math.max(1, columns + (available < width + padding ? 1 : 0));
        }
        return Math.max(3, Math.ceil((width + padding) / columnWidth));
    }
    function renderEmployee(cell) {
        const content = cellContent(cell);
        const text = employeeText(cell.dataset.boundEmp, cell.dataset.boundProp);
        const name = cell.dataset.boundProp === 'nombre';
        if (!content.querySelector('[data-bound-value]')) preserveWholeCellStyle(cell);
        if (name && !content.style.fontWeight && !cell.style.fontWeight) content.style.fontWeight = 'bold';
        const value = document.createElement('span');
        value.dataset.boundValue = 'true';
        value.textContent = text;
        // Runtime fit belongs to the generated value, never to the saved design.
        value.style.display = 'block';
        value.style.whiteSpace = 'nowrap';
        value.style.lineHeight = '1.2';
        let size = bindingFontSize(cell);
        const style = document.defaultView.getComputedStyle(content);
        const available = content.clientWidth - (Number.parseFloat(style.paddingLeft) || 2) - (Number.parseFloat(style.paddingRight) || 2);
        const measured = measureText(text, content, size);
        if (available > 0 && measured > available) size = Math.max(8, Math.floor(size * available / measured * 10) / 10);
        value.style.fontSize = `${size}px`;
        content.replaceChildren(value);
    }
    function populateData(cells = tbody.querySelectorAll('td')) {
        for (const cell of cells) {
            const content = cellContent(cell);
            if (!content) continue;
            if (cell.hasAttribute('data-bound-emp')) {
                renderEmployee(cell);
            } else if (cell.hasAttribute('data-bound-date')) {
                const date = /^(\d{4})-(\d{2})-(\d{2})$/.exec(config.fecha || '');
                if (!content.querySelector('[data-bound-value]')) preserveWholeCellStyle(cell);
                if (!content.style.fontWeight && !cell.style.fontWeight) content.style.fontWeight = 'bold';
                const value = document.createElement('span');
                value.dataset.boundValue = 'true';
                value.textContent = date ? `${date[3]}/${date[2]}/${date[1]}` : '[FECHA]';
                content.replaceChildren(value);
            }
            config.populateCell?.(cell);
            if (config.dailyMode && !bound(cell) && !content.textContent.trim()) cell.dataset.arqueoInput = 'true';
            content.setAttribute('contenteditable', readonly(cell) ? 'false' : 'plaintext-only');
            content.setAttribute('aria-readonly', String(readonly(cell)));
            if (config.dailyMode) content.setAttribute('aria-label', `${cell.getAttribute('aria-label')}, ${bound(cell) ? 'dato automático' : readonly(cell) ? 'etiqueta de plantilla' : 'texto editable'}`);
        }
    }
    function normalizeGrid() {
        const current = layout();
        const geometry = templateGeometry(tbody, config);
        table.style.setProperty('--grid-rows', String(tbody.rows.length));
        table.setAttribute('role', 'grid');
        table.setAttribute('aria-label', config.gridLabel || 'Hoja de arqueo. Selecciona una celda para escribir. En modo diseño, usa Mayúsculas y las flechas para seleccionar varias.');
        Array.from(tbody.rows).forEach(row => row.setAttribute('role', 'row'));
        const rowLabels = byId('sheet-row-labels');
        if (rowLabels && rowLabels.children.length !== tbody.rows.length) {
            rowLabels.replaceChildren(...Array.from({ length: tbody.rows.length }, (_, index) => { const label = document.createElement('span'); label.textContent = String(index + 1); return label; }));
        }
        if (config.enableImportGeometry) {
            const widths = geometry?.columns || Array.from({ length: COLS }, () => 100 / COLS);
            let group = table.querySelector('colgroup');
            if (!group) { group = document.createElement('colgroup'); table.insertBefore(group, tbody); }
            group.replaceChildren(...widths.map(width => { const column = document.createElement('col'); column.style.width = `${width}%`; return column; }));
            const columnLabels = document.querySelector('.arqueo-column-labels');
            if (columnLabels) {
                columnLabels.replaceChildren(...widths.map((width, index) => { const label = document.createElement('span'); label.textContent = columnName(index); return label; }));
                columnLabels.style.gridTemplateColumns = widths.map(width => `${width}fr`).join(' ');
            }
            if (rowLabels) rowLabels.style.gridTemplateRows = geometry ? geometry.rows.map(height => `${height}mm`).join(' ') : '';
            if (geometry) table.dataset.importGeometry = 'true';
            else delete table.dataset.importGeometry;
            Array.from(tbody.rows).forEach((row, index) => { row.style.height = geometry ? `${geometry.rows[index]}mm` : ''; });
        }
        current.bounds.forEach((b, cell) => {
            if (cell.rowSpan === 1) cell.removeAttribute('rowspan');
            if (cell.colSpan === 1) cell.removeAttribute('colspan');
            cell.style.setProperty('--cell-rows', String(cell.rowSpan));
            if (geometry) {
                const height = geometry.rows.slice(b.minR, b.maxR + 1).reduce((sum, value) => sum + value, 0);
                cell.style.height = `${height}mm`;
                cell.style.setProperty('--cell-height', `${height}mm`);
            } else if (config.enableImportGeometry) { cell.style.removeProperty('height'); cell.style.removeProperty('--cell-height'); }
            cell.setAttribute('role', 'gridcell');
            cell.setAttribute('aria-label', `Celda ${cellName(b)}`);
            const content = cellContent(cell);
            content.setAttribute('role', 'textbox');
            content.setAttribute('aria-label', `Celda ${cellName(b)}${bound(cell) ? ', dato automático' : ', texto editable'}`);
            content.setAttribute('spellcheck', 'false');
        });
        populateData();
        current.bounds.forEach((b, cell) => { cellContent(cell).title = cellContent(cell).textContent || ''; });
        const hasContent = Array.from(current.bounds.keys()).some(cell => cellText(cell) || bound(cell) || BORDER_CLASSES.some(name => cell.classList.contains(name)) || cell.style.backgroundColor);
        if (byId('template-empty-help')) byId('template-empty-help').hidden = hasContent;
        const warning = byId('sheet-overflow-warning');
        if (warning) {
            warning.hidden = !!geometry || (tbody.rows.length <= ROWS && current.width <= COLS);
            warning.textContent = 'Este diseño tiene más filas o columnas que una hoja A4. Se han conservado todas. Revisa la vista de impresión antes de imprimir.';
        }
        if (!active?.isConnected || !table.contains(active)) active = tbody.rows[0]?.cells[0] || null;
        if (!anchor?.isConnected || !table.contains(anchor)) anchor = active;
        selected = new Set(Array.from(selected).filter(cell => table.contains(cell)));
        if (!selected.size && active) selected.add(active);
        updateSelection();
        scheduleOverflow();
    }
    function flushText() {
        if (!pendingText) return;
        pendingText = false;
        history.commit(snapshot());
        updateButtons();
    }
    function commit() {
        normalizeGrid();
        history.commit(snapshot());
        updateButtons();
    }
    function restore(snapshot) {
        const previousPosition = layout().bounds.get(active);
        const focusWasInGrid = table.contains(document.activeElement);
        const restored = sanitizeTemplate(snapshot, document, config);
        tbody.replaceWith(restored);
        tbody = restored;
        selected.clear();
        active = null;
        anchor = null;
        editingText = false;
        normalizeGrid();
        if (previousPosition) selectCell(layout().map[previousPosition.minR]?.[previousPosition.minC] || active, false, focusWasInGrid);
        updateButtons();
    }
    function undo() { flushText(); const snapshot = history.undo(); if (snapshot !== null) { restore(snapshot); status('Cambio deshecho.'); } }
    function redo() { flushText(); const snapshot = history.redo(); if (snapshot !== null) { restore(snapshot); status('Cambio recuperado.'); } }
    function requireSelection() {
        if (!editMode) { status('Activa «Editar diseño» para cambiar la estructura de la hoja.', true); return []; }
        flushText();
        const cells = Array.from(selected).filter(cell => table.contains(cell));
        if (!cells.length) status('Selecciona primero una celda de la hoja.', true);
        return cells;
    }
    function hideContext(restoreFocus = false) {
        const menu = byId('context-menu');
        const wasOpen = menu && !menu.hidden && menu.style.display !== 'none';
        const menuHadFocus = menu?.contains(document.activeElement);
        const returnFocus = contextReturnFocus;
        contextReturnFocus = null;
        if (menu) { menu.style.display = 'none'; menu.hidden = true; }
        if ((restoreFocus && wasOpen) || menuHadFocus) returnFocus?.focus?.({ preventScroll: true });
    }
    function visibleViewport() {
        const view = document.defaultView;
        const visible = view.visualViewport;
        return {
            width: visible?.width || view.innerWidth, height: visible?.height || view.innerHeight,
            offsetLeft: visible?.offsetLeft || 0, offsetTop: visible?.offsetTop || 0,
        };
    }
    function moreOptionsOpen() {
        const popup = byId('arqueo-more-options');
        return !!popup && !popup.hidden && !popup.classList.contains('hidden');
    }
    function hideMoreOptions(restoreFocus = false) {
        const popup = byId('arqueo-more-options');
        const wasOpen = moreOptionsOpen();
        const hadFocus = popup?.contains(document.activeElement);
        if (popup) { popup.hidden = true; popup.classList.add('hidden'); }
        byId('btn-more-options')?.setAttribute('aria-expanded', 'false');
        const modal = byId('bind-modal');
        const modalOpen = modal && !modal.hidden && !modal.classList.contains('hidden');
        if (!modalOpen && wasOpen && (restoreFocus || hadFocus)) {
            (editMode ? byId('btn-more-options') : byId('btn-edit-mode'))?.focus({ preventScroll: true });
        }
    }
    function toggleMoreOptions() {
        if (moreOptionsOpen()) { hideMoreOptions(true); return; }
        if (!editMode) return;
        flushText();
        hideContext();
        stopDragging();
        const popup = byId('arqueo-more-options');
        const trigger = byId('btn-more-options');
        if (!popup || !trigger) return;
        popup.hidden = false;
        popup.classList.remove('hidden');
        popup.setAttribute('role', 'menu');
        popup.setAttribute('aria-labelledby', 'btn-more-options');
        trigger.setAttribute('aria-expanded', 'true');
        const viewport = visibleViewport();
        popup.style.maxWidth = `${Math.max(1, viewport.width - 16)}px`;
        popup.style.maxHeight = `${Math.max(1, viewport.height - 16)}px`;
        const position = moreOptionsPosition(trigger.getBoundingClientRect(), { width: popup.offsetWidth, height: popup.offsetHeight }, viewport);
        popup.style.left = `${position.left}px`;
        popup.style.top = `${position.top}px`;
        popup.scrollTop = 0;
        popup.querySelector('button:not([disabled]),a[href],input:not([disabled]),select:not([disabled])')?.focus({ preventScroll: true });
    }
    function measureToolbar() {
        const toolbar = byId('edit-toolbar');
        const controls = toolbar?.closest('.arqueo-design-controls') || toolbar;
        const height = editMode && toolbar && !toolbar.hidden && !toolbar.classList.contains('hidden') ? Math.ceil(controls.getBoundingClientRect().height) : 0;
        document.documentElement.style.setProperty('--arqueo-toolbar-height', `${height}px`);
    }
    function stopDragging() { dragging = false; dragged = false; }
    function toggleEditMode() {
        colorPicker.close?.();
        if (config.canEditDesign === false) return;
        flushText();
        editMode = !editMode;
        if (!editMode && active) { selected = new Set([active]); anchor = active; }
        editingText = false;
        document.body.classList.toggle('edit-mode', editMode);
        const toolbar = byId('edit-toolbar');
        if (toolbar) { toolbar.classList.toggle('hidden', !editMode); toolbar.hidden = !editMode; }
        const button = byId('btn-edit-mode');
        if (button) {
            button.setAttribute('aria-pressed', String(editMode));
            const label = button.querySelector('[data-edit-label]');
            if (label) label.textContent = editMode ? 'Terminar diseño' : 'Editar diseño';
            else button.textContent = editMode ? 'Terminar diseño' : 'Editar diseño';
        }
        hideContext();
        hideMoreOptions();
        measureToolbar();
        updateSelection();
        if (config.dailyMode) { populateData(); updateButtons(); }
        status(editMode ? 'Selecciona celdas y usa las opciones de diseño. Haz doble clic o pulsa F2 para editar un texto.' : 'Puedes rellenar las celdas y después imprimir la hoja.');
    }
    function printArqueo() {
        flushText(); hideContext(); hideMoreOptions();
        if (scanOverflow() && !document.defaultView.confirm('Hay texto que no cabe en algunas casillas. Puedes unirlas o reducir su tamaño. ¿Imprimir de todas formas?')) return;
        document.defaultView.print();
    }
    function merge(cells) {
        const allowed = config.canMerge?.(cells);
        if (allowed === false || typeof allowed === 'string') { status(typeof allowed === 'string' ? allowed : 'Quita primero el tipo de estas celdas antes de combinarlas.', true); return; }
        const current = layout();
        const rectangle = selectionRectangle(current, cells);
        if (!rectangle || rectangle.cells.size < 2) { status('Selecciona dos o más celdas contiguas para combinarlas.', true); return; }
        if (rectangle.cells.size !== cells.length || !cells.every(cell => rectangle.cells.has(cell))) {
            status('La selección debe formar un rectángulo completo. Arrastra o usa Mayúsculas y las flechas.', true);
            return;
        }
        const meaningful = Array.from(rectangle.cells).filter(cell => cellText(cell) || bound(cell));
        if (meaningful.length > 1) {
            status('Hay texto o datos automáticos en varias celdas. Muévelos antes de combinar para conservarlos todos.', true);
            return;
        }
        const first = current.map[rectangle.minR][rectangle.minC];
        if (meaningful[0] && meaningful[0] !== first) {
            const source = meaningful[0];
            cellContent(first).replaceWith(cellContent(source).cloneNode(true));
            for (const name of ['data-bound-emp', 'data-bound-prop', 'data-bound-date', ...extensionAttributes(config)]) {
                if (source.hasAttribute(name)) first.setAttribute(name, source.getAttribute(name));
            }
        }
        const edges = { 'b-top': 'minR', 'b-bottom': 'maxR', 'b-left': 'minC', 'b-right': 'maxC' };
        for (const [name, edge] of Object.entries(edges)) {
            if (Array.from(rectangle.cells).some(cell => current.bounds.get(cell)[edge] === rectangle[edge] && (cell.classList.contains(name) || cell.classList.contains('solid-border')))) first.classList.add(name);
        }
        first.classList.remove('solid-border');
        first.rowSpan = rectangle.maxR - rectangle.minR + 1;
        first.colSpan = rectangle.maxC - rectangle.minC + 1;
        rectangle.cells.forEach(cell => { if (cell !== first) cell.remove(); });
        active = first; anchor = first; selected = new Set([first]);
        commit();
        status('Celdas combinadas. El texto y el dato automático se han conservado.');
    }
    function unmerge(cells) {
        const allowed = config.canUnmerge?.(cells);
        if (allowed === false || typeof allowed === 'string') { status(typeof allowed === 'string' ? allowed : 'Quita primero el tipo de estas celdas antes de separarlas.', true); return; }
        let changed = false;
        for (const cell of cells) {
            if (cell.rowSpan <= 1 && cell.colSpan <= 1) continue;
            const current = layout();
            const b = current.bounds.get(cell);
            const borders = new Set(BORDER_CLASSES.filter(name => cell.classList.contains(name)));
            const background = cell.style.backgroundColor;
            cell.rowSpan = 1; cell.colSpan = 1;
            cell.classList.remove(...BORDER_CLASSES);
            for (let r = b.minR; r <= b.maxR; r++) {
                const row = tbody.rows[r];
                let reference = null;
                for (let c = b.maxC + 1; c < current.width; c++) {
                    const candidate = current.map[r]?.[c];
                    if (candidate?.parentElement === row) { reference = candidate; break; }
                }
                for (let c = b.minC; c <= b.maxC; c++) {
                    const part = r === b.minR && c === b.minC ? cell : makeCell();
                    if (part !== cell) row.insertBefore(part, reference);
                    if (background) part.style.backgroundColor = background;
                    if (r === b.minR && (borders.has('b-top') || borders.has('solid-border'))) part.classList.add('b-top');
                    if (r === b.maxR && (borders.has('b-bottom') || borders.has('solid-border'))) part.classList.add('b-bottom');
                    if (c === b.minC && (borders.has('b-left') || borders.has('solid-border'))) part.classList.add('b-left');
                    if (c === b.maxC && (borders.has('b-right') || borders.has('solid-border'))) part.classList.add('b-right');
                }
            }
            changed = true;
        }
        if (changed) { commit(); status('Celdas separadas. El contenido sigue en la primera celda.'); }
        else status('Esta selección no contiene celdas combinadas.');
    }
    function confirmReplace(cells, message) {
        return !cells.some(cell => cellText(cell) || bound(cell)) || document.defaultView.confirm(message);
    }
    function removeBinding(cell) {
        ['data-bound-emp', 'data-bound-prop', 'data-bound-date'].forEach(name => cell.removeAttribute(name));
        config.removeBinding?.(cell);
    }
    function clearContent(cells) {
        flushText();
        if (cells.some(bound) && !document.defaultView.confirm('La selección contiene datos automáticos. Borrarlos quitará su vinculación. ¿Continuar?')) return;
        cells.forEach(cell => { preserveWholeCellStyle(cell); removeBinding(cell); cellContent(cell).replaceChildren(); });
        editingText = false;
        commit();
        status('Contenido borrado. Se conservan las casillas, los bordes y el formato. Puedes deshacerlo.');
    }
    function pasteRange(text, encodedGrid = false) {
        flushText();
        const current = layout();
        const rectangle = selectionRectangle(current, selected);
        let values;
        try { values = clipboardValues(text, encodedGrid); } catch (error) { status(error.message, true); return; }
        const plan = planGridPaste(current, rectangle || current.bounds.get(active), values);
        if (plan.error) {
            status(plan.error === 'outside' ? 'El rango copiado no cabe en la hoja. Selecciona una casilla más arriba o a la izquierda.' : 'Varias casillas copiadas coinciden con una casilla combinada. Sepárala antes de pegar para conservar todos los datos.', true);
            return;
        }
        const allowed = config.canPaste?.(plan.writes);
        if (allowed === false || typeof allowed === 'string') { status(typeof allowed === 'string' ? allowed : 'Esta selección contiene datos vinculados. Quita primero su vinculación antes de pegar.', true); return; }
        if (config.dailyMode && !editMode && plan.writes.some(item => readonly(item.cell))) { status('Pega los datos sólo en las casillas vacías del arqueo. Los títulos y turnos automáticos se conservan.', true); return; }
        const changesBinding = plan.writes.some(item => bound(item.cell));
        const overwrites = plan.writes.some(item => readCellText(item.cell) !== '' && readCellText(item.cell) !== item.text);
        if ((changesBinding || overwrites) && !document.defaultView.confirm(changesBinding ? 'Pegar sustituirá el contenido y quitará las vinculaciones de las casillas de destino. ¿Continuar?' : 'Pegar sustituirá el contenido de las casillas de destino. ¿Continuar? Puedes deshacerlo.')) return;
        plan.writes.forEach(({ cell, text: value }) => { preserveWholeCellStyle(cell); removeBinding(cell); cellContent(cell).textContent = value; if (config.dailyMode && editMode) delete cell.dataset.arqueoInput; });
        selected = new Set(plan.writes.map(item => item.cell));
        active = plan.writes[0]?.cell || active;
        anchor = active;
        editingText = false;
        commit();
        status(`Rango pegado: ${plan.width} ${plan.width === 1 ? 'columna' : 'columnas'} × ${plan.height} ${plan.height === 1 ? 'fila' : 'filas'}. Se conserva el formato de la hoja.`);
    }
    function cmd(action, value = null) {
        if (action === 'undo') { undo(); return; }
        if (action === 'redo') { redo(); return; }
        const cells = requireSelection();
        hideContext();
        hideMoreOptions();
        if (!cells.length) return;
        if (action === 'clear-content') { clearContent(cells); return; }
        if (action === 'merge') { merge(cells); return; }
        if (action === 'unmerge') { unmerge(cells); return; }
        if (action === 'bind-date' && !confirmReplace(cells, 'Vincular la fecha sustituirá el texto o dato automático de estas celdas. ¿Continuar?')) return;
        const current = layout();
        const rectangle = selectionRectangle(current, cells);
        const formats = currentFormat(cells);
        const applyToAll = formats.states[action] !== 'on';
        cells.forEach(cell => {
            const content = cellContent(cell);
            const b = current.bounds.get(cell);
            if (action === 'b-all') cell.classList.add(...BORDER_CLASSES);
            else if (action === 'b-none') cell.classList.remove(...BORDER_CLASSES);
            else if (action === 'b-outer') {
                if (b.minR === rectangle.minR) cell.classList.add('b-top');
                if (b.maxR === rectangle.maxR) cell.classList.add('b-bottom');
                if (b.minC === rectangle.minC) cell.classList.add('b-left');
                if (b.maxC === rectangle.maxC) cell.classList.add('b-right');
            } else if (['b-top', 'b-bottom', 'b-left', 'b-right'].includes(action)) {
                if (cell.classList.contains('solid-border')) { cell.classList.remove('solid-border'); cell.classList.add('b-top', 'b-bottom', 'b-left', 'b-right'); }
                cell.classList.toggle(action, applyToAll);
            } else if (action === 'font-bold') {
                content.style.fontWeight = applyToAll ? 'bold' : 'normal';
                if (!applyToAll && !bound(cell)) content.querySelectorAll('b,strong').forEach(node => node.replaceWith(...node.childNodes));
            } else if (action === 'font-up' || action === 'font-down') {
                const size = Number.parseFloat(document.defaultView.getComputedStyle(content).fontSize) || 10;
                const minimum = bound(cell) ? 8 : 6;
                content.style.fontSize = `${action === 'font-up' ? Math.min(512, Math.max(minimum, size + 1)) : Math.max(minimum, size - 1)}px`;
                if (config.enableImportGeometry) content.querySelectorAll('[style]').forEach(node => {
                    const explicit = Number.parseFloat(node.style.fontSize);
                    if (Number.isFinite(explicit)) node.style.fontSize = `${action === 'font-up' ? Math.min(512, Math.max(minimum, explicit + 1)) : Math.max(minimum, explicit - 1)}px`;
                });
            } else if (action.startsWith('align-')) content.style.textAlign = action.slice(6);
            else if (action === 'bg-color' && /^#[\da-f]{6}$/i.test(value || '')) cell.style.backgroundColor = value;
            else if (action === 'bg-none') cell.style.backgroundColor = '';
            else if (action === 'bind-date') { removeBinding(cell); cell.dataset.boundDate = 'true'; }
            else if (action === 'unbind') removeBinding(cell);
        });
        commit();
        status(action === 'unbind' ? 'Vinculación quitada sólo en la selección. El texto visible se conserva.' : 'Cambio aplicado. Puedes deshacerlo o guardar el diseño.');
    }
    function openBindModal() {
        const cells = requireSelection();
        if (!cells.length) return;
        const current = layout();
        const first = current.bounds;
        const ordered = cells.sort((a, b) => first.get(a).minR - first.get(b).minR || first.get(a).minC - first.get(b).minC);
        modalAnchor = employeeBindingAnchor(current, ordered[0]);
        modalSelection = new Set(cells);
        hideContext();
        hideMoreOptions();
        const modal = byId('bind-modal');
        if (!modal) return;
        modalReturnFocus = document.activeElement;
        modal.hidden = false;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'Vincular empleado: nombre, mañana y tarde/noche');
        const select = byId('bind-emp');
        select.value = ordered[0].dataset.boundEmp || '';
        select.setAttribute('aria-label', 'Empleado');
        clearBindingError();
        if (byId('bind-preview')) byId('bind-preview').hidden = false;
        select.focus();
    }
    function clearBindingError() {
        const error = byId('bind-error');
        if (error) { error.hidden = true; error.textContent = ''; }
    }
    function bindingError(message) {
        status(message, true);
        let error = byId('bind-error');
        if (!error) {
            error = document.createElement('p');
            error.id = 'bind-error';
            error.className = 'arqueo-overflow-warning';
            error.setAttribute('role', 'alert');
            byId('bind-emp')?.insertAdjacentElement('afterend', error);
        }
        error.hidden = false;
        error.textContent = message;
    }
    function closeBindModal() {
        const modal = byId('bind-modal');
        if (!modal) return;
        modal.hidden = true;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modalReturnFocus?.focus?.();
        modalAnchor = null;
        modalSelection.clear();
        clearBindingError();
        if (byId('bind-preview')) byId('bind-preview').hidden = true;
    }
    function applyBind() {
        const emp = byId('bind-emp')?.value;
        if (!/^\d+$/.test(emp || '')) { bindingError('Selecciona un empleado para vincular sus tres campos.'); byId('bind-emp')?.focus(); return; }
        if (!modalAnchor || !table.contains(modalAnchor)) { bindingError('La casilla elegida ha cambiado. Cancela y selecciona de nuevo dónde vincular al empleado.'); return; }
        const current = layout();
        const previous = employeeBindingTargets(current, modalAnchor);
        const oldEmployee = modalAnchor.dataset.boundEmp;
        const priorGroup = oldEmployee && previous.length === 3 && previous.every((cell, index) => cell.dataset.boundEmp === oldEmployee && cell.dataset.boundProp === ['nombre', 'manana', 'noche'][index]) ? new Set(previous) : new Set();
        const plan = planEmployeeBinding(current, modalAnchor, {
            requiredWidth: (prop, cell) => bindingWidth(emp, prop, cell),
            canUse: cell => modalSelection.has(cell) || priorGroup.has(cell) || (!cellText(cell) && !bound(cell)),
            canRemoveDivider: (left, right) => modalSelection.has(left) && modalSelection.has(right),
        });
        if (plan.error) {
            const messages = {
                outside: 'No hay ancho suficiente para el nombre y los dos turnos. Elige una casilla más a la izquierda o deja más espacio libre en la fila.',
                occupied: 'Hay texto o datos automáticos en una casilla vecina. Se han conservado. Elige otro espacio o selecciona expresamente las casillas que quieres sustituir.',
                border: 'Una división dibujada impide dar ancho suficiente a los tres campos. Elige otro espacio o selecciona las casillas que quieras reagrupar.',
                shape: 'Las casillas vecinas tienen alturas o combinaciones distintas. Prepara tres campos de la misma altura o elige otro espacio.',
                format: 'Las casillas vecinas tienen fondos o bordes superiores e inferiores distintos. Elige un espacio con formato uniforme para conservar el diseño.',
            };
            bindingError(messages[plan.error]);
            return;
        }
        const affected = plan.fields.flatMap(field => field.cells);
        const overwrites = affected.some(cell => cellText(cell) || bound(cell));
        if (overwrites || plan.removedDividers) {
            const changes = [];
            if (overwrites) changes.push('Se sustituirá el contenido de las casillas seleccionadas o del vínculo anterior.');
            if (plan.removedDividers) changes.push('Se quitarán divisiones interiores dentro de la selección para formar tres campos legibles.');
            if (!document.defaultView.confirm(`${changes.join(' ')} ¿Continuar? Puedes deshacerlo.`)) return;
        }
        clearBindingError();
        const targets = plan.fields.map(field => {
            const cell = field.first;
            if (field.cells.length > 1) {
                const last = field.cells.at(-1);
                const has = (part, side) => part.classList.contains('solid-border') || part.classList.contains(`b-${side}`);
                const borders = ['top', 'bottom'].filter(side => has(cell, side));
                if (has(cell, 'left')) borders.push('left');
                if (has(last, 'right')) borders.push('right');
                cell.classList.remove(...BORDER_CLASSES);
                borders.forEach(side => cell.classList.add(`b-${side}`));
                cell.colSpan = field.width;
                field.cells.forEach(part => { if (part !== cell) part.remove(); });
            }
            removeBinding(cell);
            cell.dataset.boundEmp = emp;
            cell.dataset.boundProp = field.prop;
            return cell;
        });
        active = targets[0]; anchor = active; selected = new Set(targets);
        commit();
        closeBindModal();
        status('Empleado vinculado en tres campos con espacio para nombre y turnos. Se actualizan con la fecha seleccionada. Puedes deshacerlo.');
    }
    function clearGrid() {
        hideMoreOptions();
        if (!editMode) { status('Activa «Editar diseño» para vaciar la hoja.', true); return; }
        if (config.confirmClear !== false && !document.defaultView.confirm('¿Vaciar todo el diseño? Puedes deshacerlo antes de salir de esta página.')) return;
        flushText();
        const blank = blankGrid();
        tbody.replaceWith(blank); tbody = blank;
        selected.clear(); active = null; anchor = null;
        commit();
        status('Hoja vaciada. Puedes deshacer o crear tu diseño libremente.');
    }
    async function saveTemplate() {
        if (config.canEditDesign === false) return;
        flushText();
        if (saving) return;
        const savedSnapshot = snapshot();
        const savedHistory = history;
        saving = true;
        updateButtons();
        status('Guardando diseño…');
        try {
            if (typeof config.onSave === 'function') await config.onSave(savedSnapshot, publicApi);
            else {
                const result = await postTemplate(config, config.dailyMode ? definitionSnapshot(savedSnapshot) : savedSnapshot, document.defaultView.fetch.bind(document.defaultView));
                if (config.dailyMode && result.templateHash) config.templateHash = result.templateHash;
                if (config.dailyMode) config.savedTemplateHtml = definitionSnapshot(savedSnapshot);
            }
            const hasDailyValues = config.dailyMode && Array.from(tbody.querySelectorAll('td[data-arqueo-input="true"] .cell-content')).some(content => content.textContent.trim());
            if (!hasDailyValues) savedHistory.markSaved(savedSnapshot);
            const savedMessage = typeof config.savedMessage === 'function' ? config.savedMessage() : config.savedMessage;
            status(history !== savedHistory ? 'Diseño anterior guardado. La hoja actual se conserva.' : snapshot() === savedSnapshot ? (savedMessage || 'Diseño guardado. Se usará como plantilla en los próximos arqueos.') : 'Diseño guardado. Hay cambios posteriores pendientes de guardar.');
        } catch (error) {
            status(error instanceof TypeError ? 'No se pudo conectar para guardar. Tu trabajo sigue en esta pestaña; vuelve a intentarlo.' : error.message, true);
        } finally { saving = false; updateButtons(); }
    }

    // Load through a strict allow-list. Preserve existing rows, including legacy sheets taller than A4.
    if (config.templateData?.html) {
        const safe = sanitizeTemplate(config.templateData.html, document, config);
        if (config.enableImportGeometry && config.templateData.geometry) {
            const geometry = validateImportGeometry(config.templateData.geometry, buildGridMap(safe.rows));
            if (geometry) setTemplateGeometry(safe, geometry);
        }
        tbody.replaceWith(safe); tbody = safe;
        const importedGeometry = templateGeometry(tbody, config);
        if (!importedGeometry && buildGridMap(tbody.rows).width === 12) Array.from(tbody.querySelectorAll('td')).forEach(cell => { cell.colSpan *= 2; });
        let current = layout();
        while (!importedGeometry && tbody.rows.length < Math.max(ROWS, current.height)) tbody.insertRow();
        // Fill gaps using logical positions, so a rowspan never shifts the appended rows to the right.
        current = layout();
        Array.from(tbody.rows).forEach((row, r) => {
            for (let c = 0; !importedGeometry && c < COLS; c++) {
                if (current.map[r]?.[c]) continue;
                let reference = null;
                for (let next = c + 1; next < current.width; next++) {
                    const candidate = current.map[r]?.[next];
                    if (candidate?.parentElement === row) { reference = candidate; break; }
                }
                row.insertBefore(makeCell(), reference);
            }
        });
    } else { const blank = blankGrid(); tbody.replaceWith(blank); tbody = blank; }
    normalizeGrid();
    history = new ArqueoHistory(snapshot());
    updateButtons();

    table.addEventListener('pointerdown', event => {
        if (!editMode) return;
        const cell = gridCell(event.target);
        if (!cell) return;
        // Keep native right-button focus from reducing a selected rectangle before contextmenu.
        if (event.button === 2 && selected.has(cell)) { event.preventDefault(); return; }
        if (event.button !== 0) return;
        hideContext();
        flushText();
        if (editingText && cell === active && !event.shiftKey) return;
        editingText = false; dragging = true; dragged = false;
        event.preventDefault();
        selectCell(cell, event.shiftKey, true);
        if (selected.size > 1) document.defaultView.getSelection()?.removeAllRanges();
    });
    table.addEventListener('pointerover', event => {
        if (!dragging || !anchor) return;
        if (!(event.buttons & 1)) { stopDragging(); return; }
        const cell = gridCell(event.target);
        if (!cell || cell === active) return;
        dragged = true;
        selectCell(cell, true);
        document.defaultView.getSelection()?.removeAllRanges();
    });
    document.addEventListener('pointerup', () => {
        if (dragging && dragged) updateSelection(true);
        dragging = false;
    });
    document.addEventListener('pointercancel', stopDragging);
    table.addEventListener('dblclick', event => {
        const cell = gridCell(event.target);
        if (cell && !readonly(cell)) { selectCell(cell); editingText = true; cellContent(cell).focus(); }
    });
    table.addEventListener('focusin', event => {
        const cell = gridCell(event.target);
        if (cell && (cell !== active || (!editMode && selected.size > 1))) selectCell(cell);
    });
    table.addEventListener('input', event => {
        const cell = gridCell(event.target);
        if (!cell || readonly(cell)) return;
        if (config.dailyMode && editMode) delete cell.dataset.arqueoInput;
        editingText = true;
        pendingText = true;
        updateButtons();
        status('Cambios pendientes de guardar.');
        if (byId('template-empty-help')) byId('template-empty-help').hidden = true;
        scheduleOverflow();
    });
    table.addEventListener('focusout', () => { flushText(); editingText = false; });
    table.addEventListener('paste', event => {
        const cell = gridCell(event.target);
        if (!cell) return;
        event.preventDefault();
        if (config.dailyMode && readonly(cell)) return;
        const text = event.clipboardData?.getData('text/plain') || '';
        const encodedGrid = event.clipboardData?.getData('application/x-mmqp-grid') === '1' || /<table(?:\s|>)/i.test(event.clipboardData?.getData('text/html') || '');
        if (!editingText || text.includes('\t') || encodedGrid || bound(cell)) { pasteRange(text, encodedGrid); return; }
        const selection = document.defaultView.getSelection();
        if (selection?.rangeCount && cellContent(cell).contains(selection.anchorNode)) {
            const range = selection.getRangeAt(0);
            range.deleteContents();
            const node = document.createTextNode(text);
            range.insertNode(node); range.setStartAfter(node); range.collapse(true);
            selection.removeAllRanges(); selection.addRange(range);
        } else cellContent(cell).append(document.createTextNode(text));
        if (config.dailyMode && editMode) delete cell.dataset.arqueoInput;
        pendingText = true; editingText = true; updateButtons();
        scheduleOverflow();
        status('Texto pegado sin formato. Cambios pendientes de guardar.');
    });
    table.addEventListener('copy', event => {
        if (!gridCell(event.target) || !event.clipboardData) return;
        if (editingText && document.defaultView.getSelection()?.toString()) return;
        event.preventDefault();
        event.clipboardData.setData('text/plain', gridSelectionText(layout(), selected, readCellText));
        event.clipboardData.setData('application/x-mmqp-grid', '1');
        status('Selección copiada. Puedes pegarla en Excel o en otra zona de esta hoja.');
    });
    table.addEventListener('drop', event => { event.preventDefault(); status('Para añadir texto, usa copiar y pegar dentro de la celda.'); });
    document.addEventListener('keydown', event => {
        const modal = byId('bind-modal');
        if (modal && !modal.hidden && !modal.classList.contains('hidden')) {
            if (event.key === 'Escape') { event.preventDefault(); closeBindModal(); }
            if (event.key === 'Tab') {
                const controls = Array.from(modal.querySelectorAll('button:not([disabled]),select,input,[tabindex="0"]')).filter(node => !node.hidden);
                const first = controls[0], last = controls.at(-1);
                if (event.shiftKey && (document.activeElement === first || !modal.contains(document.activeElement))) { event.preventDefault(); last?.focus(); }
                else if (!event.shiftKey && (document.activeElement === last || !modal.contains(document.activeElement))) { event.preventDefault(); first?.focus(); }
            }
            return;
        }
        if (event.key === 'Escape') { if (moreOptionsOpen()) { event.preventDefault(); hideMoreOptions(true); } hideContext(true); if (editingText) { flushText(); editingText = false; event.preventDefault(); } return; }
        const cell = gridCell(event.target);
        if (!cell) return;
        if ((event.ctrlKey || event.metaKey) && ['z', 'y'].includes(event.key.toLowerCase())) {
            event.preventDefault();
            if (event.key.toLowerCase() === 'y' || event.shiftKey) redo(); else undo();
            return;
        }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') { event.preventDefault(); if (config.dailyMode && !editMode) byId('btn-save-daily')?.click(); else saveTemplate(); return; }
        if (editMode && !editingText && ['Delete', 'Backspace'].includes(event.key)) {
            event.preventDefault();
            clearContent(Array.from(selected));
            return;
        }
        if (event.key === 'F2' || (event.key === 'Enter' && !editingText)) {
            event.preventDefault();
            if (!readonly(cell)) { editingText = true; const range = document.createRange(); range.selectNodeContents(cellContent(cell)); range.collapse(false); const selection = document.defaultView.getSelection(); selection.removeAllRanges(); selection.addRange(range); }
            return;
        }
        const navigating = (!editingText && editMode && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) || event.key === 'Tab' || (event.key === 'Enter' && !event.shiftKey);
        if (!navigating) return;
        flushText(); editingText = false;
        const current = layout();
        const b = current.bounds.get(cell);
        let r = b.minR, c = b.minC;
        if (event.key === 'ArrowDown' || event.key === 'Enter') r = b.maxR + 1;
        if (event.key === 'ArrowUp') r = b.minR - 1;
        if (event.key === 'ArrowRight' || (event.key === 'Tab' && !event.shiftKey)) c = b.maxC + 1;
        if (event.key === 'ArrowLeft' || (event.key === 'Tab' && event.shiftKey)) c = b.minC - 1;
        if (event.key === 'Tab' && c >= current.width) { c = 0; r++; }
        if (event.key === 'Tab' && c < 0) { c = current.width - 1; r--; }
        const next = current.map[r]?.[c];
        if (next) { event.preventDefault(); selectCell(next, event.shiftKey && event.key !== 'Tab', true); next.scrollIntoView?.({ block: 'nearest', inline: 'nearest' }); }
        else if (event.key !== 'Tab') event.preventDefault();
    });
    table.addEventListener('contextmenu', event => {
        if (!editMode) return;
        const cell = gridCell(event.target);
        if (!cell) return;
        event.preventDefault();
        stopDragging();
        hideMoreOptions();
        if (!selected.has(cell)) selectCell(cell);
        const menu = byId('context-menu');
        if (!menu) return;
        contextReturnFocus = cellContent(active);
        menu.hidden = false; menu.style.display = 'block';
        refreshContextFormat();
        const view = document.defaultView;
        const visible = view.visualViewport;
        const viewport = {
            width: visible?.width || view.innerWidth, height: visible?.height || view.innerHeight,
            offsetLeft: visible?.offsetLeft || 0, offsetTop: visible?.offsetTop || 0,
        };
        menu.style.maxWidth = `${Math.max(1, viewport.width - 16)}px`;
        menu.style.maxHeight = `${Math.max(1, Math.min(400, viewport.height - 16))}px`;
        const rect = cell.getBoundingClientRect();
        const keyboard = event.clientX === 0 && event.clientY === 0;
        const position = contextMenuPosition(
            { x: keyboard ? rect.left : event.clientX, y: keyboard ? rect.bottom : event.clientY },
            { width: menu.offsetWidth, height: menu.offsetHeight }, viewport,
        );
        menu.style.left = `${position.left}px`;
        menu.style.top = `${position.top}px`;
        menu.scrollTop = 0;
        menu.querySelector('[role^="menuitem"]:not([disabled])')?.focus({ preventScroll: true });
    });
    const menu = byId('context-menu');
    menu?.addEventListener('keydown', event => {
        if (event.key === 'Tab') { hideContext(true); return; }
        if (!['ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const items = Array.from(menu.querySelectorAll('[role^="menuitem"]:not([disabled])'));
        const index = items.indexOf(document.activeElement);
        let next = index;
        if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = items.length - 1;
        else next = (index + (['ArrowUp', 'ArrowLeft'].includes(event.key) ? -1 : 1) + items.length) % items.length;
        const item = items[next];
        item?.focus({ preventScroll: true });
        if (item) {
            const menuRect = menu.getBoundingClientRect();
            const itemRect = item.getBoundingClientRect();
            const visibleTop = menuRect.top + (menu.querySelector('.arqueo-context-heading')?.offsetHeight || 0) + 12;
            const visibleBottom = menuRect.bottom - 8;
            if (itemRect.top < visibleTop) menu.scrollTop += itemRect.top - visibleTop;
            else if (itemRect.bottom > visibleBottom) menu.scrollTop += itemRect.bottom - visibleBottom;
        }
    });
    menu?.addEventListener('click', event => { if (event.target.closest('button')) hideContext(); });
    byId('btn-close-context')?.addEventListener('click', () => hideContext(true));
    document.addEventListener('pointerdown', event => {
        if (!event.target.closest('#context-menu')) hideContext();
        if (!event.target.closest('#arqueo-more-options') && !event.target.closest('#btn-more-options')) hideMoreOptions();
    });
    document.addEventListener('focusin', event => {
        if (moreOptionsOpen() && !event.target.closest('#arqueo-more-options') && !event.target.closest('#btn-more-options')) hideMoreOptions();
    });
    document.addEventListener('scroll', event => {
        if (!menu?.contains(event.target) && !byId('arqueo-more-options')?.contains(event.target)) { stopDragging(); hideContext(); hideMoreOptions(); }
    }, true);
    document.defaultView.addEventListener('blur', () => { stopDragging(); hideContext(); hideMoreOptions(); });
    const viewportChanged = () => { stopDragging(); hideContext(); hideMoreOptions(); scheduleOverflow(); measureToolbar(); };
    document.defaultView.addEventListener('resize', viewportChanged);
    document.defaultView.visualViewport?.addEventListener('resize', viewportChanged);
    document.defaultView.visualViewport?.addEventListener('scroll', () => { stopDragging(); hideContext(); hideMoreOptions(); });
    byId('edit-toolbar')?.addEventListener('pointerdown', event => { if (!event.target.matches('input,select,textarea')) event.preventDefault(); });
    byId('bind-modal')?.addEventListener('click', event => { if (event.target === byId('bind-modal')) closeBindModal(); });
    byId('bind-emp')?.addEventListener('change', clearBindingError);
    byId('btn-undo')?.addEventListener('click', undo);
    byId('btn-redo')?.addEventListener('click', redo);
    byId('btn-more-options')?.addEventListener('click', toggleMoreOptions);
    byId('arqueo-more-options')?.addEventListener('click', event => { if (event.target.closest('button,a')) hideMoreOptions(); });
    byId('arqueo-more-options')?.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); hideMoreOptions(true); return; }
        if (event.key === 'Tab') { hideMoreOptions(true); return; }
        if (!['ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        event.stopPropagation();
        const items = Array.from(byId('arqueo-more-options').querySelectorAll('[role="menuitem"]:not([disabled])'));
        const index = items.indexOf(document.activeElement);
        let next = index;
        if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = items.length - 1;
        else next = (index + (['ArrowUp', 'ArrowLeft'].includes(event.key) ? -1 : 1) + items.length) % items.length;
        const item = items[next];
        item?.focus({ preventScroll: true });
        if (item) {
            const popup = byId('arqueo-more-options');
            const popupRect = popup.getBoundingClientRect();
            const itemRect = item.getBoundingClientRect();
            if (itemRect.top < popupRect.top + 8) popup.scrollTop += itemRect.top - popupRect.top - 8;
            else if (itemRect.bottom > popupRect.bottom - 8) popup.scrollTop += itemRect.bottom - popupRect.bottom + 8;
        }
    });
    const moreTrigger = byId('btn-more-options');
    moreTrigger?.setAttribute('aria-controls', 'arqueo-more-options');
    moreTrigger?.setAttribute('aria-haspopup', 'menu');
    moreTrigger?.setAttribute('aria-expanded', 'false');
    if (typeof document.defaultView.ResizeObserver === 'function' && byId('edit-toolbar')) {
        const toolbarObserver = new document.defaultView.ResizeObserver(measureToolbar);
        toolbarObserver.observe(byId('edit-toolbar').closest('.arqueo-design-controls') || byId('edit-toolbar'));
    }
    const form = byId('form-fecha');
    const dateInput = form?.querySelector('[name="fecha"]');
    dateInput?.removeAttribute('onchange');
    form?.addEventListener('submit', event => {
        flushText();
        if (dateInput?.value === config.fecha) {
            event.preventDefault();
            status('La hoja ya muestra esta fecha. El diseño se conserva.');
            return;
        }
        if (!navigationApproved && history.dirty && !document.defaultView.confirm('Hay cambios sin guardar. Cambiar de fecha los descartará. ¿Continuar?')) {
            event.preventDefault();
            if (dateInput) dateInput.value = config.fecha;
            return;
        }
        navigationApproved = true;
    });
    document.defaultView.addEventListener('beforeunload', event => {
        if (!navigationApproved && (pendingText || history.dirty)) { event.preventDefault(); event.returnValue = ''; }
    });
    document.defaultView.addEventListener('beforeprint', () => { scanOverflow(); drawPrintBorders(table); });
    document.defaultView.addEventListener('afterprint', () => clearPrintBorders(table));
    Object.assign(document.defaultView, { toggleEditMode, printArqueo, cmd, openBindModal, closeBindModal, applyBind, clearGrid, saveTemplate });
    hideContext();
    hideMoreOptions();
    const toolbar = byId('edit-toolbar');
    if (toolbar) toolbar.hidden = true;
    measureToolbar();
    byId('btn-edit-mode')?.setAttribute('aria-pressed', 'false');
    if (byId('bind-modal')) byId('bind-modal').hidden = true;
    status(config.initialMessage || 'Hoja lista. Rellena las casillas o activa «Editar diseño» para organizar la hoja a tu medida.');
    function definitionSnapshot(html) {
        return templateDefinitionHtml(html, document);
    }
    publicApi = {
        undo, redo, saveTemplate, snapshot, getHtml: () => { flushText(); return snapshot(); },
        getTemplateHtml: () => { flushText(); return config.dailyMode ? definitionSnapshot(snapshot()) : snapshot(); },
        getSelection: () => ({ active, anchor, cells: new Set(selected), layout: layout() }),
        getLayout: layout,
        get table() { return table; },
        get tbody() { return tbody; },
        get editMode() { return editMode; },
        get dirty() { return pendingText || history.dirty; },
        selectCell, status,
        refreshBindings: () => { populateData(); scheduleOverflow(); },
        runTransaction: callback => {
            flushText();
            const before = snapshot();
            try {
                const result = callback(publicApi);
                if (result && typeof result.then === 'function') throw new TypeError('Las transacciones de la hoja deben ser síncronas.');
                commit();
                return result;
            } catch (error) { restore(before); throw error; }
        },
        loadHtml: (html, options = {}) => {
            if (options.geometry !== undefined) {
                const safe = sanitizeTemplate(html, document, config);
                const geometry = config.enableImportGeometry && validateImportGeometry(options.geometry, buildGridMap(safe.rows));
                if (!geometry) throw new Error('La estructura de la plantilla importada no es válida. La hoja actual se conserva.');
                setTemplateGeometry(safe, geometry);
                html = safe.innerHTML;
            }
            flushText();
            restore(html);
            const loaded = snapshot();
            if (options.resetHistory) history = new ArqueoHistory(loaded);
            else history.commit(loaded);
            if (options.markSaved) history.markSaved(loaded);
            updateButtons();
        },
        setSavedHtml: html => {
            flushText();
            history.markSaved(html === undefined ? snapshot() : templateSnapshot(sanitizeTemplate(html, document, config), config));
            updateButtons();
        },
    };
    document.defaultView.arqueoSheet = publicApi;
    if (config.startInEditMode === true) toggleEditMode();
    initializing = false;
    notify('ready');
    config.onReady?.(publicApi);
    return publicApi;
}

if (typeof window !== 'undefined') {
    const boot = () => { if (window.arqueoConfig) initializeArqueo(window.arqueoConfig); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
}
