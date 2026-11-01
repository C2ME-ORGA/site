import { expect, test } from '@playwright/test';
import { getActivity, openTerrain, openAccount, serverUpdateActivity } from './helpers.js';

test('conflit détecté et résolu avec la version serveur', async ({ page }) => {
    await openTerrain(page);

    const activity = await getActivity(page, 'Visite E2E 01');

    expect(activity).not.toBeNull();

    await serverUpdateActivity(page, activity, { body: 'Version serveur' });

    const card = await openAccount(page, 'E2E Compte 01');
    const editor = card.locator('textarea[data-activity-body]').first();

    await editor.fill('Version locale');
    await card.locator('[data-save-activity]').first().click();

    await expect(page.locator('#conflicts')).toBeVisible();
    await expect(page.locator('#tab-accounts-badge')).toBeVisible();

    const conflict = page.locator('.conflict').filter({ hasText: 'Visite E2E 01' });

    await expect(conflict).toBeVisible();
    await expect(conflict).toContainText('Version locale');
    await expect(conflict).toContainText('Version serveur');

    await conflict.locator('[data-conflict-keep-server]').click();

    await expect(page.locator('#conflicts')).toBeHidden();
    await expect(editor).toHaveValue('Version serveur');
});
