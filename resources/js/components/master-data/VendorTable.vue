<script setup lang="ts">
import DataTable from '@/components/list/DataTable.vue';
import RowActions from '@/components/master-data/RowActions.vue';
import StatusBadge from '@/components/master-data/StatusBadge.vue';
import VendorSheet from '@/components/master-data/VendorSheet.vue';
import { tableColumns } from '@/lib/masterData';
import { vendorCell } from '@/lib/vendors';
import type { ListColumn, ListMeta, VendorInfo, VendorRow } from '@/types';

/** Vendors as a table from md up and as cards on a phone (DataTable). */
const props = defineProps<{
    rows: VendorRow[];
    list: ListMeta;
    info: VendorInfo;
    companyId: number | null;
    busy: number | null;
}>();

defineEmits<{ toggle: [row: VendorRow] }>();

const columns = (): ListColumn[] => [
    // Long names and contacts wrap and the status sits under the name, so the
    // actions stay in view on a tablet.
    ...tableColumns(props.info, props.list).map((column) => ({
        ...column,
        class: column.key === 'npwp' ? undefined : 'min-w-24 whitespace-normal',
    })),
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
            <span
                :class="[
                    'flex flex-col items-start gap-1.5',
                    column.key === 'name' && 'font-medium',
                    vendorCell(row, column.key, info) ===
                        info.blank[column.key] && 'text-muted-foreground',
                ]"
            >
                {{ vendorCell(row, column.key, info) }}
                <StatusBadge
                    v-if="column.key === 'name'"
                    :active="row.is_active"
                    class="font-normal"
                />
            </span>
        </template>
        <template #cell-actions="{ row }">
            <RowActions
                :active="row.is_active"
                :can-toggle="row.can.deactivate"
                :busy="busy === row.id"
                @toggle="$emit('toggle', row)"
            >
                <VendorSheet
                    v-if="row.can.update"
                    :info="info"
                    :row="row"
                    :company-id="companyId"
                />
            </RowActions>
        </template>
    </DataTable>
</template>
