import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it, vi } from 'vitest';
import type { Component } from 'vue';

vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn(), post: vi.fn(), reload: vi.fn() },
    Head: defineComponent(() => () => null),
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
    Form: defineComponent(() => () => null),
    usePage: () => ({ url: '/contract-library/templates' }),
}));

const render = (
    component: Component,
    props: Record<string, unknown> = {},
): Promise<string> =>
    renderToString(
        createSSRApp(defineComponent(() => () => h(component, props))),
    );

const hints = {
    explain: 'Tulis {{Nama}} untuk isian.',
    unknown: 'Belum dikenal:',
    ok: 'Semua dikenali.',
};

describe('PlaceholderHint', async () => {
    const { default: PlaceholderHint } =
        await import('@/components/contract-library/PlaceholderHint.vue');

    it('names the fields a text has no field for, and says when all are known', async () => {
        const unknown = await render(PlaceholderHint, {
            text: 'Bayar {{Nilai}} {{Hantu}}.',
            knownFields: ['Nilai'],
            systemTokens: [],
            labels: hints,
        });
        const known = await render(PlaceholderHint, {
            text: 'Bayar {{Nilai}}.',
            knownFields: ['Nilai'],
            systemTokens: [],
            labels: hints,
        });

        expect(unknown).toContain('Belum dikenal:');
        expect(unknown).toContain('{{Hantu}}');
        expect(unknown).not.toContain('{{Nilai}}</strong>');
        expect(known).toContain('Semua dikenali.');
    });

    it('treats a system token as known, and shows nothing for a text without fields', async () => {
        const system = await render(PlaceholderHint, {
            text: 'Antara {{Party1Name}}.',
            knownFields: [],
            systemTokens: ['Party1Name'],
            labels: hints,
        });
        const plain = await render(PlaceholderHint, {
            text: 'Teks biasa.',
            knownFields: [],
            systemTokens: [],
            labels: hints,
        });

        expect(system).toContain('Semua dikenali.');
        expect(plain).not.toContain('<div');
    });
});

describe('DraftClauses', async () => {
    const { default: DraftClauses } =
        await import('@/components/contract-library/editor/DraftClauses.vue');
    const clauses = [
        { libraryId: 1, title: 'Pertama', body: 'a', sourceUpdated: false },
        { libraryId: 2, title: 'Kedua', body: 'b', sourceUpdated: true },
    ];
    const labels = {
        clauses: 'Pasal dalam draf',
        clause_up: 'Naikkan',
        clause_down: 'Turunkan',
        clause_remove: 'Keluarkan',
        clause_updated: 'Sudah diperbarui',
        clauses_empty: 'Kosong',
    };

    it('lets a clause move up or down with its buttons, and marks a library change', async () => {
        const html = await render(DraftClauses, {
            modelValue: clauses,
            labels,
        });

        expect(html).toContain('aria-label="Naikkan: Pertama"');
        expect(html).toContain('aria-label="Turunkan: Kedua"');
        expect(html).toContain('Sudah diperbarui');
    });

    it('says the draft has no clauses yet, in the words of the server', async () => {
        expect(
            await render(DraftClauses, { modelValue: [], labels }),
        ).toContain('Kosong');
    });
});

describe('ClausePicker', async () => {
    const { default: ClausePicker } =
        await import('@/components/contract-library/editor/ClausePicker.vue');
    const options = [
        {
            value: 1,
            label: 'Kerahasiaan',
            revision: 1,
            category: 'NDA',
            body: 'x',
        },
        {
            value: 2,
            label: 'Force Majeure',
            revision: 3,
            category: 'Umum',
            body: 'y',
        },
    ];
    const labels = {
        picker: 'Perpustakaan klausul',
        picker_search: 'Cari',
        picker_insert: 'Sisipkan',
        picker_used: 'Sudah di draf',
        picker_empty: 'Tidak ada',
    };

    it('offers to insert a clause that is not in the draft, and says so for one that is', async () => {
        const html = await render(ClausePicker, {
            options,
            usedIds: [1],
            labels,
        });

        expect(html).toContain('Sudah di draf');
        expect(html).toContain('aria-label="Sisipkan: Force Majeure"');
        expect(html).not.toContain('aria-label="Sisipkan: Kerahasiaan"');
    });
});

describe('DraftParties', async () => {
    const { default: DraftParties } =
        await import('@/components/contract-library/editor/DraftParties.vue');
    const labels = {
        parties: 'Pihak',
        party_add: 'Tambah pihak',
        party_remove: 'Hapus',
        party_empty: 'Belum ada pihak.',
    };

    it('shows the parties in order with their own remove buttons, or the empty message', async () => {
        const html = await render(DraftParties, {
            modelValue: ['Pihak Pertama', 'Pihak Kedua'],
            labels,
        });

        expect(html).toContain('aria-label="Hapus 2"');
        expect(
            await render(DraftParties, { modelValue: [], labels }),
        ).toContain('Belum ada pihak.');
    });
});
