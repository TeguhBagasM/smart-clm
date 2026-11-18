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
    | 'settings.employment_types'
    | 'roles.view'
    | 'roles.manage';

export type Option<T = string> = {
    value: T;
    label: string;
};

export type TenantSummary = {
    id: number;
    name: string;
};

/** Shape of App\Http\Resources\RoleSummaryResource. */
export type RoleSummary = {
    id: number;
    key: string;
    name: string;
    level: number;
    is_system: boolean;
};

/** A selectable role; `tenant_id` is null for roles shared by all tenants. */
export type RoleOption = Option & {
    tenant_id: number | null;
};

/** Shape of App\Http\Resources\UserResource. */
export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: RoleSummary;
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

export type PermissionGroup = {
    group: string;
    permissions: Option<Permission>[];
};

/** Shape of App\Http\Resources\RoleResource plus the role's permission keys. */
export type RoleRecord = {
    id: number;
    key: string;
    name: string;
    description: string | null;
    level: number;
    is_system: boolean;
    is_assignable: boolean;
    is_super_admin: boolean;
    tenant: TenantSummary | null;
    users_count: number;
    permissions: Permission[];
    can: {
        update: boolean;
        delete: boolean;
    };
};

/** An existing role whose permissions can pre-fill a new role. */
export type RoleTemplate = {
    id: number;
    name: string;
    permissions: Permission[];
};
