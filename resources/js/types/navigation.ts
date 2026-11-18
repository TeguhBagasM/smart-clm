import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import type { Permission } from '@/types/rbac';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    /** Set by the sidebar once it knows which item matches the page best. */
    isActive?: boolean;
    /** A path prefix that also marks this item active (for a section with several pages). */
    activePrefix?: string;
    /** Hide the item unless the user has this permission. */
    permission?: Permission;
};
