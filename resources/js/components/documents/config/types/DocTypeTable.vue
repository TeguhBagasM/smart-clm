<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import DocTypeConfigController from '@/actions/App/Http/Controllers/Documents/Config/DocTypeConfigController';
import ActiveStatusBadge from '@/components/ActiveStatusBadge.vue';
import ToggleActive from '@/components/documents/config/ToggleActive.vue';
import DataTable from '@/components/list/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { DocTypeRow, ListColumn, ListMeta } from '@/types';

defineProps<{ rows: DocTypeRow[]; list: ListMeta }>();

const columns: ListColumn[] = [
    { key: 'code', label: 'Jenis dokumen', sort: 'code' },
    { key: 'structure', label: 'Struktur' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '', class: 'text-right' },
];

const layouts = { standard: 'Standar', memo: 'Memo', kebijakan: 'Kebijakan' };
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="rows"
        :row-key="(row) => row.id"
        :list="list"
    >
        <template #cell-code="{ row }">
            <div class="flex items-center gap-2">
                <Badge variant="outline" class="font-mono">{{
                    row.code
                }}</Badge>
                <span class="font-medium">{{ row.name }}</span>
            </div>
            <div
                v-if="row.description"
                class="line-clamp-1 text-muted-foreground"
            >
                {{ row.description }}
            </div>
        </template>
        <template #cell-structure="{ row }">
            <div class="text-muted-foreground">
                {{ row.sections_count }} bagian ·
                {{ row.columns_count }} penandatangan ·
                {{ row.clauses_count }} klausul
            </div>
            <div class="text-xs text-muted-foreground">
                {{ layouts[row.layout] }} ·
                {{ row.header === 'iso' ? 'Kop ISO' : 'Kop sederhana'
                }}<template v-if="row.letterhead"> · Kop surat</template> ·
                {{ row.custom_numbering ? 'Nomor khusus' : 'Nomor default' }}
            </div>
        </template>
        <template #cell-status="{ row }">
            <ActiveStatusBadge :active="row.is_active" />
        </template>
        <template #cell-actions="{ row }">
            <div class="flex flex-wrap justify-end gap-1">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="DocTypeConfigController.edit(row.id)">
                        <Pencil /> Ubah
                    </Link>
                </Button>
                <ToggleActive
                    :active="row.is_active"
                    :name="row.code"
                    :deactivate="DocTypeConfigController.destroy.form(row.id)"
                    :restore="DocTypeConfigController.restore.form(row.id)"
                />
            </div>
        </template>
    </DataTable>
</template>
