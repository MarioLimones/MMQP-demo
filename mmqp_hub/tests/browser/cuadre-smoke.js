// Run with playwright-cli run-code --filename=... against an authenticated, isolated test database.
async (page) => {
    const base = new URL(page.url()).origin;
    const assert = (condition, message) => { if (!condition) throw new Error(message); };
    await page.goto(`${base}/admin/cuadre?fecha=2026-10-10&turno=cierre`);
    const persisted = await page.locator('#saved-total').innerText();
    const state = await page.context().storageState();
    const noJs = await page.context().browser().newContext({ storageState: state, javaScriptEnabled: false, viewport: { width: 390, height: 844 }, serviceWorkers: 'block' });
    const plain = await noJs.newPage();
    try {
        await plain.goto(`${base}/admin/cuadre?fecha=2026-10-10&turno=cierre`);
        assert((await plain.locator('#saved-total').innerText()) === persisted, 'No-JS must display the persisted server result');
        await plain.locator('#nota_blanca').fill('0.37');
        await plain.locator('#motivo').fill('Guardado sin JavaScript');
        await plain.locator('#save-cuadre').click();
        await plain.waitForURL(`${base}/admin/cuadre?fecha=2026-10-10&turno=cierre`);
        assert((await plain.locator('#saved-total').innerText()) === '400.37 €', 'No-JS submission must recalculate in the server');
        await plain.getByRole('link', { name: 'Consultar historial de modificaciones' }).click();
        assert((await plain.locator('main').innerText()).includes('Guardado sin JavaScript'), 'History must work without JavaScript');
    } finally {
        await noJs.close();
    }
    // The original editor is now stale. Conflict must keep the draft and offer explicit resolution.
    await page.locator('#nota_blanca').fill('0.39');
    const conflictResponse = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/admin/cuadre'));
    await page.locator('#save-cuadre').click();
    assert((await conflictResponse).status() === 409, 'Stale HTML editor must receive 409');
    assert((await page.locator('#nota_blanca').inputValue()) === '0.39', 'Conflict must preserve draft');
    assert(await page.locator('#save-cuadre').isDisabled(), 'Conflict must require explicit reload');
    await page.getByRole('link', { name: 'Cargar la versión vigente y descartar este borrador' }).click();
    // Bypass browser validation deliberately to check real server feedback.
    await page.locator('#billetes-100').fill('1.5');
    await page.locator('#cuadre-form').evaluate(form => { form.noValidate = true; });
    await page.locator('#save-cuadre').click();
    await page.locator('#error-summary').waitFor();
    assert(await page.locator('#error-summary').evaluate(element => document.activeElement === element), 'Error summary must receive keyboard focus');
    assert((await page.locator('#billetes-100').inputValue()) === '1.5', 'Validation must preserve invalid input');
    assert((await page.locator('#error-billetes').innerText()).includes('entero'), 'Server must show useful inline unit error');
    assert((await page.locator('#saved-total').innerText()) === '400.37 €', 'Failed validation must not change the persisted result');
    assert((await page.locator('#descuadre-text').innerText()) === 'Pendiente', 'Invalid preview must not turn bad input into zero');
    await page.goto(`${base}/admin/cuadre?fecha=2026-10-10&turno=cierre`);
    await page.setViewportSize({ width: 390, height: 844 });
    assert(!(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)), 'Mobile form must not overflow horizontally');
    assert((await page.locator('#cuadre-form').evaluate(form => Array.from(form.querySelectorAll('input:not([type=hidden]),textarea')).filter(field => !field.labels.length).length)) === 0, 'Every editable field must be labeled');
    console.log('PASS: no-JS save/history, conflict draft, server errors/focus, exact persisted result, mobile width, input labels');
}
