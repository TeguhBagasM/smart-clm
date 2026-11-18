/**
 * Shapes of App\Support\Listing\ListSpec::meta(): the server describes the
 * search, filters, sorts and page sizes of a list and the frontend draws them.
 */
export type FilterOption = { value: string; label: string };

export type DateRangeValue = { from: string | null; to: string | null };

export type FilterValue = string | string[] | boolean | DateRangeValue | null;

export type ListFilter = {
    key: string;
    label: string;
    type: 'select' | 'multi' | 'boolean' | 'date_range';
    options: FilterOption[];
    primary: boolean;
    value: FilterValue;
};

export type ListMeta = {
    search: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
    per_page_options: number[];
    sorts: { key: string; label: string }[];
    filters: ListFilter[];
    active_count: number;
};

export type ListColumn = {
    key: string;
    label: string;
    /** The key of a sort the list declares; makes the header clickable. */
    sort?: string;
    class?: string;
};
