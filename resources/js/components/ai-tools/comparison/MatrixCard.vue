<script setup lang="ts">
import DiffText from '@/components/ai-tools/comparison/DiffText.vue';
import KindBadge from '@/components/ai-tools/comparison/KindBadge.vue';
import type { ComparisonRow } from '@/types';

defineProps<{ row: ComparisonRow; labelA: string; labelB: string }>();
</script>

<template>
    <li class="flex flex-col gap-3 rounded-xl border p-4 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="font-medium">
                {{ row.a?.label ?? row.b?.label ?? 'Pembukaan' }}
                <template v-if="row.a && row.b && row.a.label !== row.b.label">
                    → {{ row.b.label }}
                </template>
            </span>
            <KindBadge :kind="row.kind.value" :label="row.kind.label" />
        </div>
        <div v-if="row.diff?.a.length">
            <p class="mb-1 text-xs text-muted-foreground">{{ labelA }}</p>
            <DiffText :runs="row.diff.a" />
        </div>
        <div v-if="row.diff?.b.length">
            <p class="mb-1 text-xs text-muted-foreground">{{ labelB }}</p>
            <DiffText :runs="row.diff.b" />
        </div>
        <p v-if="row.explanation" class="rounded-lg bg-muted/60 p-3">
            {{ row.explanation }}
        </p>
    </li>
</template>
