import { planBlocks, rowRectangles } from './consumos-grid.js';

const validBounds = bounds => bounds && ['minR', 'maxR', 'minC', 'maxC'].every(key => Number.isInteger(bounds[key]))
    && bounds.minR >= 0 && bounds.minC >= 0 && bounds.maxR >= bounds.minR && bounds.maxC >= bounds.minC;
const contains = (outer, inner) => inner && inner.minR >= outer.minR && inner.maxR <= outer.maxR
    && inner.minC >= outer.minC && inner.maxC <= outer.maxC;

/** Keep the photo's supplier / ordering notes / done / reception proportions. */
export function getOrderWidths(width) {
    if (!Number.isInteger(width) || width < 16) throw new Error('El cuadro de pedidos necesita al menos 16 casillas de ancho.');
    const provider = Math.max(3, Math.round(width * 7 / 24));
    const ordering = Math.max(4, Math.floor(width * 10 / 24));
    const done = Math.max(2, Math.floor(width * 3 / 24));
    return [provider, ordering, done, width - provider - ordering - done];
}

/** Plan an ordinary editable ordering table; this structure never creates consumption links. */
export function planOrderBlock(layout, start, names, { width = 24, rowHeight = 2, headerHeight = 2, namedCell } = {}) {
    const selected = layout.bounds.get(start) || start;
    if (!validBounds(selected) || selected.maxR >= layout.height || selected.maxC >= layout.width) {
        throw new Error('Selecciona una casilla libre para empezar el cuadro de pedidos.');
    }
    if (!Array.isArray(names) || names.length < 1 || names.length > 30
        || names.some(name => typeof name !== 'string' || !name.trim() || name.trim().length > 255)) {
        throw new Error('Indica entre 1 y 30 proveedores, con un nombre de hasta 255 caracteres para cada uno.');
    }
    if (!Number.isInteger(width) || width < 16 || !Number.isInteger(rowHeight) || rowHeight < 1 || rowHeight > 4
        || !Number.isInteger(headerHeight) || headerHeight < 1 || headerHeight > 4) {
        throw new Error('Las dimensiones del cuadro de pedidos no son válidas.');
    }
    const selectedWidth = selected.maxC - selected.minC + 1;
    const blockWidth = Math.min(selectedWidth >= 16 ? selectedWidth : width, layout.width - selected.minC);
    if (blockWidth < 16) throw new Error('No hay ancho suficiente. Empieza más a la izquierda o selecciona al menos 16 casillas de ancho.');
    const widths = getOrderWidths(blockWidth);
    const minR = selected.minR; const minC = selected.minC;
    const firstRow = minR + headerHeight;
    const maxR = firstRow + names.length * rowHeight - 1;
    if (maxR >= layout.height) throw new Error('No hay filas suficientes para todos los proveedores. Reduce su número o empieza más arriba.');
    const headers = rowRectangles(minR, minC, widths, headerHeight);
    const rectangles = [...headers];
    names.forEach((_, index) => rectangles.push(...rowRectangles(firstRow + index * rowHeight, minC, widths, rowHeight)));
    const allowed = new Set();
    const namedBounds = layout.bounds.get(namedCell);
    if (contains(headers[0], namedBounds) || contains(rectangles[4], namedBounds)) allowed.add(namedCell);
    const plans = planBlocks(layout, rectangles, allowed);
    return {
        headerPlans: plans.slice(0, 4),
        rows: names.map((name, index) => ({
            name: name.trim(), row: firstRow + index * rowHeight, height: rowHeight,
            plans: plans.slice(4 + index * 4, 8 + index * 4),
        })),
        plans, widths, headerHeight, rowHeight,
        bounds: { minR, maxR, minC, maxC: minC + blockWidth - 1 },
    };
}
