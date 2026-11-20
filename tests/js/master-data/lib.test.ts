import { describe, expect, it } from 'vitest';
import {
    cellText,
    fill,
    impactDialog,
    moveItem,
    tableColumns,
} from '@/lib/masterData';
import type { ListMeta, MasterInfo } from '@/types';

const texts = {
    impact_hide_title: 'Sembunyikan ":name"?',
    impact_used: 'Masih dipakai oleh :usage data lain.',
    impact_unused: 'Belum ada data master lain yang memakainya.',
    impact_show_title: 'Tampilkan ":name" lagi?',
    impact_show: 'Pilihan ini akan muncul lagi.',
    reorder_failed: 'Urutan gagal disimpan.',
    save_failed: 'Belum bisa menyimpan.',
};
const info = (over: Partial<MasterInfo> = {}): MasterInfo => ({
    key: 'platforms',
    label: 'Platform',
    description: '',
    columns: [
        { key: 'name', label: 'Nama' },
        { key: 'platforms', label: 'Platform' },
    ],
    help: {},
    empty: { platforms: 'Semua platform' },
    usage_covers_documents: false,
    usage_notice: 'Dokumen belum ikut dihitung.',
    texts,
    ...over,
});

describe('fill', () => {
    it('replaces every placeholder with the value', () => {
        expect(fill(':a dan :a lalu :b', { a: 'x', b: 2 })).toBe(
            'x dan x lalu 2',
        );
    });
});

describe('cellText', () => {
    it('shows related rows by name and an empty list as the server label', () => {
        expect(
            cellText(
                { module: { value: 'both', label: 'Keduanya' } },
                'module',
            ),
        ).toBe('Keduanya');
        expect(cellText({ group: { id: 1, name: 'Grup A' } }, 'group')).toBe(
            'Grup A',
        );
        expect(cellText({ department: null }, 'department')).toBe('');
        expect(
            cellText(
                { platforms: [{ name: 'A' }, { name: 'B' }] },
                'platforms',
            ),
        ).toBe('A, B');
        expect(
            cellText({ platforms: [] }, 'platforms', {
                platforms: 'Semua platform',
            }),
        ).toBe('Semua platform');
    });

    it('prints plain values and never an object', () => {
        expect(cellText({ code: 'PKS', n: 3, ok: true }, 'code')).toBe('PKS');
        expect(cellText({ n: 3 }, 'n')).toBe('3');
        expect(cellText({ x: { a: 1 } }, 'x')).toBe('');
        expect(cellText({}, 'missing')).toBe('');
    });
});

describe('tableColumns', () => {
    it('makes a column sortable only when the list declares that sort', () => {
        const list = { sorts: [{ key: 'name', label: 'Nama' }] } as ListMeta;

        expect(tableColumns(info(), list)).toEqual([
            { key: 'name', label: 'Nama', sort: 'name' },
            { key: 'platforms', label: 'Platform' },
        ]);
    });
});

describe('impactDialog', () => {
    it('says how many records use the row when hiding it', () => {
        const dialog = impactDialog(
            info(),
            { name: 'Tiketux', usage_count: 3 },
            true,
        );

        expect(dialog).toEqual({
            title: 'Sembunyikan "Tiketux"?',
            message: 'Masih dipakai oleh 3 data lain.',
        });
    });

    it('adds the honest notice only when nothing is counted and the server sent one', () => {
        const row = { name: 'X', usage_count: 0 };

        expect(impactDialog(info(), row, true).message).toBe(
            'Belum ada data master lain yang memakainya. Dokumen belum ikut dihitung.',
        );
        expect(
            impactDialog(info({ usage_notice: null }), row, true).message,
        ).toBe('Belum ada data master lain yang memakainya.');
        expect(
            impactDialog(info(), { name: 'X', usage_count: 2 }, true).message,
        ).not.toContain('belum ikut');
    });

    it('asks differently when showing a row again', () => {
        expect(
            impactDialog(info(), { name: 'X', usage_count: 9 }, false),
        ).toEqual({
            title: 'Tampilkan "X" lagi?',
            message: 'Pilihan ini akan muncul lagi.',
        });
    });
});

describe('moveItem', () => {
    it('moves one item and leaves the original alone', () => {
        const items = ['a', 'b', 'c'];

        expect(moveItem(items, 0, 2)).toEqual(['b', 'c', 'a']);
        expect(moveItem(items, 2, 1)).toEqual(['a', 'c', 'b']);
        expect(items).toEqual(['a', 'b', 'c']);
    });

    it('changes nothing for a move that goes nowhere', () => {
        expect(moveItem(['a', 'b'], 0, 0)).toEqual(['a', 'b']);
        expect(moveItem(['a', 'b'], 0, 5)).toEqual(['a', 'b']);
        expect(moveItem(['a', 'b'], -1, 0)).toEqual(['a', 'b']);
    });
});
