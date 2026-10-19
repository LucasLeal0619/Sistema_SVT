import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const raizFront = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const raizBack = path.resolve(raizFront, '../Back_SVT');
const banco = path.resolve(raizBack, 'database/e2e.sqlite');

export default function preparar() {
    if (path.basename(banco) !== 'e2e.sqlite') {
        throw new Error('Preparação do E2E recusou o arquivo ' + banco);
    }

    fs.closeSync(fs.openSync(banco, 'a'));

    execSync('php artisan migrate:fresh --seed --no-interaction', {
        cwd: raizBack,
        stdio: 'inherit',
        env: {
            ...process.env,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: banco,
            DB_URL: '',
            CACHE_STORE: 'array',
        },
    });
}
