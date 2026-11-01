import { expect, test as setup } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { ADMIN } from './helpers.js';

const authFile = 'tests/e2e/.auth/admin.json';

setup('authentification du compte de test', async ({ page }) => {
    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(ADMIN.email);
    await page.locator('input[type="password"]').fill(ADMIN.password);
    await page.locator('button[type="submit"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    await expect(page.locator('body')).not.toContainText('Ces identifiants ne correspondent pas');

    mkdirSync('tests/e2e/.auth', { recursive: true });
    await page.context().storageState({ path: authFile });
});
