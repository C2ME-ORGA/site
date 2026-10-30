import { defineConfig } from '@playwright/test';
import path from 'node:path';

const port = Number(process.env.E2E_PORT ?? 8123);
const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${port}`;
const database = path.resolve('database/e2e.sqlite');

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL,
        viewport: { width: 390, height: 844 },
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        serviceWorkers: 'allow',
    },
    projects: [
        {
            name: 'setup',
            testMatch: /auth\.setup\.js/,
        },
        {
            name: 'chromium',
            use: {
                browserName: 'chromium',
                storageState: 'tests/e2e/.auth/admin.json',
            },
            dependencies: ['setup'],
            testIgnore: /auth\.setup\.js/,
        },
    ],
    webServer: process.env.E2E_BASE_URL
        ? undefined
        : {
            command: [
                'node tests/e2e/prepare-db.mjs',
                'php artisan migrate:fresh --force',
                'php tests/e2e/seed.php',
                `cd public && php -S 127.0.0.1:${port} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
            ].join(' && '),
            url: `${baseURL}/terrain`,
            reuseExistingServer: false,
            timeout: 180_000,
            env: {
                ...process.env,
                APP_ENV: 'local',
                APP_URL: baseURL,
                DB_CONNECTION: 'sqlite',
                DB_DATABASE: database,
                DB_FOREIGN_KEYS: 'true',
                SESSION_DRIVER: 'file',
                CACHE_STORE: 'file',
                QUEUE_CONNECTION: 'sync',
                MAIL_MAILER: 'array',
            },
        },
});
