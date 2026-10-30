import { expect, test } from '@playwright/test';
import { openTerrain, openAccount, tinyPng } from './helpers.js';

test('une photo compressée est envoyée et reste visible après rechargement', async ({ page }) => {
    await openTerrain(page);

    const card = await openAccount(page, 'E2E Compte 02');
    const row = card.locator('.activity').filter({ hasText: 'Visite E2E 02' }).first();
    const input = row.locator('input[data-photo-input]');

    await expect(input).toHaveCount(1);
    await input.setInputFiles({
        name: 'chantier.png',
        mimeType: 'image/png',
        buffer: tinyPng(),
    });

    await expect(row.locator('.photo--pending')).toHaveCount(1);
    await expect(row.locator('.photos__count')).toContainText('1/5');

    const serverPhoto = row.locator('img[src*="/terrain/attachments/"]');

    await expect(serverPhoto).toBeVisible({ timeout: 20_000 });
    await expect(row.locator('.photo--pending')).toHaveCount(0);
    await expect
        .poll(() => serverPhoto.evaluate((image) => image.complete && image.naturalWidth > 0))
        .toBe(true);

    await page.reload();
    await expect(page.locator('#count')).toContainText('12 compte');

    const reloadedCard = await openAccount(page, 'E2E Compte 02');
    const reloadedRow = reloadedCard.locator('.activity').filter({ hasText: 'Visite E2E 02' }).first();

    await expect(reloadedRow.locator('.photos__count')).toContainText('1/5');
    await expect(reloadedRow.locator('img[src*="/terrain/attachments/"]')).toBeVisible();
});

test('tournée ordonnée, découpée en itinéraires et réinitialisable', async ({ page }) => {
    await openTerrain(page);

    for (let index = 1; index <= 12; index += 1) {
        const name = `E2E Compte ${String(index).padStart(2, '0')}`;
        const card = await openAccount(page, name);

        await card.locator('[data-tour-toggle]').click();
        await card.locator('summary').click();
    }

    await expect(page.locator('#tab-tour-badge')).toHaveText('12');
    await page.locator('#tab-tour').click();
    await expect(page.locator('#tour-list .tour__item')).toHaveCount(12);

    const routes = page.locator('#tour-routes a');

    await expect(routes).toHaveCount(2);
    await expect(routes.nth(0)).toContainText('étapes 1 à 10');
    await expect(routes.nth(1)).toContainText('étapes 11 à 12');

    const href = await routes.nth(0).getAttribute('href');

    expect(href).toContain('https://www.google.com/maps/dir/');
    expect(href).toContain('waypoints=');
    expect(href).toContain('travelmode=driving');

    await page.locator('#tour-list [data-tour-down]').first().click();
    await expect(page.locator('#tour-list .tour__item').first()).toContainText('E2E Compte 02');

    await page.reload();
    await expect(page.locator('#panel-tour')).toBeVisible();
    await expect(page.locator('#tour-list .tour__item').first()).toContainText('E2E Compte 02');

    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('#tour-reset').click();

    await expect(page.locator('#tour-list .tour__item')).toHaveCount(0);
    await expect(page.locator('#tab-tour-badge')).toBeHidden();
    await expect(page.locator('#tour-routes a')).toHaveCount(0);
});
