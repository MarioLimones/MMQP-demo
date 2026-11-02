import { planBlocks, rowRectangles } from './consumos-grid.js';

export const BLOCK_COLUMN_PROFILES = [
    ['product', 'consumo', 'stock', 'pedido'],
    ['product', 'consumo', 'stock'],
    ['product', 'consumo', 'stock', 'pedido', 'm', 'v'],
    ['quantity', 'product', 'consumo', 'stock', 'pedido'],
    ['quantity', 'product', 'consumo', 'stock', 'pedido', 'm', 'v'],
    ['product', 'stock', 'pedido'],
];
export const BLOCK_COLUMN_LABELS = { product: 'PRODUCTO', consumo: 'CONSUMO', stock: 'STOCK', pedido: 'PEDIDO', m: 'M', v: 'V', quantity: '' };

const dimension = (value, min, max) => Number.isInteger(value) && value >= min && value <= max;
const boundsValid = bounds => bounds && ['minR', 'maxR', 'minC', 'maxC'].every(key => Number.isInteger(bounds[key]))
    && bounds.minR >= 0 && bounds.minC >= 0 && bounds.maxR >= bounds.minR && bounds.maxC >= bounds.minC;

/** Integer grid widths keep every column visible, including the narrow M/V and quantity columns. */
export function customColumnWidths(width, columns, supplied) {
    if (!Array.isArray(columns) || !BLOCK_COLUMN_PROFILES.some(profile => profile.join(',') === columns.join(','))) {
        throw new Error('Elige una distribución válida de columnas para este cuadro.');
    }
    if (!dimension(width, columns.length + 1, 100)) throw new Error('No hay ancho suficiente para las columnas del cuadro.');
    if (supplied !== undefined) {
        if (!Array.isArray(supplied) || supplied.length !== columns.length || supplied.some(value => !dimension(value, 1, 100))
            || supplied[columns.indexOf('product')] < 2 || supplied.reduce((sum, value) => sum + value, 0) !== width) {
            throw new Error('Los anchos de las columnas deben sumar el ancho del cuadro.');
        }
        return [...supplied];
    }
    const weights = { product: 5, consumo: 3, stock: 2, pedido: 2, m: 1, v: 1, quantity: 1 };
    const result = columns.map(column => column === 'product' ? 2 : 1);
    const total = columns.reduce((sum, column) => sum + weights[column], 0);
    const remaining = width - columns.length - 1;
    const fractions = columns.map((column, index) => {
        const exact = remaining * weights[column] / total;
        result[index] += Math.floor(exact);
        return { index, remainder: exact % 1 };
    }).sort((a, b) => b.remainder - a.remainder || a.index - b.index);
    let left = width - result.reduce((sum, value) => sum + value, 0);
    for (let index = 0; index < left; index++) result[fractions[index].index]++;
    return result;
}

/** Plan the complete block before a single DOM cell changes. Source links are always selected separately. */
export function planCustomBlock(layout, start, entries, options = {}) {
    const selected = layout.bounds.get(start) || start;
    if (!boundsValid(selected) || selected.maxR >= layout.height || selected.maxC >= layout.width) {
        throw new Error('Selecciona una casilla libre para empezar el cuadro.');
    }
    const columns = [...(options.columns || BLOCK_COLUMN_PROFILES[0])];
    const width = options.width ?? 12;
    const widths = customColumnWidths(width, columns, options.columnWidths);
    const headerHeight = options.headerHeight ?? 2;
    const rowHeight = options.rowHeight ?? 2;
    const labelHeight = options.labelHeight ?? 1;
    if (![headerHeight, rowHeight, labelHeight].every(value => dimension(value, 1, 4))) {
        throw new Error('La altura del encabezado y las filas debe estar entre 1 y 4 casillas.');
    }
    if (!Array.isArray(entries) || entries.length < 1 || entries.length > 120) {
        throw new Error('Indica entre 1 y 120 productos o secciones para el cuadro.');
    }
    const rows = entries.map(entry => {
        if (typeof entry === 'string') entry = { kind: 'product', name: entry };
        else if (entry && entry.kind === undefined) entry = { ...entry, kind: 'product' };
        if (!entry || !['product', 'category'].includes(entry.kind) || typeof entry.name !== 'string'
            || !entry.name.trim() || entry.name.trim().length > 255 || !dimension(entry.height ?? (entry.kind === 'category' ? 1 : rowHeight), 1, 4)) {
            throw new Error('Cada producto o sección necesita un nombre de hasta 255 caracteres y una altura de 1 a 4.');
        }
        if (entry.quantity !== undefined && (typeof entry.quantity !== 'string' || entry.quantity.length > 255)) {
            throw new Error('La cantidad impresa debe ser texto de hasta 255 caracteres.');
        }
        return { kind: entry.kind, name: entry.name.trim(), quantity: entry.quantity ?? '', height: entry.height ?? (entry.kind === 'category' ? 1 : rowHeight) };
    });
    const typed = columns.includes('consumo');
    const title = options.title === false ? null : String(options.title ?? '');
    if (title === null && typed) throw new Error('Los productos con consumo necesitan un encabezado de proveedor.');
    const minR = selected.minR; const minC = selected.minC;
    const maxC = minC + width - 1;
    let nextRow = minR;
    const rectangles = [];
    const providerIndex = title === null ? null : rectangles.length;
    if (providerIndex !== null) {
        rectangles.push({ minR: nextRow, maxR: nextRow + headerHeight - 1, minC, maxC });
        nextRow += headerHeight;
    }
    const labelIndex = rectangles.length;
    const headers = options.headers !== false;
    if (headers) {
        rectangles.push(...rowRectangles(nextRow, minC, widths, labelHeight));
        nextRow += labelHeight;
    }
    for (const row of rows) {
        row.row = nextRow;
        row.planIndex = rectangles.length;
        rectangles.push(...(row.kind === 'category'
            ? [{ minR: nextRow, maxR: nextRow + row.height - 1, minC, maxC }]
            : rowRectangles(nextRow, minC, widths, row.height)));
        nextRow += row.height;
    }
    const plans = planBlocks(layout, rectangles);
    const headerPlans = headers ? plans.slice(labelIndex, labelIndex + columns.length).map((plan, index) => ({ ...plan, column: columns[index] })) : [];
    rows.forEach(row => {
        row.plans = plans.slice(row.planIndex, row.planIndex + (row.kind === 'category' ? 1 : columns.length))
            .map((plan, index) => ({ ...plan, column: row.kind === 'category' ? 'category' : columns[index] }));
        row.fields = Object.fromEntries(row.plans.map(plan => [plan.column, plan]));
        delete row.planIndex;
    });
    return { providerPlan: providerIndex === null ? null : plans[providerIndex], title, headerPlans, rows,
        columns, widths, typed, width, headerHeight, rowHeight, plans,
        bounds: { minR, maxR: nextRow - 1, minC, maxC } };
}
