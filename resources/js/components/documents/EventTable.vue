<script setup lang="ts">
import { ArrowRight } from '@lucide/vue';
import DataTable from '@/components/list/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { formatDateTime, versionLabel } from '@/lib/format';
import type { EventRow, ListColumn, ListMeta } from '@/types';

defineProps<{ rows: EventRow[]; list: ListMeta }>();

const columns: ListColumn[] = [
    { key: 'occurred_at', label: 'Waktu', sort: 'occurred_at' },
    { key: 'event', label: 'Kejadian', sort: 'type' },
    { key: 'version', label: 'Versi' },
    { key: 'actor', label: 'Pelaku' },
    { key: 'status', label: 'Perubahan status' },
];

const actorKinds = { user: '', guest: 'Tamu eksternal', system: 'Sistem' };
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="rows"
        :row-key="(row) => String(row.id)"
        :list="list"
    >
        <template #cell-occurred_at="{ row }">
            <span class="whitespace-nowrap">
                {{ formatDateTime(row.occurred_at) }}
            </span>
        </template>
        <template #cell-event="{ row }">
            <div class="font-medium">
                {{ row.label }}
                <Badge v-if="row.imported" variant="outline">Diimpor</Badge>
            </div>
            <div v-if="row.summary" class="text-muted-foreground">
                {{ row.summary }}
            </div>
        </template>
        <template #cell-version="{ row }">
            <span v-if="row.version">{{ versionLabel(row.version) }}</span>
            <span v-else class="text-muted-foreground">—</span>
        </template>
        <template #cell-actor="{ row }">
            {{ row.actor ?? 'Sistem' }}
            <span
                v-if="actorKinds[row.actor_type] && row.actor"
                class="text-xs text-muted-foreground"
            >
                ({{ actorKinds[row.actor_type] }})
            </span>
        </template>
        <template #cell-status="{ row }">
            <span
                v-if="row.to_status"
                class="inline-flex items-center gap-1 text-muted-foreground"
            >
                {{ row.from_status ?? '—' }}
                <ArrowRight class="size-3" />
                <span class="text-foreground">{{ row.to_status }}</span>
            </span>
            <span v-else class="text-muted-foreground">—</span>
        </template>
    </DataTable>
</template>
