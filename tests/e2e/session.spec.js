import { expect, test } from '@playwright/test';
import { openTerrain, openAccount } from './helpers.js';

test('session expirée : file conservée et bandeau de reconnexion', async ({ page, context }) => {
    await openTerrain(page);

    const card = await openAccount(page, 'E2E Compte 03');

    await card.locator('textarea[data-activity-body]').first().fill('Modification après expiration');
    await context.clearCookies();
    await card.locator('[data-save-activity]').first().click();

    await expect(page.locator('#session-banner')).toBeVisible();
    await expect(page.locator('#pending')).toContainText('en attente');

    const queued = await page.evaluate(() => new Promise((resolve, reject) => {
        const request = indexedDB.open('c2me-field');

        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            const database = request.result;
            const transaction = database.transaction('ops', 'readonly');
            const all = transaction.objectStore('ops').getAll();

            all.onsuccess = () => resolve(all.result.length);
            all.onerror = () => reject(all.error);
        };
    }));

    expect(queued).toBe(1);
});
