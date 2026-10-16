import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import AppLayout from './Layouts/AppLayout.vue';
import '../css/app.css';

/**
 * The landing stands on its own and the auth pages bring their own frame
 * (AuthLayout); everything else is wrapped in the signed-in app shell.
 */
const usesGuestFrame = (name) => name === 'Welcome' || name.startsWith('Auth/');

createInertiaApp({
    title: (title) => (title ? `${title} · ${import.meta.env.VITE_APP_NAME}` : import.meta.env.VITE_APP_NAME),
    resolve: async (name) => {
        const page = await resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'));

        if (!usesGuestFrame(name)) {
            page.default.layout ??= AppLayout;
        }

        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4b5563',
    },
});