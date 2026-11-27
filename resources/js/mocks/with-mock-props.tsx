// ページに builder の props を注入し、DemoBar を上に描く（VITE_MOCK のときだけ app.tsx から使う）。
import type { ComponentType } from 'react';
import DemoBar from './demo-bar';
import { builders, type ServerProps } from './props';
import { useMockStore } from './store';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type PageModule = { default: ComponentType<any> & Record<string, unknown> };

const cache = new Map<string, PageModule>();

export function withMockProps(name: string, mod: PageModule): PageModule {
    const hit = cache.get(name);
    if (hit) return hit;
    const Page = mod.default;
    const builder = builders[name];

    function MockPage(serverProps: ServerProps) {
        const state = useMockStore();
        const props = builder ? { ...serverProps, ...builder(state, serverProps) } : serverProps;
        return (
            <>
                <DemoBar page={name} />
                <Page {...props} />
            </>
        );
    }
    // layout など、ページの static プロパティーも引き継ぐ
    Object.assign(MockPage, Page);
    MockPage.displayName = `Mock(${Page.displayName ?? Page.name ?? name})`;

    const wrapped = { ...mod, default: MockPage as PageModule['default'] };
    cache.set(name, wrapped);
    return wrapped;
}
