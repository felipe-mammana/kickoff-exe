const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');
const baseUrl = process.argv[2];
if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(baseUrl)) throw new Error('Local fixture required');
(async () => {
    const browser = await chromium.launch({ headless: true, ...(process.env.SECURITY_BROWSER_CHANNEL ? { channel: process.env.SECURITY_BROWSER_CHANNEL } : {}) });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(baseUrl + '/?route=login');
        await page.locator('[name="email"]').fill('permissions-ui@example.test');
        await page.locator('[name="password"]').fill('Permissions-UI-123');
        await Promise.all([page.waitForURL(baseUrl + '/'), page.getByRole('button', { name: 'Entrar', exact: true }).click()]);
        for (const width of [1366, 390, 320]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto(baseUrl + '/?route=users.index');
            await page.locator('[data-user-modal-open="create"]').first().click();
            const modal = page.locator('[data-user-modal="create"]');
            const panel = modal.locator('[data-user-permissions]');
            assert.ok(await panel.locator('input[type="checkbox"]').count() >= 30);
            await modal.locator('[name="role"][value="editor"]').check();
            assert.equal(await panel.locator('[value="machines.edit"]').isChecked(), true);
            assert.equal(await panel.locator('[value="machines.delete"]').isChecked(), false);
            await panel.locator('[value="vault.copy"]').check();
            assert.equal(await panel.locator('[value="vault.view"]').isChecked(), true);
            assert.equal(await panel.locator('[value="vault.reveal"]').isChecked(), true);
            await panel.locator('[value="vault.view"]').uncheck();
            assert.equal(await panel.locator('[value="vault.copy"]').isChecked(), false);
            const dimensions = await modal.locator('.user-modal-dialog').evaluate(el => ({ width: el.getBoundingClientRect().width, left: el.getBoundingClientRect().left, scroll: el.scrollWidth, client: el.clientWidth }));
            assert.ok(dimensions.left >= 0 && dimensions.width <= width && dimensions.scroll <= dimensions.client + 1, JSON.stringify(dimensions));
            await panel.scrollIntoViewIfNeeded();
            await page.screenshot({ path: path.join(__dirname, '../storage/tmp/permissions-' + width + '.png') });
            await modal.locator('[data-user-modal-close]').first().click();
            await page.locator('[data-user-modal-open="edit"][data-user-name="Permissions Test"]').click();
            assert.equal(await page.locator('[data-user-modal="edit"] [value="users.permissions"]').isChecked(), true);
            assert.equal(await page.locator('[data-user-modal="edit"] [value="vault.view"]').isChecked(), false);
        }
        assert.deepEqual(errors, []);
        console.log('Checkbox presets, dependencies, saved selections and responsive layout OK.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
