import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from '@playwright/test';

const raizFront = path.dirname(fileURLToPath(import.meta.url));
const raizBack = path.resolve(raizFront, '../Back_SVT');
const banco = path.resolve(raizBack, 'database/e2e.sqlite');

export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    workers: 1,
    timeout: 120000,
    expect: { timeout: 15000 },
    reporter: [['list']],
    use: {
        baseURL: 'http://127.0.0.1:5174',
        channel: 'chrome',
        viewport: { width: 1440, height: 900 },
        trace: 'off',
    },
    globalSetup: './e2e/preparar.js',
    webServer: [
        {
            command: 'php artisan serve --host=127.0.0.1 --port=8001',
            cwd: raizBack,
            env: {
                ...process.env,
                DB_CONNECTION: 'sqlite',
                DB_DATABASE: banco,
                DB_URL: '',
                CACHE_STORE: 'array',
            },
            url: 'http://127.0.0.1:8001/up',
            reuseExistingServer: false,
            timeout: 120000,
        },
        {
            command: 'npx vite --host 127.0.0.1 --port 5174 --strictPort',
            cwd: raizFront,
            env: {
                ...process.env,
                VITE_API_URL: 'http://127.0.0.1:8001',
            },
            url: 'http://127.0.0.1:5174',
            reuseExistingServer: false,
            timeout: 120000,
        },
    ],
});
