import { expect, test } from '@playwright/test';
import { openTerrain } from './helpers.js';

test('invite d’installation PWA et version du cache hors ligne', async ({ page }) => {
    await openTerrain(page);

    await page.evaluate(() => {
        const event = new Event('beforeinstallprompt');

        event.prompt = async () => {};
        event.userChoice = Promise.resolve({ outcome: 'accepted' });
        window.dispatchEvent(event);
    });

    await expect(page.locator('#install-banner')).toBeVisible();
    await expect(page.locator('#install-button')).toBeVisible();

    await page.locator('#install-dismiss').click();
    await expect(page.locator('#install-banner')).toBeHidden();
    await expect(page.locator('#install-button')).toBeVisible();

    await expect(page.locator('#app-version')).toContainText('c2me-v7', { timeout: 20_000 });
});
