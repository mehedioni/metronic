import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from '@/composables/useAppearance';
import { setCurrency } from '@/lib/currency';
import type { SharedData } from '@/types';
import '../css/app.css';

const appName = import.meta.env.VITE_APP_NAME ?? '';

const appPages = import.meta.glob<DefineComponent>('./pages/**/*.vue');
const modulePages = import.meta.glob<DefineComponent>(
    '../../modules/*/Resources/js/pages/**/*.vue',
);

/**
 * Module pages are addressed as "Inventory::Products/Index" so each module
 * keeps ownership of its own Vue files (see modules/README.md). Anything
 * without "::" resolves from resources/js/pages as usual.
 */
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

createInertiaApp({
    title: (title) =>
        [title, appName].filter(Boolean).join(' - ') || document.title,
    resolve: resolvePage,
    setup({ el, App, props, plugin }) {
        /**
         * money() reads the store's currency from a module-level ref, so it is
         * set here — once on boot and again after every visit, which is what
         * makes saved settings take effect without a reload.
         */
        const apply = (shared: Partial<SharedData>) =>
            setCurrency(shared.settings?.currency);

        apply(props.initialPage.props as Partial<SharedData>);
        router.on('success', (event) =>
            apply(event.detail.page.props as Partial<SharedData>),
        );

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
