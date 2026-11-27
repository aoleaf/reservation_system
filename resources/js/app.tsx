import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';

declare global {
    const route: typeof routeFn;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type PageModule = { default: ComponentType<any> & Record<string, unknown> };

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const mod = await resolvePageComponent<PageModule>(`./pages/${name}.tsx`, import.meta.glob<PageModule>('./pages/**/*.tsx'));
        // FEモック期だけ：builder の props を注入し、DemoBar をかぶせる。本番ではこの if を消す
        if (import.meta.env.VITE_MOCK === 'true') {
            const { withMockProps } = await import('./mocks/with-mock-props');
            return withMockProps(name, mod);
        }
        return mod;
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#2F4A6B',
    },
});

// This will set light / dark mode on load...
initializeTheme();
