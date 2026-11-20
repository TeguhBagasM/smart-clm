<script setup lang="ts">
import DataTable from '@/components/list/DataTable.vue';
import EmployeeSheet from '@/components/master-data/EmployeeSheet.vue';
import RowActions from '@/components/master-data/RowActions.vue';
import StatusBadge from '@/components/master-data/StatusBadge.vue';
import { cellText, tableColumns } from '@/lib/masterData';
import type {
    EmployeeFormOptions,
    EmployeeRow,
    ListColumn,
    ListMeta,
    MasterInfo,
} from '@/types';

const props = defineProps<{
    rows: EmployeeRow[];
    list: ListMeta;
    info: MasterInfo;
    options: EmployeeFormOptions;
    companyId: number | null;
    busy: number | null;
}>();

defineEmits<{ toggle: [row: EmployeeRow] }>();

const text = (row: EmployeeRow, key: string): string =>
    cellText({ ...row }, key, props.info.empty);

const columns = (): ListColumn[] => [
    ...tableColumns(props.info, props.list),
    { key: 'status', label: 'Status' },
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
                {{ text(row, column.key) }}
            </span>
        </template>
        <template #cell-status="{ row }">
            <StatusBadge :active="row.is_active" />
        </template>
        <template #cell-actions="{ row }">
            <RowActions
                :active="row.is_active"
                :can-toggle="row.can.deactivate"
                :busy="busy === row.id"
                @toggle="$emit('toggle', row)"
            >
                <EmployeeSheet
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
