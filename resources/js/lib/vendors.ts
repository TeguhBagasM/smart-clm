import { fill } from '@/lib/masterData';
import type { VendorInfo, VendorRow } from '@/types';

/**
 * What a cell of the vendor table shows for a column key: the contact as
 * "name · phone", the account as the server's mask with the last four digits,
 * and the server's "blank" label when there is nothing.
 */
export function vendorCell(
    row: VendorRow,
    key: string,
    info: Pick<VendorInfo, 'blank' | 'bank_mask'>,
): string {
    const blank = info.blank[key] ?? '';

    if (key === 'pic') {
        const parts = [row.pic_name, row.pic_phone].filter(Boolean);

        return parts.length > 0 ? parts.join(' · ') : blank;
    }

    if (key === 'bank') {
        return row.bank_account_tail
            ? fill(info.bank_mask, {
                  bank: row.bank_name ?? '',
                  tail: row.bank_account_tail,
              }).trim()
            : blank;
    }

    const value = (row as Record<string, unknown>)[key];

    return typeof value === 'string' && value !== '' ? value : blank;
}

/** How each form field is typed on a phone keyboard; anything not listed is plain text. */
export const vendorInputs: Record<
    string,
    { type?: string; inputmode?: 'numeric' | 'decimal' | 'tel' | 'email' }
> = {
    npwp: { inputmode: 'numeric' },
    pic_phone: { type: 'tel', inputmode: 'tel' },
    email: { type: 'email', inputmode: 'email' },
    bank_account_number: { inputmode: 'numeric' },
    pph_rate_percent: { inputmode: 'decimal' },
};

/** The value a field starts with when changing a row (the account number always starts empty). */
export function vendorFieldValue(
    row: VendorRow | undefined,
    key: string,
): string {
    if (!row || key === 'bank_account_number') {
        return '';
    }

    const value = (row as Record<string, unknown>)[key];

    return typeof value === 'string' ? value : '';
}
