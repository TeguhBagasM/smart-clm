/**
 * Shapes of what VendorController sends (App\Http\Resources\MasterData\VendorResource
 * and config/vendors.php). Texts, columns, sections and help come from the server.
 */
import type { MasterTexts } from './master-data';

export type VendorRow = {
    id: number;
    name: string;
    address: string | null;
    is_active: boolean;
    usage_count: number;
    npwp?: string | null;
    pic_name?: string | null;
    pic_phone?: string | null;
    email?: string | null;
    bank_name?: string | null;
    bank_branch?: string | null;
    bank_account_name?: string | null;
    /** Last four digits; the full number never reaches the browser. */
    bank_account_tail?: string | null;
    pph_rate_percent?: string | null;
    can: { update: boolean; deactivate: boolean };
};

export type VendorField = {
    label: string;
    help: string;
    placeholder?: string;
    keep_help?: string;
    clear?: string;
};

export type VendorInfo = {
    label: string;
    title: string;
    description: string;
    search_placeholder: string;
    empty: string;
    add: string;
    columns: { key: string; label: string }[];
    sections: { key: string; title: string; fields: string[] }[];
    fields: Record<string, VendorField>;
    blank: Record<string, string>;
    bank_mask: string;
    usage_notice: string | null;
    texts: Omit<MasterTexts, 'reorder_failed'>;
};
