/**
 * Shapes of what App\Http\Controllers\MasterData sends: the index, a list page,
 * the employee page and the options of their forms. Texts, columns and help
 * come from the server (config/master_data.php); nothing here is a business list.
 */
export type MasterOption = { value: number; label: string; code?: string };

export type MasterCompany = { id: number; name: string };

export type MasterGroupItem = {
    key: string;
    label: string;
    description: string;
    href: string;
    count: number | null;
};

export type MasterGroup = {
    key: string;
    title: string;
    description: string;
    items: MasterGroupItem[];
};

export type MasterTexts = {
    impact_hide_title: string;
    impact_used: string;
    impact_unused: string;
    impact_show_title: string;
    impact_show: string;
    reorder_failed: string;
    save_failed: string;
};

export type MasterInfo = {
    key: string;
    label: string;
    description: string;
    columns: { key: string; label: string }[];
    help: Record<string, string>;
    /** Shown instead of a blank cell, e.g. "Semua platform". */
    empty?: Record<string, string>;
    usage_covers_documents: boolean;
    usage_notice: string | null;
    texts: MasterTexts;
};

type Can = { update: boolean; deactivate: boolean };

export type MasterRow = {
    id: number;
    name: string;
    code?: string;
    sort_order: number;
    is_active: boolean;
    is_system?: boolean;
    usage_count: number;
    can: Can;
    is_employee_key?: boolean;
    is_employee?: boolean;
    module?: { value: string; label: string };
    retention_years?: number | null;
    categories?: { id: number; name: string }[];
    extra_codes?: { label: string; value: string }[];
    platforms?: { id: number; name: string }[];
    partner_group_id?: number;
    group?: { id: number; name: string } | null;
};

export type EmployeeRow = {
    id: number;
    name: string;
    position: string | null;
    employee_number: string | null;
    start_date: string | null;
    is_active: boolean;
    department: { id: number; name: string } | null;
    employment_type: { id: number; name: string } | null;
    user: { id: number; name: string } | null;
    can: Can;
};

export type MasterFormOptions = {
    modules?: { value: string; label: string }[];
    categories?: { value: number; label: string }[];
    platforms?: MasterOption[];
    groups?: { value: number; label: string }[];
};

export type EmployeeFormOptions = {
    departments: MasterOption[];
    employment_types: MasterOption[];
    /** Null when the user may not see the account list. */
    users: { value: number; label: string }[] | null;
};

export type OrderRow = {
    id: number;
    name: string;
    code: string | null;
    is_active: boolean;
};

export type ReorderState = { available: boolean; hint: string | null };
