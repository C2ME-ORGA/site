import { rmSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const root = process.cwd();

writeFileSync(path.join(root, 'database', 'e2e.sqlite'), '');

rmSync(path.join(root, 'storage', 'app', 'private', 'crm-activity-attachments'), {
    recursive: true,
    force: true,
});
