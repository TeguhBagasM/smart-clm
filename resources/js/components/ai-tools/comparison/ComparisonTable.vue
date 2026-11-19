<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DocumentComparisonController from '@/actions/App/Http/Controllers/AiTools/DocumentComparisonController';
import ComparisonStatusBadge from '@/components/ai-tools/comparison/ComparisonStatusBadge.vue';
import DataTable from '@/components/list/DataTable.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import type { ComparisonListItem, ListColumn, ListMeta } from '@/types';

defineProps<{ rows: ComparisonListItem[]; list: ListMeta }>();

const columns: ListColumn[] = [
    { key: 'documents', label: 'Dokumen' },
    { key: 'status', label: 'Status', sort: 'status' },
    { key: 'created_at', label: 'Dibuat', sort: 'created_at' },
    { key: 'open', label: '', class: 'text-right' },
];
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="rows"
        :row-key="(row) => row.id"
        :list="list"
    >
        <template #cell-documents="{ row }">
            <Link
                :href="
                    DocumentComparisonController.show({ comparison: row.id })
                "
                class="font-medium underline-offset-4 hover:underline"
            >
                {{ row.label_a }}
            </Link>
            <div class="text-muted-foreground">dibanding {{ row.label_b }}</div>
        </template>
        <template #cell-status="{ row }">
            <ComparisonStatusBadge
                :status="row.status.value"
                :label="row.status.label"
            />
        </template>
        <template #cell-created_at="{ row }">
            {{ formatDateTime(row.created_at) }}
        </template>
        <template #cell-open="{ row }">
            <Button as-child size="sm" class="w-full md:w-auto">
                <Link
                    :href="
                        DocumentComparisonController.show({
                            comparison: row.id,
                        })
                    "
                >
                    Buka
                </Link>
            </Button>
        </template>
    </DataTable>
</template>
