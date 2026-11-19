import type { Permission, RoleSummary, TenantSummary } from '@/types/rbac';

/** Server-decided abilities that show or hide menus and buttons (`AuthUserResource::can`). */
export type UserAbilities = {
    create_documents: boolean;
    configure_documents: boolean;
    view_task_queue: boolean;
};

/** Shape of App\Http\Resources\AuthUserResource. */
export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: RoleSummary;
    tenant: TenantSummary | null;
    two_factor_enabled: boolean;
    can: UserAbilities;
};

export type Auth = {
    user: User;
    permissions: Permission[];
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
