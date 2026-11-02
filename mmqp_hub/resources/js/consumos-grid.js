import { buildGridMap } from './arqueo-editor.js';

export const CONSUMO_ATTRIBUTES = ['data-consumo-provider', 'data-consumo-product', 'data-consumo-field', 'data-consumo-supplier', 'data-consumo-sources', 'data-consumo-columns'];
export const PRODUCT_FIELDS = ['product', 'consumo', 'stock', 'pedido'];

export function fieldWidths(width) {
    const name = Math.max(2, Math.floor(width * 5 / 12));
    const consumption = Math.max(2, Math.floor(width * 3 / 12));
    const stock = Math.max(1, Math.floor((width - name - consumption) / 2));
    return [name, consumption, stock, width - name - consumption - stock];
}

export function readSources(cell) {
    const value = JSON.parse(cell?.getAttribute('data-consumo-sources') || '[]');
    if (!Array.isArray(value) || value.some(id => typeof id !== 'string')) throw new Error('El vínculo de esta casilla no es válido. Vuelve a elegir sus filas.');
    return value;
}

const cellText = cell => cell?.querySelector('.cell-content')?.innerText ?? cell?.querySelector('.cell-content')?.textContent ?? '';

export function readSuppliers(tbody) {
    const suppliers = [...tbody.querySelectorAll('[data-consumo-provider]')].map(cell => ({ id: cell.dataset.consumoProvider, name: cellText(cell).trim(), products: [] }));
    const providers = new Map(suppliers.map(supplier => [supplier.id, supplier]));
    const profiles = ['product,consumo,stock,pedido', 'product,consumo,stock', 'product,consumo,stock,pedido,m,v', 'quantity,product,consumo,stock,pedido', 'quantity,product,consumo,stock,pedido,m,v'];
    const providerFields = new Map([...tbody.querySelectorAll('[data-consumo-provider]')].map(cell => {
        const profile = cell.getAttribute('data-consumo-columns') ?? profiles[0];
        if (!profiles.includes(profile)) throw new Error('El proveedor tiene una distribución de columnas no válida.');
        return [cell.dataset.consumoProvider, profile.split(',').filter(field => PRODUCT_FIELDS.includes(field))];
    }));
    if (providers.size !== suppliers.length) throw new Error('Hay un proveedor repetido en esta ficha.');
    const productCells = new Map();
    for (const part of tbody.querySelectorAll('[data-consumo-product]')) {
        const id = part.dataset.consumoProduct;
        if (!productCells.has(id)) productCells.set(id, []);
        productCells.get(id).push(part);
    }
    const seenProducts = new Set();
    for (const cell of tbody.querySelectorAll('[data-consumo-field="product"]')) {
        const id = cell.dataset.consumoProduct;
        const supplier = providers.get(cell.dataset.consumoSupplier);
        if (!supplier) throw new Error('Hay un producto sin proveedor. Quita su tipo especial y vuelve a marcarlo bajo un proveedor.');
        if (!id || seenProducts.has(id)) throw new Error('Hay un producto repetido o sin identificador en la ficha.');
        seenProducts.add(id);
        const fields = productCells.get(id) || [];
        const byField = new Map(fields.map(part => [part.dataset.consumoField, part]));
        const expected = providerFields.get(supplier.id);
        if (fields.length !== expected.length || expected.some(field => !byField.has(field))
            || fields.some(part => part.dataset.consumoSupplier !== supplier.id || part.hasAttribute('data-consumo-provider')
                || part.hasAttribute('data-consumo-columns') || (part.dataset.consumoField !== 'consumo' && part.hasAttribute('data-consumo-sources')))) {
            throw new Error('El producto debe conservar todas las casillas de la distribución de su proveedor.');
        }
        supplier.products.push({ id, name: cellText(cell).trim(), stock: cellText(byField.get('stock')), pedido: cellText(byField.get('pedido')), source_ids: readSources(byField.get('consumo')) });
    }
    return suppliers;
}

