<script setup lang="ts">
import DataTable from '@/components/list/DataTable.vue';
import RowActions from '@/components/master-data/RowActions.vue';
import StatusBadge from '@/components/master-data/StatusBadge.vue';
import FieldSheet from '@/components/contract-library/fields/FieldSheet.vue';
import { placeholderText } from '@/lib/contractLibrary';
import type {
    LibraryFieldRow,
    LibraryPageInfo,
    ListColumn,
    ListMeta,
} from '@/types';

/** The fields of the library as a table, and as cards below md. */
const props = defineProps<{
    rows: LibraryFieldRow[];
    list: ListMeta;
    info: LibraryPageInfo;
    busy: number | null;
}>();

defineEmits<{ toggle: [row: LibraryFieldRow] }>();

const columns = (): ListColumn[] => [
    ...props.info.columns,
    { key: 'actions', label: '', class: 'text-right' },
];

/** The source label of a field, or the server's blank text. */
function source(row: LibraryFieldRow): string {
    const match = props.info.sources?.find(
        (item) => item.value === row.source_field,
    );

    return match?.label ?? '—';
}
</script>

<template>
    <DataTable
        :columns="columns()"
        :rows="rows"
        :row-key="(row) => String(row.id)"
        :list="list"
    >
        <template #cell-code="{ row }">
            <code class="rounded bg-muted px-1.5 py-0.5 text-sm">{{
                placeholderText(row.code)
            }}</code>
        </template>
        <template #cell-name="{ row }">
            <span class="font-medium">{{ row.name }}</span>
        </template>
        <template #cell-type="{ row }">{{ row.value_type.label }}</template>
        <template #cell-source="{ row }">{{ source(row) }}</template>
        <template #cell-status="{ row }">
            <StatusBadge :active="row.is_active" />
        </template>
        <template #cell-usage="{ row }">{{ row.usage_count }}</template>
        <template #cell-actions="{ row }">
            <RowActions
                :active="row.is_active"
                :can-toggle="row.can.deactivate"
                :busy="busy === row.id"
                @toggle="$emit('toggle', row)"
            >
                <FieldSheet v-if="row.can.update" :info="info" :row="row" />
            </RowActions>
        </template>
    </DataTable>
</template>
