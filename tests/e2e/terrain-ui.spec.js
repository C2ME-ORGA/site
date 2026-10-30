import { expect, test } from '@playwright/test';
import { openTerrain } from './helpers.js';

test('navigation par onglets, thème persistant et cibles tactiles', async ({ page }) => {
    await openTerrain(page);

    await expect(page.locator('#tabbar')).toBeVisible();
    await expect(page.locator('#panel-accounts')).toBeVisible();
    await expect(page.locator('#panel-tour')).toBeHidden();

    await page.locator('#tab-tour').click();
    await expect(page.locator('#panel-tour')).toBeVisible();
    await expect(page.locator('#tab-tour')).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('#panel-accounts')).toBeHidden();

    await page.locator('#tabbar').press('ArrowRight');
    await expect(page.locator('#tab-data')).toBeFocused();
    await expect(page.locator('#panel-data')).toBeVisible();
    await expect(page.locator('#data-panel')).toHaveAttribute('open', '');

    const initialTheme = await page.locator('html').getAttribute('data-theme');
    await page.locator('#theme-toggle').click();

    const toggledTheme = await page.locator('html').getAttribute('data-theme');

    expect(toggledTheme).not.toBe(initialTheme);

    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', toggledTheme);
    await expect(page.locator('#connection')).toHaveText(/En ligne/);

    await page.locator('#tab-accounts').click();
    await page.locator('#search').fill('Lyon');
    await expect(page.locator('details.account')).toHaveCount(1);
    await expect(page.locator('details.account')).toContainText('E2E Compte 02');
    await page.locator('#search').fill('');
    await expect(page.locator('details.account')).toHaveCount(12);

    const smallTargets = await page.evaluate(() => [...document.querySelectorAll('button, a.button')]
        .filter((element) => element.offsetParent !== null)
        .map((element) => {
            const rect = element.getBoundingClientRect();

            return {
                label: (element.textContent ?? '').trim().slice(0, 40),
                height: Math.round(rect.height),
                width: Math.round(rect.width),
            };
        })
        .filter((target) => target.height < 44 || target.width < 24));

    expect(smallTargets).toEqual([]);
});

test('desktop : les trois panneaux restent visibles sans barre d’onglets', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await openTerrain(page);

    await expect(page.locator('#tabbar')).toBeHidden();
    await expect(page.locator('#panel-accounts')).toBeVisible();
    await expect(page.locator('#panel-tour')).toBeVisible();
    await expect(page.locator('#panel-data')).toBeVisible();
});