// Plan every rectangle before changing any cell. Merged ranges are indivisible.
export function planBlocks(layout, rectangles, allowedTextCells = new Set()) {
    return rectangles.map(rectangle => {
        if (rectangle.minR < 0 || rectangle.maxR >= layout.height || rectangle.minC < 0 || rectangle.maxC >= layout.width) throw new Error('No hay casillas suficientes para este cuadro. Selecciona una zona con más espacio.');
        const cells = new Set();
        for (let r = rectangle.minR; r <= rectangle.maxR; r++) for (let c = rectangle.minC; c <= rectangle.maxC; c++) {
            const cell = layout.map[r]?.[c];
            const bounds = layout.bounds.get(cell);
            if (!bounds || bounds.minR < rectangle.minR || bounds.maxR > rectangle.maxR || bounds.minC < rectangle.minC || bounds.maxC > rectangle.maxC) throw new Error('El cuadro cruza una casilla unida. Separa esa casilla o elige otra zona.');
            if (CONSUMO_ATTRIBUTES.some(attribute => cell.hasAttribute(attribute)) || cell.hasAttribute('data-bound-emp') || cell.hasAttribute('data-bound-date')) throw new Error('Estas casillas ya tienen un tipo especial. Elige otra zona o quita su tipo primero.');
            if (cellText(cell).trim() && !allowedTextCells.has(cell)) throw new Error('Hay texto en las casillas que ocupará el cuadro. Se conserva: elige una zona libre.');
            cells.add(cell);
        }
        return { ...rectangle, cells, first: layout.map[rectangle.minR][rectangle.minC] };
    });
}

export function applyBlocks(plans) {
    return plans.map(plan => {
        const cell = plan.first;
        cell.rowSpan = plan.maxR - plan.minR + 1;
        cell.colSpan = plan.maxC - plan.minC + 1;
        plan.cells.forEach(part => { if (part !== cell) part.remove(); });
        cell.classList.add('b-top', 'b-bottom', 'b-left', 'b-right');
        return cell;
    });
}

export function rowRectangles(row, col, widths, height = 2) {
    let next = col;
    return widths.map(width => {
        const rectangle = { minR: row, maxR: row + height - 1, minC: next, maxC: next + width - 1 };
        next += width;
        return rectangle;
    });
}

