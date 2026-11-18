<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import TaskQueueController from '@/actions/App/Http/Controllers/Documents/TaskQueueController';
import { Badge } from '@/components/ui/badge';
import type { TaskKindTab } from '@/types';

defineProps<{ kinds: TaskKindTab[]; active: string }>();
</script>

<template>
    <nav
        class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
        aria-label="Jenis tugas"
    >
        <ul class="flex w-max gap-2 border-b md:w-auto">
            <li v-for="kind in kinds" :key="kind.value">
                <Link
                    :href="
                        TaskQueueController.index({
                            query: { kind: kind.value },
                        })
                    "
                    :only="['items', 'list', 'kind', 'kinds']"
                    preserve-scroll
                    :aria-current="kind.value === active ? 'page' : undefined"
                    :class="[
                        '-mb-px flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-colors',
                        kind.value === active
                            ? 'border-primary text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground',
                    ]"
                >
                    {{ kind.label }}
                    <Badge
                        :variant="kind.count > 0 ? 'default' : 'secondary'"
                        class="min-w-6"
                    >
                        {{ kind.count }}
                    </Badge>
                </Link>
            </li>
        </ul>
    </nav>
</template>
