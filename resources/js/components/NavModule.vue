<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { navIcon } from '@/components/navIcons';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import type { NavModule } from '@/types';

const props = defineProps<{
    module: NavModule;
    /** href of the one item that matches the page best, across all modules. */
    activeHref?: string;
}>();

const { state, setOpen } = useSidebar();

const containsActive = computed(() =>
    props.module.items.some((item) => item.href === props.activeHref),
);
const open = ref(containsActive.value);

watch(containsActive, (active) => {
    if (active) {
        open.value = true;
    }
});

/** In icon mode the items are hidden, so a click on the module widens the sidebar first. */
function expandSidebar(): void {
    if (state.value === 'collapsed') {
        setOpen(true);
        open.value = true;
    }
}
</script>

<template>
    <Collapsible v-model:open="open" as-child class="group/module">
        <SidebarMenuItem>
            <CollapsibleTrigger as-child>
                <SidebarMenuButton
                    :tooltip="module.title"
                    :is-active="containsActive && !open"
                    @click="expandSidebar"
                >
                    <component :is="navIcon(module.icon)" />
                    <span>{{ module.title }}</span>
                    <ChevronRight
                        class="ml-auto transition-transform duration-200 group-data-[state=open]/module:rotate-90"
                    />
                </SidebarMenuButton>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <SidebarMenuSub>
                    <SidebarMenuSubItem
                        v-for="item in module.items"
                        :key="item.href"
                    >
                        <SidebarMenuSubButton
                            as-child
                            :is-active="item.href === activeHref"
                            class="relative data-[active=true]:before:absolute data-[active=true]:before:inset-y-1 data-[active=true]:before:-left-2.5 data-[active=true]:before:w-0.5 data-[active=true]:before:rounded-full data-[active=true]:before:bg-highlight"
                        >
                            <Link :href="item.href">
                                <component :is="navIcon(item.icon)" />
                                <span class="min-w-0 flex-1 truncate">
                                    {{ item.title }}
                                </span>
                                <span
                                    v-if="item.in_development"
                                    class="shrink-0 rounded-sm bg-muted px-1.5 py-0.5 text-[10px] leading-none text-muted-foreground"
                                >
                                    Segera
                                </span>
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </CollapsibleContent>
        </SidebarMenuItem>
    </Collapsible>
</template>
