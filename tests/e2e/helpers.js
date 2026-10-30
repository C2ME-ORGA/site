import { randomUUID } from 'node:crypto';
import { expect } from '@playwright/test';

export const ADMIN = {
    email: 'admin@e2e.test',
    password: 'password',
};

export async function login(page) {
    await page.goto('/admin/login');
    await page.locator('input[type="email"]').fill(ADMIN.email);
    await page.locator('input[type="password"]').fill(ADMIN.password);
    await page.locator('button[type="submit"]').click();

    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    await page.goto('/terrain');

    await expect(page.locator('#connection')).toHaveText(/En ligne/);
    await expect(page.locator('#count')).toContainText('12 compte');
}

export async function openTerrain(page) {
    await page.goto('/terrain');

    await expect(page.locator('#connection')).toHaveText(/En ligne/);
    await expect(page.locator('#count')).toContainText('12 compte');
}

export async function openAccount(page, name) {
    const card = page.locator('details.account').filter({ hasText: name }).first();

    await expect(card).toBeVisible();

    if (!(await card.evaluate((element) => element.open))) {
        await card.locator('summary').click();
    }

    return card;
}

export async function getActivity(page, subject) {
    return page.evaluate(async (wanted) => {
        const response = await fetch('/terrain/activities?limit=200', {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        const data = await response.json();

        return (data.activities ?? []).find((activity) => activity.subject === wanted) ?? null;
    }, subject);
}

export async function serverUpdateActivity(page, activity, payload) {
    const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');

    const response = await page.context().request.post('/terrain/sync', {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
        },
        data: {
            operations: [
                {
                    op_id: randomUUID(),
                    type: 'update_activity',
                    entity_id: activity.id,
                    payload,
                    base_updated_at: activity.updated_at,
                },
            ],
        },
    });

    expect(response.ok()).toBeTruthy();

    const body = await response.json();

    expect(body.results[0].status).toBe('ok');
}

export function tinyPng() {
    return Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        'base64',
    );
}
