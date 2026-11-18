<script setup lang="ts">
import { Power, RotateCcw } from '@lucide/vue';
import DepartmentConfigController from '@/actions/App/Http/Controllers/Documents/Config/DepartmentConfigController';
import ActiveStatusBadge from '@/components/ActiveStatusBadge.vue';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import DepartmentDialog from '@/components/documents/config/departments/DepartmentDialog.vue';
import DataTable from '@/components/list/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import type { DepartmentRow, ListColumn, ListMeta } from '@/types';

defineProps<{ rows: DepartmentRow[]; list: ListMeta }>();

const columns: ListColumn[] = [
    { key: 'code', label: 'Kode', sort: 'code' },
    { key: 'name', label: 'Nama', sort: 'name' },
    { key: 'documents', label: 'Dokumen' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '', class: 'text-right' },
];
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="rows"
        :row-key="(row) => row.id"
        :list="list"
    >
        <template #cell-code="{ row }">
            <Badge variant="outline" class="font-mono">{{ row.code }}</Badge>
        </template>
        <template #cell-name="{ row }">
            <span class="font-medium">{{ row.name }}</span>
        </template>
        <template #cell-documents="{ row }">
            <span class="text-muted-foreground">
                {{ row.documents_count }} dokumen
            </span>
        </template>
        <template #cell-status="{ row }">
            <ActiveStatusBadge :active="row.is_active" />
        </template>
        <template #cell-actions="{ row }">
            <div class="flex flex-wrap justify-end gap-1">
                <DepartmentDialog :department="row" />
                <ActionDialog
                    :form="DepartmentConfigController.update.form(row.id)"
                    :trigger-label="
                        row.is_active ? 'Nonaktifkan' : 'Aktifkan kembali'
                    "
                    :trigger-icon="row.is_active ? Power : RotateCcw"
                    trigger-variant="ghost"
                    :title="
                        row.is_active
                            ? `Nonaktifkan ${row.code}?`
                            : `Aktifkan kembali ${row.code}?`
                    "
                    :description="
                        row.is_active
                            ? 'Dokumen yang sudah ada tidak berubah; departemen ini tidak bisa dipilih untuk dokumen baru.'
                            : 'Bisa dipilih lagi untuk dokumen baru.'
                    "
                    :submit-variant="row.is_active ? 'destructive' : 'default'"
                >
                    <input
                        type="hidden"
                        name="isActive"
                        :value="row.is_active ? 0 : 1"
                    />
                    <input type="hidden" name="name" :value="row.name" />
                </ActionDialog>
            </div>
        </template>
    </DataTable>
</template>
