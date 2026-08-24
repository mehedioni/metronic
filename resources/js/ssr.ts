import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createSSRApp, h } from 'vue';

const appName = import.meta.env.VITE_APP_NAME || 'RentMy Admin';

const appPages = import.meta.glob<DefineComponent>('./pages/**/*.vue');
const modulePages = import.meta.glob<DefineComponent>(
    '../../modules/*/Resources/js/pages/**/*.vue',
);

function resolvePage(name: string) {
    if (!name.includes('::')) {
        return resolvePageComponent(`./pages/${name}.vue`, appPages);
    }

    const [module, page] = name.split('::');

    return resolvePageComponent(
        `../../modules/${module}/Resources/js/pages/${page}.vue`,
        modulePages,
    );
}

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: resolvePage,
        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) }).use(plugin);
        },
    }),
);
