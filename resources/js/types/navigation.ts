import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

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
};

/** One menu of a module, as sent by App\Support\Navigation\NavigationMenu. */
export type NavModuleItem = {
    title: string;
    icon: string;
    href: string;
    active_prefix: string | null;
    /** A placeholder page: the module is not migrated yet. */
    in_development: boolean;
};

/** One sidebar dropdown (shared prop `navigation`). */
export type NavModule = {
    key: string;
    title: string;
    icon: string;
    items: NavModuleItem[];
};
