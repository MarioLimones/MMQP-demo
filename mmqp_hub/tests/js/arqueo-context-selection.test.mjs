import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { initializeArqueo } from '../../resources/js/arqueo-editor.js';

const require = createRequire(import.meta.url);
let JSDOM;
try { ({ JSDOM } = require(process.env.MMQP_JSDOM_MODULE || 'jsdom')); } catch {}

test('right-button pointerdown preserves a selected range before native focus and contextmenu, with source links untouched',
    { skip: !JSDOM && 'Install jsdom or set MMQP_JSDOM_MODULE to run the DOM event regression.' }, () => {
        const dom = new JSDOM(`<!doctype html><html><body><table id="arqueo-grid"><tbody></tbody></table>
            <div id="context-menu" hidden><button role="menuitem" id="range-action">Apply to selected range</button></div>
            </body></html>`, { pretendToBeVisual: true });
        const { document, MouseEvent } = dom.window;
        try {
            const api = initializeArqueo({ cellAttributes: ['data-consumo-sources', 'data-consumo-product'],
                isBound: cell => cell.hasAttribute('data-consumo-sources'), onSave: () => {} }, document);
            dom.window.toggleEditMode();
            const at = (row, column) => api.tbody.rows[row].cells[column];
            const content = cell => cell.querySelector('.cell-content');
            const linked = at(1, 1);
            linked.dataset.consumoProduct = 'existing-product';
            linked.dataset.consumoSources = '["zero-source","duplicate-name-source"]';
            content(linked).textContent = '0 KG';
            api.refreshBindings();
            const observed = [];
            for (const type of ['pointerdown', 'focusin', 'contextmenu']) document.addEventListener(type, event => {
                observed.push({ type, button: event.button, prevented: event.defaultPrevented, cells: api.getSelection().cells.size,
                    target: event.target.closest('td')?.getAttribute('aria-label') || event.target.id });
            });
            const pointer = (cell, button, shiftKey = false) => {
                const event = new MouseEvent('pointerdown', { bubbles: true, cancelable: true, button, shiftKey });
                const permitted = content(cell).dispatchEvent(event);
                // JSDOM has no browser default actions. Model native pointer focus only when it was not canceled.
                if (permitted) content(cell).focus();
                return event;
            };
            pointer(at(0, 0), 0);
            pointer(at(2, 2), 0, true);
            assert.equal(api.getSelection().cells.size, 9);
            assert.equal(api.getSelection().active, at(2, 2));
            const before = api.snapshot();
            observed.length = 0;
            assert.equal(pointer(at(0, 0), 2).defaultPrevented, true);
            content(at(0, 0)).dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, button: 2, clientX: 10, clientY: 10 }));
            assert.equal(api.getSelection().cells.size, 9, 'right-button focus must not collapse the selected rectangle');
            assert.equal(api.getSelection().active, at(2, 2));
            assert.equal(document.getElementById('context-menu').hidden, false);
            assert.equal(observed.find(event => event.type === 'pointerdown').prevented, true);
            assert.equal(observed.some(event => event.type === 'focusin' && event.target === 'Celda A1'), false);
            assert.equal(observed.find(event => event.type === 'contextmenu').cells, 9);
            assert.equal(observed.some(event => event.type === 'focusin' && event.target === 'range-action'), true);
            assert.equal(linked.dataset.consumoSources, '["zero-source","duplicate-name-source"]');
            assert.equal(content(linked).textContent, '0 KG');
            assert.equal(api.snapshot(), before);
            let actionSelection;
            document.getElementById('range-action').addEventListener('click', () => { actionSelection = [...api.getSelection().cells]; });
            document.getElementById('range-action').click();
            assert.deepEqual(actionSelection.map(cell => cell.getAttribute('aria-label')), [
                'Celda A1', 'Celda B1', 'Celda C1', 'Celda A2', 'Celda B2', 'Celda C2', 'Celda A3', 'Celda B3', 'Celda C3',
            ]);
            // Existing left-button and outside-range right-button interactions retain their behavior.
            pointer(at(0, 0), 0);
            assert.equal(api.getSelection().cells.size, 1);
            assert.equal(api.getSelection().active, at(0, 0));
            assert.equal(pointer(at(0, 3), 2).defaultPrevented, false);
            content(at(0, 3)).dispatchEvent(new MouseEvent('contextmenu', { bubbles: true, cancelable: true, button: 2 }));
            assert.equal(api.getSelection().cells.size, 1);
            assert.equal(api.getSelection().active, at(0, 3));
            assert.equal(linked.dataset.consumoSources, '["zero-source","duplicate-name-source"]');
            // Keyboard/programmatic focus and ordinary non-design focus still select a new cell.
            content(at(3, 0)).focus();
            assert.equal(api.getSelection().active, at(3, 0));
            dom.window.toggleEditMode();
            assert.equal(pointer(at(0, 0), 2).defaultPrevented, false);
            assert.equal(api.getSelection().active, at(0, 0));
        } finally { dom.window.close(); }
    });
