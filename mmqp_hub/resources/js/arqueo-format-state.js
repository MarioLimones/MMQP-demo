const EDGES = ['top', 'bottom', 'left', 'right'];

const aggregate = values => values.length && values.every(Boolean) ? 'on' : values.some(Boolean) ? 'mixed' : 'off';

/** Format feedback for the actual selected cells, including merged ranges. */
export function selectionFormatState(current, cells, describe) {
    const selected = [...new Set(cells)].filter(cell => current.bounds.has(cell));
    const records = selected.map(cell => ({ bounds: current.bounds.get(cell), ...describe(cell) }));
    if (!records.length) return { states: {}, fontSize: null, color: null, hasBinding: false };
    const minR = Math.min(...records.map(record => record.bounds.minR));
    const maxR = Math.max(...records.map(record => record.bounds.maxR));
    const minC = Math.min(...records.map(record => record.bounds.minC));
    const maxC = Math.max(...records.map(record => record.bounds.maxC));
    const perimeter = [];
    for (const { bounds, edges } of records) {
        if (bounds.minR === minR) perimeter.push(edges.top);
        if (bounds.maxR === maxR) perimeter.push(edges.bottom);
        if (bounds.minC === minC) perimeter.push(edges.left);
        if (bounds.maxC === maxC) perimeter.push(edges.right);
    }
    const states = {
        'b-all': aggregate(records.flatMap(record => EDGES.map(edge => record.edges[edge]))),
        'b-outer': aggregate(perimeter),
        'b-none': aggregate(records.map(record => EDGES.every(edge => !record.edges[edge]))),
        'font-bold': aggregate(records.map(record => record.bold)),
        'bg-none': aggregate(records.map(record => !record.background)),
        'bind-date': aggregate(records.map(record => record.date)),
        'bind-employee': aggregate(records.map(record => record.employee)),
    };
    for (const edge of EDGES) states[`b-${edge}`] = aggregate(records.map(record => record.edges[edge]));
    for (const align of ['left', 'center', 'right']) states[`align-${align}`] = aggregate(records.map(record => record.align === align));
    const sizes = [...new Set(records.map(record => record.size))];
    const colors = [...new Set(records.map(record => record.background || null))];
    return {
        states,
        fontSize: sizes.length === 1 ? sizes[0] : 'mixed',
        color: colors.length === 1 ? colors[0] : 'mixed',
        hasBinding: records.some(record => record.date || record.employee),
    };
}

export function cellAppearance(cell, view) {
    const content = cell.querySelector('.cell-content');
    const style = view.getComputedStyle(content);
    const valueStyle = view.getComputedStyle(content.querySelector('[data-bound-value]') || content);
    const weight = style.fontWeight;
    const alignment = style.textAlign;
    const color = cell.style.backgroundColor;
    return {
        edges: Object.fromEntries(EDGES.map(edge => [edge, cell.classList.contains('solid-border') || cell.classList.contains(`b-${edge}`)])),
        bold: weight === 'bold' || Number(weight) >= 600 || [...content.querySelectorAll('b,strong')].some(node => Number(view.getComputedStyle(node).fontWeight) >= 600 || view.getComputedStyle(node).fontWeight === 'bold'),
        size: Math.round((Number.parseFloat(valueStyle.fontSize) || 10) * 10) / 10,
        align: alignment === 'start' ? 'left' : alignment === 'end' ? 'right' : alignment,
        // The selection's blue highlight is screen-only and is not saved formatting.
        background: !color || color === 'transparent' || /^rgba\([^)]*,\s*0\s*\)$/.test(color) ? null : color,
        date: cell.hasAttribute('data-bound-date'),
        employee: cell.hasAttribute('data-bound-emp'),
    };
}

export function updateContextFormatFeedback(menu, format) {
    if (!menu) return;
    for (const button of menu.querySelectorAll('[data-context-action]')) {
        const action = button.dataset.contextAction;
        const state = format.states[action];
        if (state) {
            button.dataset.formatState = state;
            const role = button.getAttribute('role');
            if (role === 'menuitemcheckbox') button.setAttribute('aria-checked', state === 'on' ? 'true' : state === 'mixed' ? 'mixed' : 'false');
            if (role === 'menuitemradio') button.setAttribute('aria-checked', String(state === 'on'));
            const detail = state === 'mixed' ? 'Aplicado en algunas casillas de la selección.' : state === 'on' ? 'Aplicado en toda la selección.' : null;
            if (detail) button.setAttribute('aria-description', detail);
            else button.removeAttribute('aria-description');
        }
        if (action === 'unbind') button.disabled = !format.hasBinding;
    }
    const size = format.fontSize === 'mixed' ? 'Texto · Varios tamaños' : format.fontSize === null ? 'Texto' : `Texto · ${format.fontSize} px`;
    const summary = menu.querySelector('[data-context-text-size]');
    if (summary) summary.textContent = size;
    const swatch = menu.querySelector('[data-color-preview]');
    if (swatch) {
        swatch.dataset.formatState = format.color === 'mixed' ? 'mixed' : format.color ? 'on' : 'off';
        swatch.style.backgroundColor = format.color && format.color !== 'mixed' ? format.color : '';
        const button = swatch.closest('button');
        button?.setAttribute('aria-description', format.color === 'mixed' ? 'Hay varios fondos en la selección.' : format.color ? `Fondo actual: ${format.color}.` : 'La selección no tiene un color de fondo.');
    }
}
