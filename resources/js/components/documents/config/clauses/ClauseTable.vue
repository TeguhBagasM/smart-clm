<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import ClauseConfigController from '@/actions/App/Http/Controllers/Documents/Config/ClauseConfigController';
import ActiveStatusBadge from '@/components/ActiveStatusBadge.vue';
import ToggleActive from '@/components/documents/config/ToggleActive.vue';
import DataTable from '@/components/list/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ClauseRow, ListColumn, ListMeta } from '@/types';

defineProps<{ rows: ClauseRow[]; list: ListMeta }>();

const columns: ListColumn[] = [
    { key: 'title', label: 'Klausul', sort: 'title' },
    { key: 'category', label: 'Kategori', sort: 'category' },
    { key: 'types', label: 'Berlaku untuk' },
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
        <template #cell-title="{ row }">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium">{{ row.title }}</span>
                <Badge v-if="row.is_mandatory" variant="default">Wajib</Badge>
            </div>
            <div class="line-clamp-2 text-muted-foreground">
                {{ row.excerpt }}
            </div>
            <div v-if="row.tags.length" class="mt-1 flex flex-wrap gap-1">
                <Badge v-for="tag in row.tags" :key="tag" variant="outline">
                    {{ tag }}
                </Badge>
            </div>
        </template>
        <template #cell-types="{ row }">
            <span v-if="row.types.length === 0" class="text-muted-foreground">
                Semua jenis dokumen
            </span>
            <div v-else class="flex flex-wrap gap-1">
                <Badge
                    v-for="code in row.types"
                    :key="code"
                    variant="secondary"
                    class="font-mono"
                >
                    {{ code }}
                </Badge>
            </div>
        </template>
        <template #cell-status="{ row }">
            <ActiveStatusBadge :active="row.is_active" />
        </template>
        <template #cell-actions="{ row }">
            <div class="flex flex-wrap justify-end gap-1">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="ClauseConfigController.edit(row.id)">
                        <Pencil /> Ubah
                    </Link>
                </Button>
                <ToggleActive
                    :active="row.is_active"
                    :name="row.title"
                    :deactivate="ClauseConfigController.destroy.form(row.id)"
                    :restore="ClauseConfigController.restore.form(row.id)"
                />
            </div>
        </template>
    </DataTable>
</template>
