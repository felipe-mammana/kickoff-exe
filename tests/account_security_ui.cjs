const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const baseUrl = process.argv[2];
const mailFile = process.argv[3];
const password = 'Security-UI-Password-123';
if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(baseUrl)) throw new Error('Only local test servers are allowed');

(async () => {
    const browser = await chromium.launch({ headless: true, ...(process.env.SECURITY_BROWSER_CHANNEL ? { channel: process.env.SECURITY_BROWSER_CHANNEL } : {}) });
    try {
        const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(baseUrl + '/?route=login');
        await page.locator('[name="email"]').fill('security-ui@example.test');
        await page.locator('[name="password"]').fill(password);
        await Promise.all([page.waitForURL(baseUrl + '/'), page.getByRole('button', { name: 'Entrar', exact: true }).click()]);
        await page.goto(baseUrl + '/?route=settings.security');
        const protection = page.locator('[name="vault_require_password_reveal"]');
        await protection.check();
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Salvar segurança' }).click()]);

        for (const [label, width, height] of [['desktop', 1366, 900], ['mobile', 390, 844], ['small-mobile', 320, 640]]) {
            await page.setViewportSize({ width, height });
            await protection.uncheck();
            await page.getByRole('button', { name: 'Salvar segurança' }).click();
            const dialog = page.locator('[data-account-password-modal]:visible');
            await dialog.waitFor();
            assert.equal(await dialog.locator('input').evaluate(el => el === document.activeElement), true);
            const metrics = await dialog.locator('[role="dialog"]').evaluate(el => {
                const rect = el.getBoundingClientRect();
                return { left: rect.left, right: rect.right, top: rect.top, bottom: rect.bottom, viewport: innerWidth, scroll: el.scrollWidth, width: el.clientWidth, page: document.documentElement.scrollWidth };
            });
            assert.ok(metrics.left >= 0 && metrics.right <= width + 1, JSON.stringify(metrics));
            assert.ok(metrics.top >= 0 && metrics.bottom <= height + 1, JSON.stringify(metrics));
            assert.ok(metrics.scroll <= metrics.width + 1 && metrics.page <= width + 1, JSON.stringify(metrics));
            assert.equal(await page.evaluate(() => document.elementFromPoint(2, innerHeight - 2).matches('[data-account-password-modal]')), true);
            await page.screenshot({ path: path.join('storage', 'tmp', 'security-ui-' + label + '.png') });
            await page.keyboard.press('Escape');
            assert.equal(await dialog.count(), 0);
            assert.equal(await page.locator('[data-account-password-input]').inputValue(), '');
            console.log('[OK] Modal de senha sem overflow: ' + label);
        }

        await page.getByRole('button', { name: 'Salvar segurança' }).click();
        await page.locator('[data-account-password-input]').fill('wrong');
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Confirmar', exact: true }).click()]);
        assert.equal(await protection.isChecked(), true);
        await protection.uncheck();
        await page.getByRole('button', { name: 'Salvar segurança' }).click();
        await page.locator('[data-account-password-input]').fill(password);
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Confirmar', exact: true }).click()]);
        assert.equal(await protection.isChecked(), false);

        await page.goto(baseUrl + '/?route=settings.account');
        await page.locator('[name="email"]').fill('security-ui-new@example.test');
        await page.getByRole('button', { name: 'Salvar perfil' }).click();
        await page.locator('[data-account-password-input]').fill(password);
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Confirmar', exact: true }).click()]);
        await page.getByRole('heading', { name: 'Confirmar novo e-mail', exact: true }).waitFor();
        assert.equal(await page.locator('[name="email"]').inputValue(), 'security-ui@example.test');
        assert.equal(await page.locator('[data-email-cooldown]').isDisabled(), true);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
        await page.evaluate(() => Promise.all(document.getAnimations().filter(animation => animation.effect.getComputedTiming().iterations !== Infinity).map(animation => animation.finished.catch(() => {}))));
        await page.screenshot({ path: path.join('storage', 'tmp', 'security-ui-email.png'), fullPage: true });
        const lines = fs.readFileSync(mailFile, 'utf8').trim().split('\n');
        const message = JSON.parse(lines[lines.length - 1]);
        const code = message.body.match(/\b[1-9][0-9]{5}\b/)[0];
        await page.locator('[name="email_change_code"]').fill(code);
        await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Confirmar e-mail', exact: true }).click()]);
        assert.equal(await page.locator('[name="email"]').inputValue(), 'security-ui-new@example.test');
        assert.equal(await page.locator('.account-email-verification').count(), 0);
        assert.deepEqual(errors, []);
        console.log('[OK] Fluxos visuais: senha incorreta, confirmacao correta, troca de email e reenvio bloqueado');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
