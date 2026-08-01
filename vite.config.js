import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

const host = 'authzio.test';
const herdCertDir = path.resolve(
    os.homedir(),
    'Library/Application Support/Herd/config/valet/Certificates',
);
const keyPath = path.join(herdCertDir, `${host}.key`);
const certPath = path.join(herdCertDir, `${host}.crt`);
const https = fs.existsSync(keyPath) && fs.existsSync(certPath)
    ? {
          key: fs.readFileSync(keyPath),
          cert: fs.readFileSync(certPath),
      }
    : undefined;

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/console/main.tsx',
            ],
            refresh: true,
            fonts: [
                bunny('Syne', {
                    weights: [500, 600, 700, 800],
                    optimizedFallbacks: false,
                }),
                bunny('Source Sans 3', {
                    weights: [400, 500, 600, 700],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve('resources/js/console'),
        },
    },
    server: {
        host,
        port: 5173,
        strictPort: true,
        https,
        hmr: {
            host,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
