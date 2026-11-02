const MAX_STEPS = 32;
const hasSize = metrics => Number.isFinite(metrics?.clientWidth) && metrics.clientWidth > 0
    && Number.isFinite(metrics?.clientHeight) && metrics.clientHeight > 0;
const overflows = metrics => metrics.scrollWidth > metrics.clientWidth + 1 || metrics.scrollHeight > metrics.clientHeight + 1;

/** Choose a smaller font from measured text; never enlarge an existing font. */
export function chooseConsumoFont(currentFont, measure, { minFont = 8, maxFont = 14 } = {}) {
    if (!Number.isFinite(currentFont) || currentFont <= 0 || !Number.isFinite(minFont) || minFont <= 0
        || !Number.isFinite(maxFont) || maxFont < minFont || typeof measure !== 'function') {
        throw new Error('Las dimensiones del ajuste de texto no son válidas.');
    }
    let metrics = measure(currentFont);
    if (!hasSize(metrics)) return { fontSize: currentFont, reduced: false, fits: false, hidden: true };
    if (!overflows(metrics)) return { fontSize: currentFont, reduced: false, fits: true, hidden: false };
    if (currentFont <= minFont) return { fontSize: currentFont, reduced: false, fits: false, hidden: false };

    let fontSize = currentFont;
    for (let step = 0; step < MAX_STEPS && fontSize > minFont; step++) {
        fontSize = step === MAX_STEPS - 1 ? minFont : Math.max(minFont, Math.min(maxFont, Math.ceil(fontSize) - 1));
        metrics = measure(fontSize);
        if (!hasSize(metrics)) return { fontSize, reduced: true, fits: false, hidden: true };
        if (!overflows(metrics)) return { fontSize, reduced: true, fits: true, hidden: false };
    }
    return { fontSize, reduced: fontSize < currentFont, fits: false, hidden: false };
}

/** Fit visible cell text without changing content, links, row heights or widths. */
export function fitConsumoText(cells, options = {}) {
    let fitted = 0; let unresolved = 0;
    const unique = new Set(cells || []);
    for (const cell of unique) {
        const content = cell?.querySelector?.('.cell-content');
        if (!content || !content.textContent?.trim()) continue;
        const initial = {
            clientWidth: content.clientWidth, clientHeight: content.clientHeight,
            scrollWidth: content.scrollWidth, scrollHeight: content.scrollHeight,
        };
        if (!hasSize(initial) || !overflows(initial)) continue;
        const computed = cell.ownerDocument?.defaultView?.getComputedStyle(content);
        const currentFont = Number.parseFloat(computed?.fontSize || content.style.fontSize || cell.style.fontSize) || 10;
        const result = chooseConsumoFont(currentFont, fontSize => {
            if (fontSize === currentFont) return initial;
            // The serializer preserves styles on both elements. Persist the same
            // value on the cell and its editable content to survive reload/print.
            cell.style.fontSize = `${fontSize}px`;
            content.style.fontSize = `${fontSize}px`;
            return {
                clientWidth: content.clientWidth, clientHeight: content.clientHeight,
                scrollWidth: content.scrollWidth, scrollHeight: content.scrollHeight,
            };
        }, options);
        if (result.hidden) continue;
        if (result.fits && result.reduced) fitted++;
        else if (!result.fits) unresolved++;
    }
    return { fitted, unresolved };
}
