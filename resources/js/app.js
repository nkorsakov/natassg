import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import vuetify from './plugins/vuetify';
import { setupOffline } from './offline/setup';
import '../css/app.css';

const appName = import.meta.env.VITE_APP_NAME || 'SkyDesk';

if (import.meta.env.PROD) {
    void import('virtual:pwa-register').then(({ registerSW }) => {
        registerSW({ immediate: true });
    });
}

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        setupOffline(props.initialPage);

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(vuetify)
            .mount(el);
    },
    progress: {
        color: 'var(--skydesk-accent, #6957EE)',
    },
});