/** Append one product within its supplier's columns; neighbouring tables stay in place. */
export function appendSupplierProduct(tbody, provider, id) {
    readSuppliers(tbody);
    const layout = buildGridMap(tbody.rows);
    const supplier = provider.dataset.consumoProvider;
    const providerBounds = layout.bounds.get(provider);
    const products = [...tbody.querySelectorAll('[data-consumo-field="product"]')]
        .filter(cell => cell.dataset.consumoSupplier === supplier)
        .sort((a, b) => layout.bounds.get(a).maxR - layout.bounds.get(b).maxR);
    let last = products.at(-1);
    let labelRow = false;
    if (!last) {
        let row = providerBounds.maxR + 1;
        while (row < layout.height) {
            const cells = [...new Set(layout.map[row].slice(providerBounds.minC, providerBounds.maxC + 1))];
            if (cells.some(cell => cellText(cell).trim().toUpperCase() === 'CONSUMO')) { last = cells[0]; labelRow = true; break; }
            if (cells.length !== 1 || !cellText(cells[0]).trim()) break;
            row = layout.bounds.get(cells[0]).maxR + 1;
        }
    }
    if (!last) throw new Error('Prepara las columnas del proveedor antes de añadir un producto.');
    const lastBounds = layout.bounds.get(last);
    const fields = labelRow ? [] : [...tbody.querySelectorAll('[data-consumo-product]')].filter(cell => cell.dataset.consumoProduct === last.dataset.consumoProduct);
    const minC = providerBounds.minC;
    const maxC = Math.max(providerBounds.maxC, ...fields.map(cell => layout.bounds.get(cell).maxC));
    const row = lastBounds.maxR + 1;
    const height = labelRow ? 2 : lastBounds.maxR - lastBounds.minR + 1;
    const sideProvider = providerBounds.maxC < lastBounds.minC && providerBounds.maxR === lastBounds.maxR;
    const templates = [...new Set(layout.map[lastBounds.minR].slice(minC, maxC + 1))].filter(cell => cell !== provider);
    for (const cell of templates) {
        const b = layout.bounds.get(cell);
        if (b.minR !== lastBounds.minR || b.maxR !== lastBounds.maxR || b.minC < minC || b.maxC > maxC) {
            throw new Error('La última fila tiene casillas unidas fuera del proveedor. Revísala antes de añadir un producto.');
        }
    }
    const occupied = cell => cellText(cell).trim() || CONSUMO_ATTRIBUTES.some(attribute => cell.hasAttribute(attribute))
        || cell.hasAttribute('data-bound-emp') || cell.hasAttribute('data-bound-date')
        || ['solid-border', 'b-top', 'b-bottom', 'b-left', 'b-right'].some(name => cell.classList.contains(name))
        || cell.rowSpan > 1 || cell.colSpan > 1 || cell.querySelector('.cell-content')?.getAttribute('style')
        || cell.style.backgroundColor;
    const overlaps = b => b.maxC >= minC && b.minC <= maxC;
    const shift = [...layout.bounds].some(([cell, b]) => overlaps(b) && b.maxR >= row && b.minR < row + height && occupied(cell)) ? height : 0;
    const descriptors = [];
    for (const [cell, b] of layout.bounds) {
        if (overlaps(b) && b.maxR >= row && (b.minR < row || (shift && (b.minC < minC || b.maxC > maxC)))) {
            throw new Error('Hay una casilla unida que cruza otro cuadro. Sepárala antes de añadir el producto.');
        }
        if (!shift && overlaps(b) && b.minR >= row && b.maxR < row + height) continue;
        descriptors.push({ cell, ...b, minR: b.minR + (shift && overlaps(b) && b.minR >= row ? shift : 0), maxR: b.maxR + (shift && overlaps(b) && b.minR >= row ? shift : 0) });
    }
    const rows = Math.max(layout.height, row + height, ...descriptors.filter(item => occupied(item.cell)).map(item => item.maxR + 1));
    const document = tbody.ownerDocument;
    let product;
    templates.forEach((template, index) => {
        const b = layout.bounds.get(template);
        const cell = template.cloneNode(true);
        [...cell.children].filter(child => !child.classList.contains('cell-content')).forEach(child => child.remove());
        cell.querySelector('.cell-content').textContent = '';
        CONSUMO_ATTRIBUTES.forEach(attribute => cell.removeAttribute(attribute));
        cell.removeAttribute('data-bound-emp'); cell.removeAttribute('data-bound-date');
        const field = labelRow ? (provider.dataset.consumoColumns || PRODUCT_FIELDS.join(',')).split(',')[index] : template.dataset.consumoField;
        if (PRODUCT_FIELDS.includes(field)) {
            cell.dataset.consumoProduct = id; cell.dataset.consumoSupplier = supplier; cell.dataset.consumoField = field;
            if (field === 'consumo') cell.dataset.consumoSources = '[]';
            if (field === 'product') product = cell;
        }
        cell.rowSpan = height;
        descriptors.push({ cell, minR: row, maxR: row + height - 1, minC: b.minC, maxC: b.maxC });
    });
    if (!product) throw new Error('No se ha encontrado la columna de producto del proveedor.');
    if (sideProvider) {
        const item = descriptors.find(item => item.cell === provider);
        item.maxR += height;
    }
    const positions = Array.from({ length: rows }, () => Array(layout.width).fill(null));
    for (const item of descriptors) {
        if (item.minR >= rows) continue;
        if (item.maxR >= rows) throw new Error('No se puede desplazar una casilla unida fuera de la hoja.');
        for (let r = item.minR; r <= item.maxR; r++) for (let c = item.minC; c <= item.maxC; c++) {
            if (positions[r][c]) throw new Error('No hay espacio libre para añadir el producto sin alterar otro cuadro.');
            positions[r][c] = item;
        }
    }
    const fragment = document.createDocumentFragment();
    for (let r = 0; r < rows; r++) {
        const tr = document.createElement('tr');
        for (let c = 0; c < layout.width; c++) {
            const item = positions[r][c];
            if (item) {
                if (item.minR === r && item.minC === c) { item.cell.rowSpan = item.maxR - item.minR + 1; tr.append(item.cell); }
            } else {
                const cell = document.createElement('td'); const content = document.createElement('div');
                content.className = 'cell-content'; cell.append(content); tr.append(cell);
            }
        }
        fragment.append(tr);
    }
    tbody.replaceChildren(fragment);
    return product;
}

