import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it, vi } from 'vitest';
import type { Component } from 'vue';

vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn() },
    usePage: () => ({ url: '/master-data/platforms' }),
    Head: defineComponent(() => () => null),
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}));

const render = (
    component: Component,
    props: Record<string, unknown> = {},
    provide?: () => void,
): Promise<string> => {
    const Host = defineComponent({
        setup() {
            provide?.();

            return () => h(component, props);
        },
    });

    return renderToString(createSSRApp(Host));
};

describe('StatusBadge', async () => {
    const { default: StatusBadge } =
        await import('@/components/master-data/StatusBadge.vue');

    it('says whether the row is offered, and marks a system default', async () => {
        expect(await render(StatusBadge, { active: true })).toContain(
            'Tampil di pilihan',
        );
        expect(await render(StatusBadge, { active: false })).toContain(
            'Disembunyikan',
        );
        expect(await render(StatusBadge, { active: true })).not.toContain(
            'Bawaan sistem',
        );
        expect(
            await render(StatusBadge, { active: true, system: true }),
        ).toContain('Bawaan sistem');
    });
});

describe('ReorderList', async () => {
    const { default: ReorderList } =
        await import('@/components/master-data/ReorderList.vue');
    const rows = [
        { id: 1, name: 'Satu', code: null, is_active: true },
        { id: 2, name: 'Dua', code: null, is_active: false },
        { id: 3, name: 'Tiga', code: null, is_active: true },
    ];
    const props = { rows, saving: false, dirty: true, error: null };

    it('gives every row up and down buttons named after it, and a hidden marker', async () => {
        const html = await render(ReorderList, props);

        expect(html).toContain('aria-label="Naikkan Dua"');
        expect(html).toContain('aria-label="Turunkan Dua"');
        expect(html).toContain('Disembunyikan');
        expect(html).toContain('draggable="true"');
    });

    /** The opening tag of the button whose label or text contains `text`. */
    const buttonTag = (html: string, text: string): string =>
        (html.match(/<button[\s\S]*?<\/button>/g) ?? [])
            .find((button) => button.includes(text))
            ?.match(/<button[^>]*>/)?.[0] ?? '';
    /** The attribute, not the `disabled:` utility classes every button carries. */
    const isOff = (tag: string): boolean => /\sdisabled(="")?[\s>]/.test(tag);

    it('cannot move the first row up or the last row down', async () => {
        const html = await render(ReorderList, props);

        expect(isOff(buttonTag(html, 'aria-label="Naikkan Satu"'))).toBe(true);
        expect(isOff(buttonTag(html, 'aria-label="Turunkan Tiga"'))).toBe(true);
        expect(isOff(buttonTag(html, 'aria-label="Naikkan Dua"'))).toBe(false);
        expect(isOff(buttonTag(html, 'aria-label="Turunkan Satu"'))).toBe(
            false,
        );
    });

    it('keeps save off until the order changes, and while saving', async () => {
        expect(
            isOff(
                buttonTag(
                    await render(ReorderList, { ...props, dirty: false }),
                    'Simpan urutan',
                ),
            ),
        ).toBe(true);
        expect(
            isOff(buttonTag(await render(ReorderList, props), 'Simpan urutan')),
        ).toBe(false);
        expect(
            isOff(
                buttonTag(
                    await render(ReorderList, { ...props, saving: true }),
                    'Menyimpan…',
                ),
            ),
        ).toBe(true);
    });

    it('shows why saving failed', async () => {
        expect(
            await render(ReorderList, {
                ...props,
                error: 'Urutan gagal disimpan, jadi urutan semula dikembalikan.',
            }),
        ).toContain('urutan semula dikembalikan');
    });
});

describe('CheckboxGroup', async () => {
    const { default: CheckboxGroup } =
        await import('@/components/master-data/CheckboxGroup.vue');
    const options = [
        { value: 1, label: 'Satu' },
        { value: 2, label: 'Dua' },
    ];

    it('sends the ticked boxes as name[] and shows an empty text when there is nothing to pick', async () => {
        const html = await render(CheckboxGroup, {
            name: 'category_ids',
            legend: 'Kategori',
            options,
            selected: [2],
        });

        expect(html).toContain('name="category_ids[]"');
        expect(html).toContain('value="1"');
        expect(html).toContain('value="2"');
        expect(
            await render(CheckboxGroup, {
                name: 'x',
                legend: 'Kategori',
                options: [],
                selected: [],
                emptyText: 'Belum ada kategori.',
            }),
        ).toContain('Belum ada kategori.');
    });
});
