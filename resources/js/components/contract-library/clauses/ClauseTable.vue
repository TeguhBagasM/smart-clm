<script setup lang="ts">
import DataTable from '@/components/list/DataTable.vue';
import RowActions from '@/components/master-data/RowActions.vue';
import StatusBadge from '@/components/master-data/StatusBadge.vue';
import ClauseSheet from '@/components/contract-library/clauses/ClauseSheet.vue';
import { Badge } from '@/components/ui/badge';
import type {
    LibraryClauseRow,
    LibraryPageInfo,
    ListColumn,
    ListMeta,
} from '@/types';

/** The clause library as a table, and as cards below md (DataTable). */
const props = defineProps<{
    rows: LibraryClauseRow[];
    list: ListMeta;
    info: LibraryPageInfo;
    categories: { value: string; label: string }[];
    fieldCodes: string[];
    busy: number | null;
}>();

defineEmits<{ toggle: [row: LibraryClauseRow] }>();

const columns = (): ListColumn[] => [
    ...props.info.columns,
    { key: 'actions', label: '', class: 'text-right' },
];
</script>

<template>
    <DataTable
        :columns="columns()"
        :rows="rows"
        :row-key="(row) => String(row.id)"
        :list="list"
    >
        <template #cell-title="{ row }">
            <span class="font-medium">{{ row.title }}</span>
        </template>
        <template #cell-category="{ row }">
            {{ row.category?.name ?? '—' }}
        </template>
        <template #cell-kind="{ row }">
            <span class="flex flex-wrap gap-1.5">
                <Badge v-if="row.is_mandatory" variant="secondary"
                    >Wajib ada</Badge
                >
                <Badge v-if="row.is_protected" variant="outline"
                    >Terlindungi</Badge
                >
                <span
                    v-if="!row.is_mandatory && !row.is_protected"
                    class="text-muted-foreground"
                    >Biasa</span
                >
            </span>
        </template>
        <template #cell-revision="{ row }">Revisi {{ row.revision }}</template>
        <template #cell-status="{ row }">
            <StatusBadge :active="row.is_active" />
        </template>
        <template #cell-usage="{ row }">
            {{ row.usage_count }}
        </template>
        <template #cell-actions="{ row }">
            <RowActions
                :active="row.is_active"
                :can-toggle="row.can.deactivate"
                :busy="busy === row.id"
                @toggle="$emit('toggle', row)"
            >
                <ClauseSheet
                    v-if="row.can.update"
                    :info="info"
                    :categories="categories"
                    :field-codes="fieldCodes"
                    :row="row"
                />
            </RowActions>
        </template>
    </DataTable>
</template>
