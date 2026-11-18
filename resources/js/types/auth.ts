import type { Permission, RoleSummary, TenantSummary } from '@/types/rbac';

/** Shape of App\Http\Resources\AuthUserResource. */
export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    role: RoleSummary;
    tenant: TenantSummary | null;
    two_factor_enabled: boolean;
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
