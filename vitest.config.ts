import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    esbuild: { jsx: 'automatic' },
    test: {
        include: ['tests/fe/**/*.test.{ts,tsx}'],
        environment: 'node',
    },
});
