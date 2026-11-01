import { defineConfig } from 'vitest/config';

// Deliberately separate from vite.config.js: this file only configures the
// test runner (jsdom environment) and must never affect the production
// Vite/Laravel build pipeline.
export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.js'],
    },
});
