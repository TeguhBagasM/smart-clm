<script setup lang="ts">
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';

export type PageTab = {
    key: string;
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
    count?: number;
};

defineProps<{
    tabs: PageTab[];
    active: string;
    label: string;
    /** Props to reload when switching tabs (default: the whole page). */
    only?: string[];
}>();
</script>

<template>
    <nav class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0" :aria-label="label">
        <ul class="flex w-max gap-2 border-b md:w-auto">
            <li v-for="tab in tabs" :key="tab.key">
                <Link
                    :href="tab.href"
                    :only="only"
                    preserve-scroll
                    :aria-current="tab.key === active ? 'page' : undefined"
                    :class="[
                        '-mb-px flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-colors',
                        tab.key === active
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    ]"
                >
                    {{ tab.label }}
                    <Badge
                        v-if="tab.count !== undefined"
                        :variant="tab.count > 0 ? 'default' : 'secondary'"
                        class="min-w-6"
                    >
                        {{ tab.count }}
                    </Badge>
                </Link>
            </li>
        </ul>
    </nav>
</template>
