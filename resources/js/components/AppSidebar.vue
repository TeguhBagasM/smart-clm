<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavModule from '@/components/NavModule.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const { currentUrl } = useCurrentUrl();

/** Modules and menus decided by the server (NavigationMenu): only what this user may open. */
const modules = computed(() => page.props.navigation ?? []);

/** The one item that matches the page best across all modules, so /documents/queue does not also light up /documents. */
const activeHref = computed(() => {
    const matching = modules.value
        .flatMap((module) => module.items)
        .map((item) => ({
            href: item.href,
            prefix: item.active_prefix ?? item.href,
        }))
        .filter(({ prefix }) => currentUrl.value.startsWith(prefix));

    return matching.sort((a, b) => b.prefix.length - a.prefix.length)[0]?.href;
});

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
        isActive: currentUrl.value === toUrl(dashboard()),
    },
]);
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
            <NavMain :items="mainNavItems" label="Menu" />
            <SidebarGroup v-if="modules.length" class="px-2 py-0">
                <SidebarGroupLabel>Modul</SidebarGroupLabel>
                <SidebarMenu>
                    <NavModule
                        v-for="module in modules"
                        :key="module.key"
                        :module="module"
                        :active-href="activeHref"
                    />
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
