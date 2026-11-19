<script setup lang="ts">
import DiffText from '@/components/ai-tools/comparison/DiffText.vue';
import KindBadge from '@/components/ai-tools/comparison/KindBadge.vue';
import type { ComparisonRow } from '@/types';

defineProps<{ row: ComparisonRow }>();
</script>

<template>
    <tr class="align-top">
        <td class="w-28 p-3 font-medium">
            {{ row.a?.label ?? (row.a ? 'Pembukaan' : '-') }}
        </td>
        <td class="p-3 text-sm">
            <DiffText v-if="row.diff?.a.length" :runs="row.diff.a" />
            <span v-else class="text-muted-foreground">-</span>
        </td>
        <td class="w-28 p-3 font-medium">
            {{ row.b?.label ?? (row.b ? 'Pembukaan' : '-') }}
        </td>
        <td class="p-3 text-sm">
            <DiffText v-if="row.diff?.b.length" :runs="row.diff.b" />
            <span v-else class="text-muted-foreground">-</span>
        </td>
        <td class="w-64 p-3 text-sm">
            <KindBadge :kind="row.kind.value" :label="row.kind.label" />
            <p v-if="row.explanation" class="mt-2">{{ row.explanation }}</p>
        </td>
    </tr>
</template>
