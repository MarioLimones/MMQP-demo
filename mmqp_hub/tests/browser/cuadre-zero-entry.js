// Run with playwright-cli run-code --filename=tests/browser/cuadre-zero-entry.js
// against an authenticated, isolated test database with responsible DNI 01234567.
async (page) => {
    const base = new URL(page.url()).origin;
    const assert = (condition, message) => { if (!condition) throw new Error(message); };
    const byId = id => page.locator(`[id="${id}"]`);
    await page.goto(`${base}/admin/cuadre?fecha=2026-10-21&turno=cierre`);
    const rows = await page.locator('.billete-total, .moneda-total').evaluateAll(inputs => inputs.map(input => ({
        total: input.id, income: input.closest('tr').querySelector('[class*="-ingreso"]').id,
    })));
    assert(rows.length === 13, 'Must cover every denomination');
    for (const row of rows) {
        const total = byId(row.total), income = byId(row.income);
        for (const input of [total, income]) {
            await input.click();
            assert((await input.inputValue()) === '', `${row.total}: zero must disappear on entry`);
            assert((await total.locator('xpath=ancestor::tr/td[last()]').innerText()) === (input === total ? '0' : '20'), `${row.total}: empty units must count as zero in remaining units`);
            assert((await byId('total-caja').innerText()) !== 'Pendiente', `${row.total}: empty units must keep a numeric preview`);
            await page.keyboard.press('Tab');
            assert((await input.inputValue()) === '0', `${row.total}: leaving without typing must restore zero`);
            await input.click();
            assert((await input.inputValue()) === '', `${row.total}: zero must clear again when clicked`);
            await page.keyboard.type('20');
            assert((await input.inputValue()) === '20', `${row.total}: typing must enter the new units directly`);
        }
        assert(await income.evaluate(input => input.checkValidity()), `${row.total}: equal total and deposit must stay valid`);
        for (const input of [income, total]) {
            await input.click();
            await page.keyboard.press('ControlOrMeta+A');
            await page.keyboard.press('Backspace');
            assert((await byId('total-caja').innerText()) !== 'Pendiente', `${row.total}: deleting units must count as zero while editing`);
            await page.keyboard.press('Tab');
            assert((await input.inputValue()) === '0', `${row.total}: leaving an empty field must restore zero`);
            assert(await input.evaluate(input => input.checkValidity()), `${row.total}: restored zero must clear validation errors`);
        }
    }
    const total = byId('monedas-2'), income = byId('monedas_ingreso-2');
    await total.click();
    await page.keyboard.type('20');
    await page.keyboard.press('Tab');
    assert((await income.inputValue()) === '', 'Tab focus must also clear zero');
    await page.keyboard.type('20');
    assert((await income.inputValue()) === '20', 'Typing after Tab must enter the new units directly');
    await total.click();
    await page.keyboard.press('End');
    await page.keyboard.type('1');
    assert((await total.inputValue()) === '201', 'Nonzero values must remain editable without automatic selection');
    await total.fill('20');
    await income.focus();
    await page.keyboard.press('ArrowUp');
    assert((await income.inputValue()) === '20', 'Deposit keyboard spinner must still respect total');
    await income.fill('0');
    await total.fill('0');
    for (const text of ['-', '1e']) {
        await total.click();
        await page.keyboard.type(text);
        assert(await total.evaluate(input => input.validity.badInput), `${text}: incomplete numeric input must remain invalid`);
        await page.keyboard.press('Tab');
        assert((await total.inputValue()) === '', `${text}: invalid input must not silently turn into zero`);
        assert(await total.evaluate(input => input.validity.badInput), `${text}: blur must preserve invalidity`);
        await total.fill('0');
    }
    await byId('dni_empleado').fill('01234567');
    await total.fill('2');
    await income.fill('1');
    await total.fill('');
    await byId('save-cuadre').click();
    assert((await total.inputValue()) === '0', 'An empty total must become zero before saving');
    assert((await income.inputValue()) === '1', 'Restoring total must not alter the deposit');
    assert(!(await income.evaluate(input => input.checkValidity())), 'Restoring total to zero must keep an excessive deposit blocked');
    await income.fill('0');
    // Saving with the mouse must restore an empty unit before native validation.
    await income.fill('');
    await byId('save-cuadre').click();
    await byId('saved-total').waitFor();
    assert((await income.inputValue()) === '0', 'Clicking Save must restore an empty unit');
    assert((await byId('saved-total').innerText()) === '0.00 €', 'Mouse save must persist the correct zero total');
    // Enter submits without a blur, so normalize before native validation runs.
    await total.fill('');
    const saved = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/admin/cuadre'));
    await total.press('Enter');
    assert((await saved).status() === 302, 'Enter must save instead of stopping on an empty required unit');
    await page.waitForLoadState('domcontentloaded');
    assert((await total.inputValue()) === '0', 'Enter must restore an empty unit to zero');
    assert((await byId('saved-total').innerText()) === '0.00 €', 'Enter save must retain the correct total');
    for (const shift of ['apertura', 'intermedio']) {
        await page.goto(`${base}/admin/cuadre?fecha=2026-10-21&turno=${shift}`);
        await total.click();
        assert((await total.inputValue()) === '', `${shift}: zero must disappear on entry`);
        assert((await byId('total-caja').innerText()) === '0.00 €', `${shift}: entering a zero must keep the preview stable`);
        await page.keyboard.type('20');
        assert((await total.inputValue()) === '20', `${shift}: zero must be replaceable`);
        await total.fill('');
        await page.keyboard.press('Tab');
        assert((await total.inputValue()) === '0', `${shift}: empty units must return to zero`);
    }
    return 'PASS: all 26 unit inputs, zero clears on click and Tab, untouched fields return to zero, nonzero editing, income limit, invalid-number preservation, mouse and Enter save, all three shifts';
}
