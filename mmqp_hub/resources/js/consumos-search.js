// Search changes what is visible; it never chooses or assigns source rows.
export function normalizeSearch(value) {
    return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
}

export function searchRows(rows, query) {
    const terms = normalizeSearch(query).split(' ').filter(Boolean);
    return rows.filter(row => {
        const name = normalizeSearch(row.name);
        return terms.every(term => name.includes(term));
    });
}
