// Collapse shared and overlapping cell edges into one print stroke.
export function mergePrintEdges(edges) {
    const groups = new Map();
    for (const { axis, position, start, end } of edges) {
        const coordinate = Math.round(position * 1000) / 1000;
        const key = `${axis}:${coordinate}`;
        if (!groups.has(key)) groups.set(key, { axis, position: coordinate, intervals: [] });
        groups.get(key).intervals.push([Math.min(start, end), Math.max(start, end)]);
    }
    const result = [];
    for (const { axis, position, intervals } of groups.values()) {
        intervals.sort((a, b) => a[0] - b[0]);
        let previous;
        for (const [start, end] of intervals) {
            if (previous && start <= previous.end + .001) previous.end = Math.max(previous.end, end);
            else { previous = { axis, position, start, end }; result.push(previous); }
        }
    }
    return result;
}

export function clearPrintBorders(grid, { id = 'arqueo-print-borders' } = {}) {
    grid.ownerDocument.getElementById(id)?.remove();
    grid.removeAttribute('data-print-borders');
}

export function drawPrintBorders(grid, { id = 'arqueo-print-borders', className } = {}) {
    clearPrintBorders(grid, { id });
    const bounds = grid.getBoundingClientRect();
    if (!bounds.width || !bounds.height) return;
    const edges = [];
    for (const cell of grid.querySelectorAll('td')) {
        const rect = cell.getBoundingClientRect();
        const left = rect.left - bounds.left, right = rect.right - bounds.left;
        const top = rect.top - bounds.top, bottom = rect.bottom - bounds.top;
        const has = side => cell.classList.contains('solid-border') || cell.classList.contains(`b-${side}`);
        if (has('top')) edges.push({ axis: 'y', position: top, start: left, end: right });
        if (has('bottom')) edges.push({ axis: 'y', position: bottom, start: left, end: right });
        if (has('left')) edges.push({ axis: 'x', position: left, start: top, end: bottom });
        if (has('right')) edges.push({ axis: 'x', position: right, start: top, end: bottom });
    }
    const ns = 'http://www.w3.org/2000/svg';
    const svg = grid.ownerDocument.createElementNS(ns, 'svg');
    svg.id = id;
    if (className) svg.setAttribute('class', className);
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('viewBox', `0 0 ${bounds.width} ${bounds.height}`);
    svg.setAttribute('preserveAspectRatio', 'none');
    svg.style.width = `${bounds.width}px`;
    svg.style.height = `${bounds.height}px`;
    const path = grid.ownerDocument.createElementNS(ns, 'path');
    path.setAttribute('d', mergePrintEdges(edges).map(edge => edge.axis === 'x'
        ? `M${edge.position} ${edge.start}V${edge.end}`
        : `M${edge.start} ${edge.position}H${edge.end}`).join(''));
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke', '#000');
    path.setAttribute('stroke-width', '1');
    path.setAttribute('vector-effect', 'non-scaling-stroke');
    svg.append(path);
    grid.parentElement.append(svg);
    grid.setAttribute('data-print-borders', '');
}
