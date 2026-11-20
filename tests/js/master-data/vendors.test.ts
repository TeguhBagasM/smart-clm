import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import { vendorCell, vendorFieldValue } from '@/lib/vendors';
import type { Component } from 'vue';
import type { VendorInfo, VendorRow } from '@/types';

const info: VendorInfo = {
    label: 'Vendor',
    title: 'Data vendor',
    description: 'Deskripsi',
    search_placeholder: 'Cari',
    empty: 'Kosong',
    add: 'Tambah vendor',
    columns: [
        { key: 'name', label: 'Nama vendor' },
        { key: 'pic', label: 'Kontak' },
        { key: 'bank', label: 'Rekening' },
    ],
    sections: [
        { key: 'main', title: 'Data utama', fields: ['name', 'address'] },
        {
            key: 'bank',
            title: 'Rekening dan pajak',
            fields: ['bank_account_number'],
        },
    ],
    fields: {
        name: { label: 'Nama vendor', help: 'Bantuan nama' },
        address: { label: 'Alamat', help: 'Bantuan alamat' },
        bank_account_number: {
            label: 'Nomor rekening',
            help: 'Hanya angka.',
            keep_help: 'Tersimpan: rekening berakhiran :tail.',
            clear: 'Hapus nomor rekening yang tersimpan',
        },
    },
    blank: { pic: 'Belum diisi', bank: 'Belum diisi' },
    bank_mask: ':bank •••• :tail',
    usage_notice: null,
    texts: {
        impact_hide_title: '',
        impact_used: '',
        impact_unused: '',
        impact_show_title: '',
        impact_show: '',
        save_failed: '',
    },
};

const row: VendorRow = {
    id: 1,
    name: 'CV Uji',
    address: 'Jl. Uji',
    is_active: true,
    usage_count: 0,
    pic_name: 'Pak Uji',
    pic_phone: '0812',
    bank_name: 'Bank Uji',
    bank_account_tail: '7890',
    can: { update: true, deactivate: true },
};

describe('vendorCell', () => {
    it('joins the contact, masks the account and falls back to the server label', () => {
        expect(vendorCell(row, 'pic', info)).toBe('Pak Uji · 0812');
        expect(vendorCell(row, 'bank', info)).toBe('Bank Uji •••• 7890');
        expect(vendorCell(row, 'name', info)).toBe('CV Uji');

        const bare = {
            ...row,
            pic_name: null,
            pic_phone: null,
            bank_account_tail: null,
        };
        expect(vendorCell(bare, 'pic', info)).toBe('Belum diisi');
        expect(vendorCell(bare, 'bank', info)).toBe('Belum diisi');
    });
});

describe('vendorFieldValue', () => {
    it('starts the account number empty and the rest from the row', () => {
        expect(vendorFieldValue(row, 'bank_account_number')).toBe('');
        expect(vendorFieldValue(row, 'address')).toBe('Jl. Uji');
        expect(vendorFieldValue(undefined, 'name')).toBe('');
    });
});

describe('VendorFields', async () => {
    const { default: VendorFields } =
        await import('@/components/master-data/fields/VendorFields.vue');
    const render = (props: Record<string, unknown>): Promise<string> =>
        renderToString(
            createSSRApp(
                defineComponent(
                    () => () => h(VendorFields as Component, props),
                ),
            ),
        );

    it('draws the server sections and a write-only account field for a new vendor', async () => {
        const html = await render({ info, errors: {} });

        expect(html).toContain('Data utama');
        expect(html).toContain('Rekening dan pajak');
        expect(html).toContain('Hanya angka.');
        expect(html).not.toContain('Hapus nomor rekening');
    });

    it('says which account is stored, offers to remove it and never fills it in', async () => {
        const html = await render({
            info,
            row,
            errors: { name: 'Nama salah' },
        });

        expect(html).toContain('Tersimpan: rekening berakhiran 7890.');
        expect(html).toContain('Hapus nomor rekening yang tersimpan');
        expect(html).toContain('Nama salah');
        expect(html).not.toContain('value="7890"');
    });
});
