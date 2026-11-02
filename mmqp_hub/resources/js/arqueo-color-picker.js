export function normalizeHex(value) {
    const code = String(value || '').trim().replace(/^#/, '');
    if (/^[\da-f]{3}$/i.test(code)) return `#${[...code].map(char => char + char).join('').toUpperCase()}`;
    return /^[\da-f]{6}$/i.test(code) ? `#${code.toUpperCase()}` : null;
}

function colorHex(value) {
    const hex = normalizeHex(value);
    if (hex) return hex;
    const rgb = /^rgba?\(\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*[\d.]+)?\s*\)$/.exec(value || '');
    return rgb ? `#${rgb.slice(1, 4).map(channel => Number(channel).toString(16).padStart(2, '0')).join('').toUpperCase()}` : null;
}

function toHsv(hex) {
    const [r, g, b] = hex.slice(1).match(/../g).map(channel => parseInt(channel, 16) / 255);
    const max = Math.max(r, g, b), min = Math.min(r, g, b), delta = max - min;
    let h = 0;
    if (delta) h = 60 * (max === r ? ((g - b) / delta + 6) % 6 : max === g ? (b - r) / delta + 2 : (r - g) / delta + 4);
    return { h, s: max ? delta / max : 0, v: max };
}

function fromHsv({ h, s, v }) {
    const f = n => { const k = (n + h / 60) % 6; return Math.round(255 * v * (1 - s * Math.max(0, Math.min(k, 4 - k, 1)))); };
    return `#${[f(5), f(3), f(1)].map(channel => channel.toString(16).padStart(2, '0')).join('').toUpperCase()}`;
}

export function initializeColorPicker(document, apply) {
    const byId = id => document.getElementById(id);
    const trigger = byId('cell-bg-color'), panel = byId('arqueo-color-picker');
    if (!trigger || !panel) return { sync() {} };
    const input = byId('cell-color-hex'), error = byId('cell-color-error');
    const hue = byId('cell-color-hue'), saturation = byId('cell-color-saturation'), brightness = byId('cell-color-brightness');
    const plane = panel.querySelector('[data-color-plane]'), cursor = panel.querySelector('[data-color-cursor]');
    const swatch = trigger.querySelector('[data-color-swatch]');
    let hex = '#E5E7EB', hsv = toHsv(hex), mixed = false;
    function clearError() { error.hidden = true; input.removeAttribute('aria-invalid'); }
    function render() {
        input.value = hex;
        input.placeholder = '';
        hue.value = String(hsv.h); saturation.value = String(hsv.s * 100); brightness.value = String(hsv.v * 100);
        plane.style.backgroundColor = `hsl(${hsv.h} 100% 50%)`;
        cursor.style.left = `${hsv.s * 100}%`; cursor.style.top = `${(1 - hsv.v) * 100}%`;
        swatch.style.backgroundColor = hex;
        trigger.setAttribute('aria-label', `Elegir color de fondo. Actual: ${hex}`);
        clearError();
    }
    function choose() {
        hex = fromHsv(hsv); mixed = false; render(); apply(hex);
    }
    function applyCode() {
        const code = normalizeHex(input.value);
        if (!code) { error.hidden = false; input.setAttribute('aria-invalid', 'true'); input.focus(); return; }
        hex = code; hsv = toHsv(hex); mixed = false; render(); apply(hex);
    }
    input.addEventListener('input', clearError);
    input.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); applyCode(); } });
    byId('cell-color-apply').addEventListener('click', applyCode);
    for (const control of [hue, saturation, brightness]) {
        control.addEventListener('input', () => { hsv = { h: Number(hue.value), s: Number(saturation.value) / 100, v: Number(brightness.value) / 100 }; choose(); });
    }
    let pointer = null;
    function point(event) {
        const rect = plane.getBoundingClientRect();
        hsv.s = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
        hsv.v = 1 - Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height));
        hex = fromHsv(hsv); render();
    }
    plane.addEventListener('pointerdown', event => { if (event.button !== 0) return; event.preventDefault(); pointer = event.pointerId; plane.setPointerCapture(pointer); point(event); });
    plane.addEventListener('pointermove', event => { if (pointer === event.pointerId) point(event); });
    plane.addEventListener('pointerup', event => { if (pointer !== event.pointerId) return; point(event); pointer = null; mixed = false; apply(hex); });
    plane.addEventListener('pointercancel', () => { pointer = null; });
    function position() {
        const rect = trigger.getBoundingClientRect(), viewport = document.documentElement;
        panel.style.left = `${Math.max(8, Math.min(rect.left, viewport.clientWidth - panel.offsetWidth - 8))}px`;
        panel.style.top = `${Math.max(8, Math.min(rect.bottom + 6, viewport.clientHeight - panel.offsetHeight - 8))}px`;
    }
    trigger.addEventListener('click', () => {
        if (panel.matches(':popover-open')) { panel.hidePopover(); return; }
        clearError(); panel.showPopover(); position(); input.focus({ preventScroll: true }); input.select();
    });
    panel.addEventListener('toggle', () => trigger.setAttribute('aria-expanded', String(panel.matches(':popover-open'))));
    document.defaultView.addEventListener('resize', () => { if (panel.matches(':popover-open')) position(); });
    document.addEventListener('scroll', () => { if (panel.matches(':popover-open')) position(); }, true);
    render();
    return {
        sync(color) {
            const value = colorHex(color);
            mixed = color === 'mixed';
            if (value) { hex = value; hsv = toHsv(hex); }
            render();
            if (!value) {
                input.value = ''; input.placeholder = mixed ? 'Varios' : 'Sin fondo';
                swatch.style.backgroundColor = '';
                trigger.setAttribute('aria-label', mixed ? 'Elegir color de fondo. Varios colores seleccionados' : 'Elegir color de fondo. Sin fondo');
            }
        },
        close() { if (panel.matches(':popover-open')) panel.hidePopover(); },
    };
}
