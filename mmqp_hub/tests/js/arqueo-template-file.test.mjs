import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { initializeArqueo, sanitizeTemplate, templateGeometry, templateSnapshot } from '../../resources/js/arqueo-editor.js';
import { createTemplateFile, readTemplateFile, MAX_TEMPLATE_FILE_BYTES, MAX_TEMPLATE_HTML_LENGTH } from '../../resources/js/arqueo-template-file.js';

let JSDOM;
try { ({ JSDOM } = createRequire(import.meta.url)(process.env.MMQP_JSDOM_MODULE || 'jsdom')); }
catch { try { ({ JSDOM } = createRequire(join(process.env.MM_CONSUMOS_DOM_RUNTIME || join(tmpdir(), 'consumos-editor-diagnostic'), 'package.json'))('jsdom')); } catch {} }
const domTest = (name, callback) => test(name, { skip: !JSDOM && 'A jsdom runtime is required for template-file integration.' }, callback);
const envelope = html => JSON.stringify({ format: 'mmqp.arqueo-template', version: 1, template: { html } });
const simple = '<tr><td><div class="cell-content">Caja</div></td></tr>';
const boot = () => new JSDOM('<!doctype html><html><body><table id="arqueo-grid"><tbody></tbody></table></body></html>', { pretendToBeVisual: true });

domTest('a blank real editor exports a versioned compact file and round-trips exactly', () => {
    const dom = boot();
    try {
        const api = initializeArqueo({}, dom.window.document);
        const html = api.getHtml();
        const file = createTemplateFile(html, dom.window.document);
        const data = JSON.parse(file);
        assert.deepEqual(Object.keys(data), ['format', 'version', 'template']);
        assert.equal(data.format, 'mmqp.arqueo-template');
        assert.equal(data.version, 1);
        assert.equal(data.template.html, html);
        assert.equal(readTemplateFile(file, dom.window.document), html);
        assert.equal(createTemplateFile(readTemplateFile(file, dom.window.document), dom.window.document), file);
        assert.equal(api.dirty, false);
    } finally { dom.window.close(); }
});

domTest('Unicode, merged cells, borders, font formatting, backgrounds and line breaks survive a native file', () => {
    const dom = boot();
    try {
        const html = '<tr><td rowspan="2" class="b-top b-left" style="background-color: rgb(240, 245, 250);"><div class="cell-content" style="font-size: 18px; font-weight: bold; text-align: center;">Arqueo España € 🧾<br><i>Observación</i></div></td><td colspan="2" class="solid-border"><div class="cell-content"><u>Tarjeta</u> &amp; café</div></td></tr><tr><td><div class="cell-content">Mañana</div></td><td class="b-bottom b-right"><div class="cell-content"><span style="color: red; font-size: 12px;">Noche</span></div></td></tr>';
        const canonical = templateSnapshot(sanitizeTemplate(html, dom.window.document));
        const file = createTemplateFile(canonical, dom.window.document);
        assert.equal(readTemplateFile(file, dom.window.document), canonical);
        assert.ok(file.includes('España € 🧾'));
        const body = sanitizeTemplate(readTemplateFile(file, dom.window.document), dom.window.document);
        assert.equal(body.rows[0].cells[0].rowSpan, 2);
        assert.equal(body.rows[0].cells[1].colSpan, 2);
        assert.equal(body.querySelectorAll('br').length, 1);
        assert.equal(body.rows[0].cells[0].style.backgroundColor, 'rgb(240, 245, 250)');
        assert.equal(body.querySelector('.cell-content').style.fontSize, '18px');
        assert.equal(body.querySelector('.cell-content').style.fontWeight, 'bold');
        assert.equal(body.querySelector('.cell-content').style.textAlign, 'center');
    } finally { dom.window.close(); }
});

domTest('date and employee configuration survives while generated runtime values and editing markers are removed', () => {
    const dom = boot();
    try {
        const html = '<tr role="row"><td data-bound-date="true" class="b-top multi-selected cell-overflow" role="gridcell" aria-selected="true" style="--cell-rows: 1;"><div class="cell-content" contenteditable="false" aria-readonly="true" title="20/10/2026"><span data-bound-value="true">20/10/2026</span></div></td><td data-bound-emp="7" data-bound-prop="nombre"><div class="cell-content"><span data-bound-value="true" style="font-size: 10px; display: block;">ANA PRIVADA</span></div></td><td data-bound-emp="7" data-bound-prop="manana"><div class="cell-content">08:00-14:00</div></td><td data-bound-emp="7" data-bound-prop="noche"><div class="cell-content">18:00-22:00</div></td></tr>';
        const file = createTemplateFile(html, dom.window.document);
        const restored = readTemplateFile(file, dom.window.document);
        assert.ok(!/20\/10\/2026|ANA PRIVADA|08:00-14:00|18:00-22:00|multi-selected|cell-overflow|contenteditable|data-bound-value|--cell-rows/.test(restored));
        const body = sanitizeTemplate(restored, dom.window.document);
        assert.equal(body.rows[0].cells[0].dataset.boundDate, 'true');
        assert.deepEqual([...body.rows[0].cells].slice(1).map(cell => [cell.dataset.boundEmp, cell.dataset.boundProp]), [['7', 'nombre'], ['7', 'manana'], ['7', 'noche']]);
        assert.ok([...body.querySelectorAll('.cell-content')].every(node => node.textContent === ''));
    } finally { dom.window.close(); }
});

