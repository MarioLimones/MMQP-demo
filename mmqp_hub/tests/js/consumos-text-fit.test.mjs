import test from 'node:test';
import assert from 'node:assert/strict';
import { chooseConsumoFont, fitConsumoText } from '../../resources/js/consumos-text-fit.js';

const metrics = (font, { width = 60, height = 24, characters = 12, lineHeight = 1.2 } = {}) => {
    const textWidth = characters * font * 0.6;
    return { clientWidth: width, clientHeight: height, scrollWidth: width, scrollHeight: Math.ceil(textWidth / width) * font * lineHeight };
};

test('wrapped text shrinks only as far as necessary to fit the fixed cell height', () => {
    const calls = [];
    const chosen = chooseConsumoFont(12, font => { calls.push(font); return metrics(font); });
    assert.deepEqual(chosen, { fontSize: 10, reduced: true, fits: true, hidden: false });
    assert.deepEqual(calls, [12, 11, 10]);
    assert.equal(chooseConsumoFont(10, font => metrics(font)).fontSize, 10);
});

test('both horizontal and vertical overflow are considered, including minimum-size unresolved text', () => {
    const horizontal = chooseConsumoFont(14, font => ({ clientWidth: 60, clientHeight: 20, scrollWidth: font * 6, scrollHeight: 20 }));
    assert.equal(horizontal.fontSize, 10);
    assert.equal(horizontal.fits, true);
    const unresolved = chooseConsumoFont(12, font => metrics(font, { width: 20, height: 12, characters: 80 }));
    assert.deepEqual(unresolved, { fontSize: 8, reduced: true, fits: false, hidden: false });
    const smaller = chooseConsumoFont(7, font => metrics(font, { width: 20, height: 12, characters: 80 }));
    assert.deepEqual(smaller, { fontSize: 7, reduced: false, fits: false, hidden: false });
});

test('empty dimensions are skipped and measurements remain bounded for unusually large fonts', () => {
    let calls = 0;
    const hidden = chooseConsumoFont(12, () => { calls++; return { clientWidth: 0, clientHeight: 0, scrollWidth: 100, scrollHeight: 100 }; });
    assert.equal(hidden.hidden, true);
    assert.equal(calls, 1);
    calls = 0;
    const result = chooseConsumoFont(512, font => { calls++; return metrics(font, { width: 20, height: 12, characters: 80 }); }, { minFont: 8, maxFont: 500 });
    assert.equal(result.fontSize, 8);
    assert(calls <= 33);
    assert.equal(result.fits, false);
    assert.throws(() => chooseConsumoFont(12, () => ({}), { minFont: 0 }), /dimensiones/);
});

function cell(text, { font = 12, width = 60, height = 24, characters = text.length } = {}) {
    const content = {
        textContent: text, style: { fontSize: `${font}px` }, clientWidth: width, clientHeight: height,
        get scrollWidth() { return metrics(Number.parseFloat(this.style.fontSize), { width, height, characters }).scrollWidth; },
        get scrollHeight() { return metrics(Number.parseFloat(this.style.fontSize), { width, height, characters }).scrollHeight; },
    };
    const item = {
        style: { fontSize: `${font}px` }, rowSpan: 2, colSpan: 4,
        attributes: { 'data-consumo-sources': '["pdf-row-zero"]', 'data-consumo-field': 'consumo' },
        querySelector: selector => selector === '.cell-content' ? content : null,
        ownerDocument: { defaultView: { getComputedStyle: () => ({ fontSize: content.style.fontSize }) } },
    };
    return { item, content };
}

test('DOM fitting persists the smaller font on cell and content while preserving text, units and bindings', () => {
    const { item, content } = cell('60,955 kg', { characters: 12 });
    const attributes = JSON.stringify(item.attributes);
    assert.deepEqual(fitConsumoText([item, item]), { fitted: 1, unresolved: 0 });
    assert.equal(item.style.fontSize, '10px');
    assert.equal(content.style.fontSize, '10px');
    assert.equal(content.textContent, '60,955 kg');
    assert.equal(JSON.stringify(item.attributes), attributes);
    assert.equal(item.rowSpan, 2); assert.equal(item.colSpan, 4);
    assert.deepEqual(fitConsumoText([item]), { fitted: 0, unresolved: 0 });
    assert.equal(content.style.fontSize, '10px');
});

test('fitting skips hidden and empty cells and reports text that remains too long without enlarging small fonts', () => {
    const empty = cell(''); const hidden = cell('Oculto', { width: 0, height: 0 });
    const tooLong = cell('Nombre de producto extremadamente largo', { width: 20, height: 12 });
    const alreadySmall = cell('Nombre largo', { font: 7, width: 20, height: 12 });
    assert.deepEqual(fitConsumoText([empty.item, hidden.item, tooLong.item, alreadySmall.item, null]), { fitted: 0, unresolved: 2 });
    assert.equal(empty.content.style.fontSize, '12px');
    assert.equal(hidden.content.style.fontSize, '12px');
    assert.equal(tooLong.content.style.fontSize, '8px');
    assert.equal(alreadySmall.content.style.fontSize, '7px');
});
