import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: process.env.VITE_HMR_HOST || 'localhost',
            clientPort: Number(process.env.VITE_HMR_PORT || 5173),
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/ag-grid-products.js',
                'resources/js/ag-grid-orders.js',
            ],
            refresh: true,
        }),
    ],
});