/** Plan a continuous supplier body without changing existing products or links. */
export function planProductRows(layout, header, target, { namedCell, productHeight = 2 } = {}) {
    const headerBounds = layout.bounds.get(header);
    const targetBounds = layout.bounds.get(target) || target;
    const supplier = header?.getAttribute('data-consumo-provider');
    if (!headerBounds || !supplier || !targetBounds || !['minR', 'maxR', 'minC', 'maxC'].every(key => Number.isInteger(targetBounds[key]))
        || targetBounds.maxR < targetBounds.minR || targetBounds.maxC < targetBounds.minC) {
        throw new Error('Selecciona una casilla debajo del encabezado de un proveedor.');
    }
    if (!Number.isInteger(productHeight) || productHeight < 1) throw new Error('La altura de las filas de producto no es válida.');
    if (targetBounds.minR <= headerBounds.maxR || targetBounds.minC < headerBounds.minC || targetBounds.maxC > headerBounds.maxC) {
        throw new Error('Selecciona una casilla debajo del proveedor, dentro del ancho de su cuadro.');
    }
    const hasBinding = cell => CONSUMO_ATTRIBUTES.some(attribute => cell?.hasAttribute(attribute))
        || cell?.hasAttribute('data-bound-emp') || cell?.hasAttribute('data-bound-date');
    let labelRow = headerBounds.maxR + 1;
    let labelCells;
    // Full-width notes may sit between a provider and its column labels (photo template).
    // Blank grid rows and other occupied ranges cannot be silently treated as headings.
    while (labelRow <= targetBounds.maxR && labelRow < layout.height) {
        const labels = [];
        let col = headerBounds.minC;
        for (const expected of ['PRODUCTO', 'CONSUMO', 'STOCK', 'PEDIDO']) {
            const cell = layout.map[labelRow]?.[col];
            const bounds = layout.bounds.get(cell);
            const value = cellText(cell).trim().toLocaleUpperCase('es');
            if (!bounds || bounds.minR !== labelRow || bounds.maxR !== labelRow || bounds.minC !== col
                || bounds.maxC > headerBounds.maxC || hasBinding(cell) || (value !== expected && !(expected === 'PRODUCTO' && value === ''))) break;
            labels.push(cell);
            col = bounds.maxC + 1;
        }
        if (labels.length === 4) { labelCells = labels; break; }
        const note = layout.map[labelRow]?.[headerBounds.minC];
        const bounds = layout.bounds.get(note);
        if (!bounds || bounds.minR !== labelRow || bounds.minC !== headerBounds.minC || bounds.maxC !== headerBounds.maxC
            || hasBinding(note) || !cellText(note).trim()) break;
        labelRow = bounds.maxR + 1;
    }
    if (!labelCells) throw new Error('El proveedor debe conservar sus encabezados Producto, Consumo, Stock y Pedido.');
    const firstRow = labelRow + 1;
    if (targetBounds.minR < firstRow) throw new Error('Selecciona una casilla debajo de Producto, Consumo, Stock y Pedido.');
    const widths = labelCells.map(cell => { const b = layout.bounds.get(cell); return b.maxC - b.minC + 1; });
    const minC = headerBounds.minC;
    const maxC = layout.bounds.get(labelCells[3]).maxC;
    if (targetBounds.minC > maxC || targetBounds.maxC > maxC) {
        throw new Error('Selecciona una casilla de Producto, Consumo, Stock o Pedido dentro del cuadro del proveedor.');
    }
    if (targetBounds.maxR >= layout.height) throw new Error('No hay casillas suficientes para este cuadro. Selecciona una zona con más espacio.');
    const allCells = [...layout.bounds.keys()];
    const existingRow = row => {
        const cells = new Set(layout.map[row]?.slice(minC, maxC + 1));
        if (![...cells].some(hasBinding)) return null;
        const ids = new Set([...cells].map(cell => cell?.getAttribute('data-consumo-product')).filter(Boolean));
        const reject = () => { throw new Error('El cuadro cruza casillas ocupadas o un producto incompleto. Se conservan: revisa esa fila antes de ampliarlo.'); };
        if (ids.size !== 1) reject();
        const id = [...ids][0];
        const fields = allCells.filter(cell => cell.getAttribute('data-consumo-product') === id);
        if (fields.length !== PRODUCT_FIELDS.length || cells.size !== PRODUCT_FIELDS.length) reject();
        const byField = new Map(fields.map(cell => [cell.getAttribute('data-consumo-field'), cell]));
        if (PRODUCT_FIELDS.some(field => !byField.has(field))) reject();
        const first = layout.bounds.get(byField.get('product'));
        let col = minC;
        for (let index = 0; index < PRODUCT_FIELDS.length; index++) {
            const field = PRODUCT_FIELDS[index];
            const cell = byField.get(field); const b = layout.bounds.get(cell);
            if (!cells.has(cell) || cell.getAttribute('data-consumo-supplier') !== supplier || cell.hasAttribute('data-consumo-provider')
                || cell.hasAttribute('data-bound-emp') || cell.hasAttribute('data-bound-date')
                || (field !== 'consumo' && cell.hasAttribute('data-consumo-sources'))
                || b.minR !== first.minR || b.maxR !== first.maxR || b.minC !== col || b.maxC !== col + widths[index] - 1) reject();
            col += widths[index];
        }
        readSources(byField.get('consumo'));
        return { row: first.minR, height: first.maxR - first.minR + 1, id };
    };
    const rows = []; const preserved = [];
    let row = firstRow;
    let targetRow;
    namedCell ||= layout.bounds.has(target) ? target : undefined;
    const namedBounds = layout.bounds.get(namedCell);
    while (row <= targetBounds.maxR) {
        const existing = existingRow(row);
        if (existing) {
            if (existing.row !== row) throw new Error('El cuadro cruza una fila de producto que empieza fuera de esta zona.');
            preserved.push(existing);
            if (targetBounds.minR < row + existing.height) targetRow = row;
            row += existing.height;
            continue;
        }
        let height = productHeight;
        // Earlier single-row actions may have left a product on an odd grid row.
        // Finish the gap at its exact boundary instead of merging into that product.
        for (let offset = 1; offset < height && row + offset < layout.height; offset++) {
            const next = existingRow(row + offset);
            if (next) {
                if (next.row !== row + offset) throw new Error('El cuadro cruza una casilla unida de otro producto.');
                height = offset;
                break;
            }
        }
        const first = layout.map[row]?.[minC];
        const leftName = cellText(first);
        const selected = namedBounds && namedBounds.minR >= row && namedBounds.maxR < row + height
            && namedBounds.minC >= minC && namedBounds.maxC <= maxC ? namedCell : undefined;
        const selectedName = cellText(selected);
        if (first !== selected && leftName.trim() && selectedName.trim() && leftName.trim() !== selectedName.trim()) {
            throw new Error('Hay textos distintos en esta fila. Muévelos antes de crear el producto para conservarlos todos.');
        }
        const allowed = new Set([first, selected].filter(Boolean));
        const plans = planBlocks(layout, rowRectangles(row, minC, widths, height), allowed);
        rows.push({ row, height, name: leftName.trim() ? leftName : selectedName, plans });
        if (targetBounds.minR >= row && targetBounds.minR < row + height) targetRow = row;
        row += height;
    }
    return { rows, preserved, targetRow, bounds: { minR: firstRow, maxR: row - 1, minC, maxC } };
}

