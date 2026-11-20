<script setup lang="ts">
import MasterRecordSheet from '@/components/master-data/MasterRecordSheet.vue';
import RowActions from '@/components/master-data/RowActions.vue';
import StatusBadge from '@/components/master-data/StatusBadge.vue';
import DataTable from '@/components/list/DataTable.vue';
import { cellText, tableColumns } from '@/lib/masterData';
import type {
    ListColumn,
    ListMeta,
    MasterFormOptions,
    MasterInfo,
    MasterRow,
} from '@/types';

/**
 * The rows of a list. Below `md` the DataTable draws cards. Columns and their
 * labels come from the server; status, usage and actions are added here.
 */
const props = defineProps<{
    rows: MasterRow[];
    list: ListMeta;
    info: MasterInfo;
    options: MasterFormOptions;
    companyId: number | null;
    busy: number | null;
}>();

defineEmits<{ toggle: [row: MasterRow] }>();

const columns = (): ListColumn[] => [
    ...tableColumns(props.info, props.list),
    { key: 'status', label: 'Status' },
    { key: 'usage', label: 'Dipakai oleh' },
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
        <template
            v-for="column in info.columns"
            :key="column.key"
            #[`cell-${column.key}`]="{ row }"
        >
            <span :class="column.key === 'name' ? 'font-medium' : ''">
                {{ cellText(row, column.key, info.empty) }}
            </span>
        </template>
        <template #cell-status="{ row }">
            <StatusBadge :active="row.is_active" :system="row.is_system" />
        </template>
        <template #cell-usage="{ row }">
            <span class="text-muted-foreground"
                >{{ row.usage_count }} data</span
            >
        </template>
        <template #cell-actions="{ row }">
            <RowActions
                :active="row.is_active"
                :can-toggle="row.can.deactivate"
                :busy="busy === row.id"
                @toggle="$emit('toggle', row)"
            >
                <MasterRecordSheet
                    v-if="row.can.update"
                    :info="info"
                    :options="options"
                    :row="row"
                    :company-id="companyId"
                />
            </RowActions>
        </template>
    </DataTable>
</template>
