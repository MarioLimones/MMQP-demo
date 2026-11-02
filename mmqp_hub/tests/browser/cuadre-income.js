// Run with playwright-cli run-code --filename=tests/browser/cuadre-income.js
// against an authenticated, isolated test database with responsible DNI 01234567.
async (page) => {
    const base = new URL(page.url()).origin;
    const assert = (condition, message) => { if (!condition) throw new Error(message); };
    const totalInputs = () => page.locator('.billete-total, .moneda-total');
    const byId = id => page.locator(`[id="${id}"]`);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`${base}/admin/cuadre?fecha=2026-10-10&turno=cierre`);
    await byId('dni_empleado').fill('01234567');
    let posts = 0;
    page.on('request', request => { if (request.method() === 'POST' && request.url().endsWith('/admin/cuadre')) posts++; });
    const rows = await totalInputs().evaluateAll(inputs => inputs.map(input => ({
        id: input.id,
        incomeId: input.closest('tr').querySelector('[class*="-ingreso"]').id,
    })));
    assert(rows.length === 13, 'Must check every note and coin denomination');
    for (const row of rows) {
        const total = byId(row.id), income = byId(row.incomeId);
        const remainder = total.locator('xpath=ancestor::tr/td[last()]');
        const inline = byId(`error-${row.incomeId}`);
        assert((await income.getAttribute('max')) === '0', `${row.id}: empty cash must limit deposit to zero`);
        await total.fill('3');
        assert((await income.getAttribute('max')) === '3', `${row.id}: max must follow total`);
        await income.fill('2');
        assert((await remainder.innerText()) === '1', `${row.id}: correct remaining units`);
        await income.fill('4');
        assert(!(await income.evaluate(input => input.checkValidity())), `${row.id}: excessive deposit must be invalid`);
        assert((await income.getAttribute('aria-invalid')) === 'true', `${row.id}: accessible invalid state`);
        assert(await inline.isVisible(), `${row.id}: inline error must be visible`);
        assert((await remainder.innerText()) === 'Pendiente', `${row.id}: must clear stale remaining units`);
        const fieldset = total.locator('xpath=ancestor::fieldset');
        assert((await fieldset.locator('[id^="suma-"]').allTextContents()).every(value => value === 'Pendiente'), `${row.id}: must clear stale subtotals`);
        assert((await byId('descuadre-text').innerText()) === 'Pendiente', `${row.id}: invalid cash must have no calculated difference`);
        await byId('save-cuadre').click();
        assert(posts === 0, `${row.id}: invalid cash must not be submitted`);
        assert(!(await byId('save-cuadre').isDisabled()), `${row.id}: rejected submission must not lock save`);
        await total.fill('4');
        assert(await income.evaluate(input => input.checkValidity()), `${row.id}: increasing total must clear invalidity`);
        assert(!(await inline.isVisible()), `${row.id}: fixed error must disappear`);
        assert((await remainder.innerText()) === '0', `${row.id}: equality must be valid`);
        await total.fill('1');
        assert((await income.getAttribute('max')) === '1', `${row.id}: reducing total must update the limit`);
        assert(!(await income.evaluate(input => input.checkValidity())), `${row.id}: reducing total must revalidate deposit`);
        assert((await income.inputValue()) === '4', `${row.id}: validation must preserve entered cash`);
        await income.fill('1');
        assert((await remainder.innerText()) === '0', `${row.id}: correcting deposit must restore the result`);
        await income.focus();
        await page.keyboard.press('ArrowUp');
        assert((await income.inputValue()) === '1', `${row.id}: spinner must stop at counted units`);
        await income.fill('0');
        await total.fill('0');
    }
    // Every row must update even when an earlier row is invalid.
    await byId('billetes_ingreso-100').fill('1');
    await byId('monedas_ingreso-0.01').fill('1');
    assert(await byId('error-billetes_ingreso-100').isVisible(), 'First invalid denomination must have feedback');
    assert(await byId('error-monedas_ingreso-0.01').isVisible(), 'Later invalid denomination must also have feedback');
    await byId('billetes_ingreso-100').fill('0');
    await byId('monedas_ingreso-0.01').fill('0');
    await byId('monedas-0.1').fill('2');
    for (const value of ['-1', '1.5']) {
        await byId('monedas_ingreso-0.1').fill(value);
        assert(!(await byId('monedas_ingreso-0.1').evaluate(input => input.checkValidity())), `Invalid deposit ${JSON.stringify(value)} must be rejected`);
        assert((await byId('descuadre-text').innerText()) === 'Pendiente', 'Invalid units must not be converted to zero');
    }
    await byId('monedas_ingreso-0.1').fill('0');
    await byId('monedas-0.1').fill('0');
    await byId('billetes-100').fill('4');
    await byId('billetes_ingreso-100').fill('1');
    assert((await byId('total-caja').innerText()) === '400.00 €', 'Valid total must recover');
    assert((await byId('total-ingreso-text').innerText()) === '100.00 €', 'Valid deposit must recover');
    assert((await byId('descuadre-text').innerText()) === '0.00 €', 'Valid difference must recover');
    await page.setViewportSize({ width: 390, height: 844 });
    await byId('monedas_ingreso-0.01').fill('1');
    assert(!(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)), 'Inline error must fit mobile width');
    await page.screenshot({ path: 'output/playwright/cuadre/ingreso-validation-mobile.png', fullPage: true });
    await byId('monedas_ingreso-0.01').fill('0');
    await page.setViewportSize({ width: 1440, height: 1000 });
    const saved = page.waitForNavigation();
    await byId('save-cuadre').click();
    await saved;
    assert((await byId('saved-total').innerText()) === '400.00 €', 'Valid cash must save exactly');
    assert((await byId('saved-income').innerText()) === '100.00 €', 'Valid deposit must save exactly');
    assert(errors.length === 0, `No browser errors: ${errors.join('; ')}`);
    console.log('PASS: all 13 denominations, dynamic max, keyboard limit, inline errors, blocked submit, reduced totals, invalid units, recovery, mobile width and exact save');
}
