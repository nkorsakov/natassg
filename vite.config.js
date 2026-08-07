import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        vuetify({ autoImport: true }),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: false,
            scope: '/',
            base: '/',
            includeAssets: [
                'icons/icon-192.png',
                'icons/icon-512.png',
                'icons/apple-touch-icon.png',
            ],
            manifest: {
                id: '/',
                name: 'SkyDesk',
                short_name: 'SkyDesk',
                description: 'SkyDesk — поручения, календарь и финансы для личного помощника',
                lang: 'ru',
                theme_color: '#6957EE',
                background_color: '#F5F5F9',
                display: 'standalone',
                orientation: 'portrait-primary',
                start_url: '/dashboard',
                scope: '/',
                icons: [
                    {
                        src: '/icons/icon-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/icons/icon-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                    },
                    {
                        src: '/icons/icon-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                navigateFallback: null,
                globPatterns: ['**/*.{js,css,woff2,woff,png,svg,ico,webp}'],
                runtimeCaching: [
                    {
                        urlPattern: ({ request }) =>
                            request.mode === 'navigate'
                            || request.headers.get('X-Inertia') === 'true',
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'skydesk-pages',
                            networkTimeoutSeconds: 4,
                            expiration: {
                                maxEntries: 64,
                                maxAgeSeconds: 60 * 60 * 24 * 14,
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                    {
                        urlPattern: /^https:\/\/fonts\.googleapis\.com\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'google-fonts-css',
                            expiration: {
                                maxEntries: 8,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                        },
                    },
                    {
                        urlPattern: /^https:\/\/fonts\.gstatic\.com\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'google-fonts-files',
                            expiration: {
                                maxEntries: 16,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                        },
                    },
                ],
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
