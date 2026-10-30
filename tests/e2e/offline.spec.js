import { expect, test } from '@playwright/test';
import { getActivity, openTerrain, openAccount } from './helpers.js';

test('rechargement hors ligne puis synchronisation d’une activité', async ({ page, context }) => {
    await openTerrain(page);

    await page.evaluate(() => navigator.serviceWorker.ready);
    await page.reload();
    await expect(page.locator('#count')).toContainText('12 compte');

    await context.setOffline(true);
    await page.reload();

    await expect(page).toHaveTitle(/Mode terrain/);
    await expect(page.locator('#search')).toBeVisible();
    await expect(page.locator('#connection')).toHaveText(/Hors ligne/);

    const card = await openAccount(page, 'E2E Compte 01');

    await card.locator('[data-new-activity]').click();
    await card.locator('input[id^="new-activity-subject-"]').fill('Visite hors ligne');
    await card.locator('[data-save-new-activity]').click();

    const row = page.locator('.activity').filter({ hasText: 'Visite hors ligne' });

    await expect(row).toBeVisible();
    await expect(row).toContainText('En attente');
    await expect(page.locator('#pending')).toContainText('en attente');

    await context.setOffline(false);
    await expect(page.locator('#connection')).toHaveText(/En ligne/);
    await expect(page.locator('#sync-button')).toBeEnabled();

    const pendingBadge = page
        .locator('.activity')
        .filter({ hasText: 'Visite hors ligne' })
        .locator('.pill--pending');

    await expect(pendingBadge).toHaveCount(0, { timeout: 20_000 });

    const serverActivity = await getActivity(page, 'Visite hors ligne');

    expect(serverActivity).not.toBeNull();
    expect(serverActivity.crm_account_id).toBeTruthy();
});
