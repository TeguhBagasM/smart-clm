import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it, vi } from 'vitest';
import { provideListQuery } from '@/composables/useListQuery';
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

describe('IndexCards', async () => {
    const { default: IndexCards } =
        await import('@/components/master-data/IndexCards.vue');
    const groups = [
        {
            key: 'g',
            title: 'Dokumen',
            description: 'Pilihan formulir.',
            items: [
                {
                    key: 'platforms',
                    label: 'Platform',
                    description: 'Layanan perusahaan.',
                    href: '/master-data/platforms',
                    count: 3,
                },
                {
                    key: 'employees',
                    label: 'Karyawan',
                    description: 'Daftar karyawan.',
                    href: '/master-data/employees',
                    count: null,
                },
            ],
        },
    ];

    it('shows each list with its purpose and active count, and no count when none is known', async () => {
        const html = await render(IndexCards, { groups, companyId: null });

        expect(html).toContain('Dokumen');
        expect(html).toContain('Layanan perusahaan.');
        expect(html).toContain('<strong>3</strong> tampil di pilihan');
        expect(html.match(/tampil di pilihan/g)).toHaveLength(1);
        expect(html).toContain('href="/master-data/platforms"');
    });

    it('keeps the chosen company in every link for a super admin', async () => {
        expect(await render(IndexCards, { groups, companyId: 7 })).toContain(
            'href="/master-data/platforms?tenant_id=7"',
        );
    });
});

describe('MasterHeader', async () => {
    const { default: MasterHeader } =
        await import('@/components/master-data/MasterHeader.vue');

    it('shows the notice from the server only when there is one', async () => {
        const base = { title: 'Platform', description: 'Untuk apa.' };

        expect(
            await render(MasterHeader, {
                ...base,
                notice: 'Dokumen belum ikut dihitung.',
            }),
        ).toContain('Dokumen belum ikut dihitung.');
        expect(
            await render(MasterHeader, { ...base, notice: null }),
        ).not.toContain('belum ikut');
    });

    it('offers the company choice only to a super admin', async () => {
        const base = { title: 'Platform', description: 'x' };

        expect(await render(MasterHeader, base)).not.toContain('Perusahaan');
        expect(
            await render(MasterHeader, {
                ...base,
                companies: [{ value: 1, label: 'PT Satu' }],
                company: { id: 1, name: 'PT Satu' },
            }),
        ).toContain('Perusahaan');
    });
});

describe('ListEmpty', async () => {
    const { default: ListEmpty } =
        await import('@/components/list/ListEmpty.vue');
    const query = { clearAll: vi.fn() } as never;

    it('invites to add the first row when the list is empty, with no reset button', async () => {
        const html = await render(
            ListEmpty,
            {
                filtered: false,
                emptyText: 'Belum ada platform. Tambahkan yang pertama.',
            },
            () => provideListQuery(query),
        );

        expect(html).toContain('Belum ada platform. Tambahkan yang pertama.');
        expect(html).not.toContain('Hapus pencarian');
    });

    it('says nothing matched and offers to reset when a search or filter is active', async () => {
        const html = await render(ListEmpty, { filtered: true }, () =>
            provideListQuery(query),
        );

        expect(html).toContain('Tidak ada data yang cocok');
        expect(html).toContain('Hapus pencarian dan filter');
    });
});

describe('Forbidden page', async () => {
    const { default: Forbidden } = await import('@/pages/errors/Forbidden.vue');

    it('explains in the words from the server and offers a way back', async () => {
        const html = await render(Forbidden, {
            title: 'Anda tidak punya akses ke halaman ini',
            message: 'Hubungi admin perusahaan Anda.',
        });

        expect(html).toContain('Anda tidak punya akses ke halaman ini');
        expect(html).toContain('Hubungi admin perusahaan Anda.');
        expect(html).toContain('Kembali ke beranda');
    });
});
