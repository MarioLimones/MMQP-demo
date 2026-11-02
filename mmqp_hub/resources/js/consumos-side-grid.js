import { CONSUMO_ATTRIBUTES, PRODUCT_FIELDS, fieldWidths, planBlocks, readSources, rowRectangles } from './consumos-grid.js';

const LABELS = ['PRODUCTO', 'CONSUMO', 'STOCK', 'PEDIDO'];
const cellText = cell => cell?.querySelector('.cell-content')?.innerText ?? cell?.querySelector('.cell-content')?.textContent ?? '';
const hasBinding = cell => CONSUMO_ATTRIBUTES.some(attribute => cell?.hasAttribute(attribute))
    || cell?.hasAttribute('data-bound-emp') || cell?.hasAttribute('data-bound-date');
const size = bounds => ({ width: bounds.maxC - bounds.minC + 1, height: bounds.maxR - bounds.minR + 1 });
const validBounds = bounds => bounds && ['minR', 'maxR', 'minC', 'maxC'].every(key => Number.isInteger(bounds[key]))
    && bounds.minR >= 0 && bounds.minC >= 0 && bounds.maxR >= bounds.minR && bounds.maxC >= bounds.minC;
const requireCount = count => {
    if (!Number.isInteger(count) || count < 1) throw new Error('Indica cuántas filas de producto quieres crear, como 5 o 7.');
};

/** Plan a supplier beside its products, leaving every consumption unlinked. */
export function planSideBlock(layout, start, productCount, { width = 24, providerWidth = 7, productHeight = 2, headerHeight = 1, namedCell } = {}) {
    requireCount(productCount);
    const selected = layout.bounds.get(start) || start;
    if (!validBounds(selected) || selected.maxR >= layout.height || selected.maxC >= layout.width) {
        throw new Error('Selecciona una casilla libre para empezar el cuadro del proveedor.');
    }
    if (!Number.isInteger(width) || width < 16 || !Number.isInteger(providerWidth) || providerWidth < 2
        || !Number.isInteger(productHeight) || productHeight < 1 || productHeight > 4
        || !Number.isInteger(headerHeight) || headerHeight < 0 || headerHeight > 1) {
        throw new Error('Las dimensiones del cuadro del proveedor no son válidas.');
    }
    const selectedWidth = size(selected).width;
    const blockWidth = Math.min(selectedWidth >= 16 ? selectedWidth : width, layout.width - selected.minC);
    if (blockWidth < 16 || blockWidth - providerWidth < 8) {
        throw new Error('No hay ancho suficiente. Empieza más a la izquierda o selecciona al menos 16 casillas de ancho.');
    }
    const minR = selected.minR; const minC = selected.minC;
    const firstRow = minR + headerHeight;
    const maxR = firstRow + productCount * productHeight - 1;
    if (maxR >= layout.height) throw new Error('No hay filas suficientes para todos los productos. Reduce su número o empieza más arriba.');
    const widths = fieldWidths(blockWidth - providerWidth);
    const productCol = minC + providerWidth;
    const corner = headerHeight ? { minR, maxR: minR, minC, maxC: productCol - 1 } : null;
    const provider = { minR: firstRow, maxR, minC, maxC: productCol - 1 };
    const headers = headerHeight ? rowRectangles(minR, productCol, widths, headerHeight) : [];
    const rectangles = [...(corner ? [corner] : []), provider, ...headers];
    for (let index = 0; index < productCount; index++) rectangles.push(...rowRectangles(firstRow + index * productHeight, productCol, widths, productHeight));
    const allowed = new Set();
    const namedBounds = layout.bounds.get(namedCell);
    if (namedBounds && namedBounds.minC >= minC && namedBounds.maxC < productCol && namedBounds.minR >= minR && namedBounds.maxR <= maxR) allowed.add(namedCell);
    const plans = planBlocks(layout, rectangles, allowed);
    const providerIndex = corner ? 1 : 0;
    const rowOffset = providerIndex + 1 + headers.length;
    const rows = Array.from({ length: productCount }, (_, index) => ({ row: firstRow + index * productHeight, height: productHeight, name: '', plans: plans.slice(rowOffset + index * 4, rowOffset + (index + 1) * 4) }));
    return {
        cornerPlan: corner ? plans[0] : null, providerPlan: plans[providerIndex], headerPlans: plans.slice(providerIndex + 1, rowOffset), rows, plans, widths, productCount, productHeight, headerHeight,
        bounds: { minR, maxR, minC, maxC: minC + blockWidth - 1 },
    };
}

