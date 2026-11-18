/**
 * Permission keys mirrored from App\Enums\Permission. The server is the
 * authority: these are only used to hide UI the user cannot use.
 */
export type Permission =
    | 'contract.edit'
    | 'contract.delete'
    | 'contract.move'
    | 'contract.classify'
    | 'contract.bulk'
    | 'review.access'
    | 'review.markup_decide'
    | 'review.send_external'
    | 'signing.manage'
    | 'release.announce'
    | 'retention.manage'
    | 'templates.manage'
    | 'dcs.config'
    | 'dcs.edit'
    | 'dcs.release'
    | 'legaljob.request'
    | 'legaljob.process'
    | 'budgets.manage'
    | 'employees.manage'
    | 'vendors.manage'
    | 'settings.manage'
    | 'settings.employment_types';

export type Option<T = string> = {
    value: T;
    label: string;
};

export type TenantSummary = {
    id: number;
    name: string;
};

/** Shape of App\Http\Resources\UserResource. */
export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: Option;
    tenant: TenantSummary | null;
    is_active: boolean;
    last_login_at: string | null;
    can: {
        update: boolean;
    };
};

/** A Laravel paginator serialized through an API resource collection. */
export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
    };
};
