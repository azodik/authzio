#!/usr/bin/env node
import { existsSync, readdirSync, rmSync, statSync } from 'node:fs';
import { join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(fileURLToPath(new URL('..', import.meta.url)));

/** Exact paths relative to the repo root */
const exactTargets = [
    'node_modules',
    'vendor',
    'public/build',
    'public/hot',
    '.phpunit.cache',
    '.phpunit.result.cache',
    'playwright-report',
    'test-results',
    'blob-report',
    'playwright/.cache',
    'storage/pail',
    'storage/app/e2e-fixtures.json',
    'npm-debug.log',
    'yarn-error.log',
    'authzio.sqlite',
];

/** Filename / extension patterns matched anywhere under the repo (skipping ignored dirs) */
const skipDirs = new Set([
    '.git',
    'vendor',
    'node_modules',
    '.cursor',
    '.idea',
    '.vscode',
]);

const filePatterns = [
    /\.sqlite(\d|-journal|-wal|-shm)?$/i,
    /\.log$/i,
    /^\.DS_Store$/,
];

function removePath(absolutePath) {
    if (!existsSync(absolutePath)) {
        return false;
    }

    rmSync(absolutePath, { recursive: true, force: true });
    console.log(`removed  ${relative(root, absolutePath) || '.'}`);
    return true;
}

function walk(dir, onFile) {
    let entries;
    try {
        entries = readdirSync(dir, { withFileTypes: true });
    } catch {
        return;
    }

    for (const entry of entries) {
        const absolutePath = join(dir, entry.name);

        if (entry.isDirectory()) {
            if (skipDirs.has(entry.name)) {
                continue;
            }
            walk(absolutePath, onFile);
            continue;
        }

        if (entry.isFile() || entry.isSymbolicLink()) {
            onFile(absolutePath, entry.name);
        }
    }
}

let removed = 0;

for (const target of exactTargets) {
    if (removePath(join(root, target))) {
        removed += 1;
    }
}

// Accidental bare SQLite file named "authzio" at repo root (no extension)
const bareSqlite = join(root, 'authzio');
if (existsSync(bareSqlite)) {
    try {
        const stats = statSync(bareSqlite);
        if (stats.isFile()) {
            if (removePath(bareSqlite)) {
                removed += 1;
            }
        }
    } catch {
        // ignore
    }
}

walk(root, (absolutePath, name) => {
    if (!filePatterns.some((pattern) => pattern.test(name))) {
        return;
    }

    // Avoid deleting already-handled exact targets twice; rm is idempotent anyway
    if (removePath(absolutePath)) {
        removed += 1;
    }
});

console.log(removed === 0 ? 'clean: nothing to remove' : `clean: removed ${removed} path(s)`);
