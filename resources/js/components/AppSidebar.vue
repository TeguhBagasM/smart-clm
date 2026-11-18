<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ClipboardCheck,
    FileText,
    KeyRound,
    LayoutGrid,
    Settings2,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import DocTypeConfigController from '@/actions/App/Http/Controllers/Documents/Config/DocTypeConfigController';
import TaskQueueController from '@/actions/App/Http/Controllers/Documents/TaskQueueController';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const { can } = usePermissions();
const { currentUrl } = useCurrentUrl();

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const documentNavItems: NavItem[] = [
    {
        title: 'Tugas saya',
        href: TaskQueueController.index(),
        icon: ClipboardCheck,
    },
    {
        title: 'Dokumen Terkontrol',
        href: DocumentController.index(),
        icon: FileText,
    },
];

const configurationNavItems: NavItem[] = [
    {
        title: 'Pengaturan Dokumen',
        href: DocTypeConfigController.index(),
        icon: Settings2,
        activePrefix: '/documents/config',
        permission: 'dcs.config',
    },
    {
        title: 'Pengguna',
        href: UserController.index(),
        icon: Users,
        permission: 'settings.manage',
    },
    {
        title: 'Hak Akses',
        href: RoleController.index(),
        icon: KeyRound,
        permission: 'roles.view',
    },
];

const visibleConfigurationNavItems = computed(() =>
    configurationNavItems.filter(
        (item) => item.permission === undefined || can(item.permission),
    ),
);

/** The one item that matches the page best across all groups, so /documents/queue does not also light up /documents. */
const activeHref = computed(() => {
    const prefix = (item: NavItem): string =>
        item.activePrefix ?? toUrl(item.href);
    const matching = [
        ...mainNavItems,
        ...documentNavItems,
        ...visibleConfigurationNavItems.value,
    ].filter((item) => currentUrl.value.startsWith(prefix(item)));

    return matching.sort((a, b) => prefix(b).length - prefix(a).length)[0]
        ?.title;
});

const marked = (items: NavItem[]): NavItem[] =>
    items.map((item) => ({
        ...item,
        isActive: item.title === activeHref.value,
    }));
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="marked(mainNavItems)" label="Menu" />
            <NavMain :items="marked(documentNavItems)" label="Dokumen" />
            <NavMain
                v-if="visibleConfigurationNavItems.length"
                :items="marked(visibleConfigurationNavItems)"
                label="Konfigurasi"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