function blankBody(document, height = 73) {
    const tbody = document.createElement('tbody');
    for (let r = 0; r < height; r++) {
        const row = tbody.insertRow();
        for (let c = 0; c < 24; c++) { const cell = row.insertCell(); const content = document.createElement('div'); content.className = 'cell-content'; cell.append(content); }
    }
    return tbody;
}

export function blankGrid(document) {
    return blankBody(document).innerHTML;
}

// Preserve sheets created by the earlier structured editor without assigning new links.
export function legacyGrid(suppliers, document) {
    if (!suppliers.length) return null;
    const needed = Math.max(73, suppliers.reduce((total, supplier) => total + 4 + supplier.products.length * 2, 0));
    const tbody = blankBody(document, needed);
    let r = 0;
    for (const supplier of suppliers) {
        const rectangles = [{ minR: r, maxR: r + 1, minC: 0, maxC: 11 }, ...rowRectangles(r + 2, 0, [5, 3, 2, 2], 1)];
        const [header, ...labels] = applyBlocks(planBlocks(buildGridMap(tbody.rows), rectangles));
        header.dataset.consumoProvider = supplier.id;
        header.querySelector('.cell-content').textContent = supplier.name;
        header.style.backgroundColor = '#e5e7eb'; header.style.textAlign = 'center'; header.style.fontWeight = 'bold';
        labels.forEach((cell, index) => { cell.querySelector('.cell-content').textContent = ['PRODUCTO', 'CONSUMO', 'STOCK', 'PEDIDO'][index]; cell.style.fontWeight = 'bold'; cell.style.textAlign = 'center'; });
        r += 3;
        for (const product of supplier.products) {
            const cells = applyBlocks(planBlocks(buildGridMap(tbody.rows), rowRectangles(r, 0, [5, 3, 2, 2])));
            cells.forEach((cell, index) => { cell.dataset.consumoProduct = product.id; cell.dataset.consumoSupplier = supplier.id; cell.dataset.consumoField = PRODUCT_FIELDS[index]; cell.querySelector('.cell-content').textContent = [product.name, '', product.stock, product.pedido][index]; });
            cells[1].dataset.consumoSources = JSON.stringify(product.source_ids);
            r += 2;
        }
        r++;
    }
    return tbody.innerHTML;
}
