<script setup lang="ts">
import { Undo2 } from '@lucide/vue';
import NumberingConfigController from '@/actions/App/Http/Controllers/Documents/Config/NumberingConfigController';
import ActionDialog from '@/components/documents/actions/ActionDialog.vue';
import MaskDialog from '@/components/documents/config/numbering/MaskDialog.vue';
import DataTable from '@/components/list/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import type { ListColumn, ListMeta, NumberingRow } from '@/types';

defineProps<{
    rows: NumberingRow[];
    list: ListMeta;
    tokens: string[];
    suggested?: string;
}>();

const columns: ListColumn[] = [
    { key: 'code', label: 'Jenis dokumen', sort: 'code' },
    { key: 'mask', label: 'Format' },
    { key: 'example', label: 'Contoh' },
    { key: 'source', label: 'Sumber' },
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
            <span class="ml-2 font-medium">{{ row.name }}</span>
        </template>
        <template #cell-mask="{ row }">
            <code class="text-xs">{{ row.mask }}</code>
        </template>
        <template #cell-example="{ row }">
            <span class="font-mono text-xs">{{ row.example }}</span>
        </template>
        <template #cell-source="{ row }">
            <Badge :variant="row.custom ? 'default' : 'secondary'">
                {{ row.custom ? 'Format khusus' : 'Mengikuti default' }}
            </Badge>
        </template>
        <template #cell-actions="{ row }">
            <div class="flex flex-wrap justify-end gap-1">
                <MaskDialog
                    :mask="row.mask"
                    :tokens="tokens"
                    :type-id="row.id"
                    :type-code="row.code"
                    :suggested="suggested"
                />
                <ActionDialog
                    v-if="row.custom"
                    :form="NumberingConfigController.destroy.form(row.id)"
                    trigger-label="Kembali ke default"
                    :trigger-icon="Undo2"
                    trigger-variant="ghost"
                    :title="`Kembalikan ${row.code} ke format default?`"
                    description="Nomor berikutnya memakai format default perusahaan. Nomor yang sudah terbit tidak berubah."
                />
            </div>
        </template>
    </DataTable>
</template>