domTest('legacy custom geometry remains part of the native HTML document when enabled', () => {
    const dom = boot();
    try {
        const options = { enableImportGeometry: true };
        const html = '<tr data-arqueo-columns="[40,60]" data-arqueo-rows="[100,176]"><td rowspan="2"><div class="cell-content" style="padding-left: 12px; line-height: 1.2;">Título</div></td><td><div class="cell-content"></div></td></tr><tr><td><div class="cell-content"></div></td></tr>';
        const file = createTemplateFile(html, dom.window.document, options);
        const restored = readTemplateFile(file, dom.window.document, options);
        const body = sanitizeTemplate(restored, dom.window.document, options);
        assert.deepEqual(templateGeometry(body, options), { columns: [40, 60], rows: [100, 176] });
        assert.equal(body.querySelector('.cell-content').style.paddingLeft, '12px');
        assert.throws(() => readTemplateFile(file, dom.window.document), /medidas/);
    } finally { dom.window.close(); }
});

domTest('wrong envelopes, unsupported versions and missing designs are rejected', () => {
    const dom = boot();
    try {
        for (const text of ['', '{', 'null', '[]', '42', '{}', JSON.stringify({ format: 'other', version: 1, template: { html: simple } }), JSON.stringify({ format: 'mmqp.arqueo-template', version: 2, template: { html: simple } }), JSON.stringify({ format: 'mmqp.arqueo-template', version: '1', template: { html: simple } }), JSON.stringify({ format: 'mmqp.arqueo-template', version: 1, template: [] }), envelope(null), envelope(3), envelope('')]) assert.throws(() => readTemplateFile(text, dom.window.document));
        assert.throws(() => readTemplateFile({}, dom.window.document), /leer/);
        assert.equal(readTemplateFile(`\uFEFF${envelope(simple)}`, dom.window.document), simple);
    } finally { dom.window.close(); }
});

domTest('HTML character and UTF-8 file limits reject oversized input before parsing', () => {
    const dom = boot();
    try {
        assert.throws(() => createTemplateFile('a'.repeat(MAX_TEMPLATE_HTML_LENGTH + 1), dom.window.document), /caracteres/);
        assert.throws(() => readTemplateFile(' '.repeat(MAX_TEMPLATE_FILE_BYTES + 1), dom.window.document), /2 MB/);
        assert.throws(() => readTemplateFile('€'.repeat(Math.floor(MAX_TEMPLATE_FILE_BYTES / 3) + 1), dom.window.document), /2 MB/);
        const unicodeHeavy = `<tr><td><div class="cell-content">${'€'.repeat(800_000)}</div></td></tr>`;
        assert.throws(() => createTemplateFile(unicodeHeavy, dom.window.document), /2 MB/);
    } finally { dom.window.close(); }
});

domTest('incomplete markup, overlapping spans, holes, invalid bindings and stray cell content are rejected atomically', () => {
    const dom = boot();
    try {
        const api = initializeArqueo({}, dom.window.document);
        const before = api.getHtml();
        const malformed = [
            '<table><tr><td>Caja</td></tr></table>', '<tr></tr>', '<tr><td>Caja', '<tr><td><b>Caja</td></b></tr>',
            '<tr><td>Caja</td></tr>Texto perdido', '<tr><td rowspan="0">Caja</td></tr>', '<tr><td colspan="101">Caja</td></tr>', '<tr><td rowspan="2">Caja</td></tr>',
            '<tr><td></td><td rowspan="2"></td><td></td></tr><tr><td colspan="2"></td></tr>', '<tr><td></td><td></td></tr><tr><td></td></tr>',
            '<tr><td><div class="cell-content">Caja</div>Texto perdido</td></tr>', '<tr><td><div class="cell-content">Caja</div><div class="cell-content">Otro</div></td></tr>',
            '<tr><td data-bound-emp="x" data-bound-prop="nombre">Caja</td></tr>', '<tr><td data-bound-emp="7">Caja</td></tr>', '<tr><td data-bound-emp="7" data-bound-prop="nombre" data-bound-date="true">Caja</td></tr>',
            `<tr><td>${'<span>'.repeat(70)}Caja${'</span>'.repeat(70)}</td></tr>`,
        ];
        for (const html of malformed) assert.throws(() => readTemplateFile(envelope(html), dom.window.document), html);
        assert.equal(api.getHtml(), before);
        assert.equal(api.dirty, false);
    } finally { dom.window.close(); }
});

domTest('scripts, handlers, URL styles and active HTML are rejected instead of silently imported', () => {
    const dom = boot();
    try {
        for (const html of [
            '<tr><td><script>alert(1)</script></td></tr>', '<tr><td onclick="alert(1)">Caja</td></tr>', '<tr><td><div onmouseover="alert(1)">Caja</div></td></tr>',
            '<tr><td><img src=x onerror=alert(1)></td></tr>', '<tr><td><iframe srcdoc="x"></iframe></td></tr>', '<tr><td><a href="javascript:alert(1)">Caja</a></td></tr>',
            '<tr><td style="background-image:url(https://example.com/x)">Caja</td></tr>', '<tr><td style="color:expression(alert(1))">Caja</td></tr>', '<tr><td style="color:r/**/ed">Caja</td></tr>',
            '<tr><td><svg onload="alert(1)"></svg></td></tr>', '<tr><td><!-- hidden -->Caja</td></tr>',
        ]) assert.throws(() => readTemplateFile(envelope(html), dom.window.document), html);
        const literal = '<tr><td><div class="cell-content">&lt;script&gt;Texto&lt;/script&gt;</div></td></tr>';
        assert.equal(readTemplateFile(envelope(literal), dom.window.document), literal);
    } finally { dom.window.close(); }
});
