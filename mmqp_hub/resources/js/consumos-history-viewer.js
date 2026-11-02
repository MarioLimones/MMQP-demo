import { drawPrintBorders, clearPrintBorders } from './arqueo-print-borders.js';

const viewer = document.querySelector('[data-consumos-history-viewer]');

if (viewer) {
    const grids = [...viewer.querySelectorAll('.consumos-history-grid')];
    const borderOptions = index => ({ id: `consumos-history-borders-${index}`, className: 'consumos-history-borders' });

    viewer.querySelector('[data-consumos-history-print]')?.addEventListener('click', () => window.print());
    window.addEventListener('beforeprint', () => {
        grids.forEach((grid, index) => drawPrintBorders(grid, borderOptions(index)));
    });
    window.addEventListener('afterprint', () => {
        grids.forEach((grid, index) => clearPrintBorders(grid, borderOptions(index)));
    });
}