/** Read only the exact side layout; unknown or incomplete structures stay untouched. */
export function readSideBlock(layout, provider) {
    const providerBounds = layout.bounds.get(provider);
    const supplier = provider?.getAttribute('data-consumo-provider');
    const reject = () => { throw new Error('Este cuadro debe conservar el proveedor a la izquierda y las cuatro casillas de cada producto a la derecha.'); };
    if (!providerBounds || !supplier || PRODUCT_FIELDS.some(field => provider.hasAttribute(`data-consumo-${field}`))
        || provider.hasAttribute('data-consumo-field') || provider.hasAttribute('data-consumo-supplier') || provider.hasAttribute('data-consumo-sources')
        || provider.hasAttribute('data-bound-emp') || provider.hasAttribute('data-bound-date')) reject();
    const productCol = providerBounds.maxC + 1;
    // Geometry comes from the four bound fields, so compact blocks can omit their
    // repeated labels without sacrificing extension or the original source links.
    let col = productCol;
    const firstFields = PRODUCT_FIELDS.map(field => {
        const cell = layout.map[providerBounds.minR]?.[col];
        const bounds = layout.bounds.get(cell);
        if (!bounds || bounds.minR !== providerBounds.minR || bounds.minC !== col
            || cell.getAttribute('data-consumo-field') !== field) reject();
        col = bounds.maxC + 1;
        return cell;
    });
    const maxC = col - 1;
    const widths = firstFields.map(cell => size(layout.bounds.get(cell)).width);
    const candidateRow = providerBounds.minR - 1;
    const candidates = firstFields.map(cell => layout.map[candidateRow]?.[layout.bounds.get(cell).minC]);
    const hasHeaders = candidates.some(cell => !hasBinding(cell) && LABELS.includes(cellText(cell).trim().toLocaleUpperCase('es')));
    const headerCells = hasHeaders ? candidates : [];
    if (hasHeaders) headerCells.forEach((cell, index) => {
        const bounds = layout.bounds.get(cell); const fieldBounds = layout.bounds.get(firstFields[index]);
        if (!bounds || bounds.minR !== candidateRow || bounds.maxR !== candidateRow
            || bounds.minC !== fieldBounds.minC || bounds.maxC !== fieldBounds.maxC
            || hasBinding(cell) || cellText(cell).trim().toLocaleUpperCase('es') !== LABELS[index]) reject();
    });
    const allCells = [...layout.bounds.keys()];
    const rows = []; const preserved = [];
    let row = providerBounds.minR; let productHeight;
    while (row <= providerBounds.maxR) {
        const first = layout.map[row]?.[productCol];
        const id = first?.getAttribute('data-consumo-product');
        if (!id) reject();
        const fields = allCells.filter(cell => cell.getAttribute('data-consumo-product') === id);
        if (fields.length !== PRODUCT_FIELDS.length) reject();
        const byField = new Map(fields.map(cell => [cell.getAttribute('data-consumo-field'), cell]));
        if (PRODUCT_FIELDS.some(field => !byField.has(field))) reject();
        const firstBounds = layout.bounds.get(byField.get('product'));
        const height = size(firstBounds).height;
        if (firstBounds.minR !== row || firstBounds.maxR > providerBounds.maxR || (productHeight && productHeight !== height)) reject();
        productHeight ||= height;
        let nextCol = productCol;
        for (let index = 0; index < PRODUCT_FIELDS.length; index++) {
            const field = PRODUCT_FIELDS[index];
            const cell = byField.get(field); const bounds = layout.bounds.get(cell);
            if (cell.getAttribute('data-consumo-supplier') !== supplier || cell.hasAttribute('data-consumo-provider')
                || cell.hasAttribute('data-bound-emp') || cell.hasAttribute('data-bound-date')
                || (field !== 'consumo' && cell.hasAttribute('data-consumo-sources'))
                || bounds.minR !== row || bounds.maxR !== firstBounds.maxR || bounds.minC !== nextCol || size(bounds).width !== widths[index]) reject();
            for (let r = row; r <= firstBounds.maxR; r++) for (let c = bounds.minC; c <= bounds.maxC; c++) {
                if (layout.map[r]?.[c] !== cell) reject();
            }
            nextCol = bounds.maxC + 1;
        }
        readSources(byField.get('consumo'));
        rows.push({ row, height, id, cells: PRODUCT_FIELDS.map(field => byField.get(field)) });
        preserved.push({ row, height, id });
        row += height;
    }
    return { productCount: rows.length, productHeight, headerHeight: hasHeaders ? 1 : 0, widths, headerCells, rows, preserved, bounds: { minR: hasHeaders ? candidateRow : providerBounds.minR, maxR: providerBounds.maxR, minC: providerBounds.minC, maxC } };
}

/** Extend only free space beneath a side block, keeping every existing cell and link. */
export function planSideBlockExtension(layout, provider, desiredTotal) {
    requireCount(desiredTotal);
    const existing = readSideBlock(layout, provider);
    if (desiredTotal < existing.productCount) throw new Error('Puedes añadir filas. Para conservar productos y vínculos, este cuadro no se reduce.');
    const providerBounds = layout.bounds.get(provider);
    const firstRow = providerBounds.maxR + 1;
    const maxR = providerBounds.minR + desiredTotal * existing.productHeight - 1;
    if (maxR >= layout.height) throw new Error('No hay filas suficientes para todos los productos. Reduce su número o mueve el cuadro más arriba.');
    const rectangles = [];
    if (maxR >= firstRow) rectangles.push({ minR: firstRow, maxR, minC: providerBounds.minC, maxC: providerBounds.maxC });
    const rows = [];
    for (let index = existing.productCount; index < desiredTotal; index++) {
        const row = providerBounds.minR + index * existing.productHeight;
        rows.push({ row, height: existing.productHeight, name: '' });
        rectangles.push(...rowRectangles(row, providerBounds.maxC + 1, existing.widths, existing.productHeight));
    }
    const freePlans = planBlocks(layout, rectangles);
    const providerPlan = { ...providerBounds, maxR, first: provider, cells: new Set([provider, ...(rows.length ? freePlans[0].cells : [])]) };
    rows.forEach((row, index) => { row.plans = freePlans.slice(1 + index * 4, 5 + index * 4); });
    return {
        providerPlan, headerPlans: [], rows, plans: [providerPlan, ...rows.flatMap(row => row.plans)],
        preserved: existing.preserved, existingCount: existing.productCount, productCount: desiredTotal, widths: existing.widths, productHeight: existing.productHeight, headerHeight: existing.headerHeight,
        bounds: { ...existing.bounds, maxR },
    };
}
