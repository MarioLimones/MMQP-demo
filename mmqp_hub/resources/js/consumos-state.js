// Exact decimal addition: no float rounding and no implicit unit conversion.
export function sumQuantities(rows) {
    if (!rows.length) return null;
    if (new Set(rows.map(row => row.unit)).size !== 1) throw new Error('Selecciona filas con la misma unidad. No se convierten kg, bolsas ni unidades automáticamente.');
    const values = rows.map(row => String(row.quantity));
    if (values.some(value => !/^[+-]?\d+(\.\d+)?$/.test(value))) throw new Error('Una cantidad del informe no es válida.');
    const scale = Math.max(...values.map(value => (value.split('.')[1] || '').length));
    const total = values.reduce((sum, value) => {
        const negative = value.startsWith('-');
        const [whole, decimals = ''] = value.replace(/^[+-]/, '').split('.');
        const integer = BigInt(whole + decimals.padEnd(scale, '0'));
        return sum + (negative ? -integer : integer);
    }, 0n);
    let digits = (total < 0 ? -total : total).toString().padStart(scale + 1, '0');
    if (scale) digits = `${digits.slice(0, -scale)}.${digits.slice(-scale)}`.replace(/0+$/, '').replace(/\.$/, '');
    return { amount: `${total < 0 ? '-' : ''}${digits}`, unit: rows[0].unit, source_count: rows.length };
}

export function sourceOwners(suppliers) {
    const owners = new Map();
    for (const supplier of suppliers) for (const product of supplier.products) {
        for (const id of product.source_ids) owners.set(id, { productId: product.id, label: `${supplier.name || 'Proveedor'} · ${product.name || 'Producto'}` });
    }
    return owners;
}

export function validateBinding(selectedIds, rows, suppliers, productId) {
    const owners = sourceOwners(suppliers);
    const ids = new Set(selectedIds);
    if (ids.size !== selectedIds.length) throw new Error('Una fila se ha seleccionado más de una vez.');
    const selection = selectedIds.map(id => {
        const row = rows.find(row => row.id === id);
        if (!row) throw new Error('El vínculo apunta a una fila que no pertenece al informe.');
        if (owners.has(id) && owners.get(id).productId !== productId) throw new Error(`Esta fila ya está vinculada a ${owners.get(id).label}.`);
        return row;
    });
    return sumQuantities(selection);
}

export function newSheet() {
    return { id: null, version: null, title: 'Ficha de consumos', import_id: null, suppliers: [] };
}

export function savePayload(sheet) {
    return {
        ...(sheet.id ? { id: sheet.id, version: sheet.version } : {}), title: sheet.title,
        import_id: sheet.import_id, suppliers: sheet.suppliers.map(supplier => ({
            id: supplier.id, name: supplier.name, products: supplier.products.map(product => ({
                id: product.id, name: product.name, stock: product.stock, pedido: product.pedido, source_ids: [...product.source_ids],
            })),
        })),
        ...(typeof sheet.grid_html === 'string' ? { grid_html: sheet.grid_html } : {}),
    };
}
