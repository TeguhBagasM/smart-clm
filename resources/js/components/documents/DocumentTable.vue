<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MessageSquare } from '@lucide/vue';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import VersionStatusBadge from '@/components/documents/VersionStatusBadge.vue';
import DataTable from '@/components/list/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { formatDate, versionLabel } from '@/lib/format';
import type { DocumentRow, ListColumn, ListMeta } from '@/types';

defineProps<{ rows: DocumentRow[]; list: ListMeta }>();

const columns: ListColumn[] = [
    { key: 'number', label: 'Nomor', sort: 'number' },
    { key: 'title', label: 'Judul', sort: 'title' },
    { key: 'status', label: 'Status' },
    { key: 'effective', label: 'Berlaku', sort: 'effective_at' },
    { key: 'review', label: 'Tinjau ulang', sort: 'review_due_at' },
    { key: 'updated', label: 'Diubah', sort: 'updated_at' },
];
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="rows"
        :row-key="(row) => row.id"
        :list="list"
    >
        <template #cell-number="{ row }">
            <Link
                :href="DocumentController.show({ document: row.id })"
                class="font-mono text-xs underline-offset-4 hover:underline"
            >
                {{ row.number }}
            </Link>
        </template>
        <template #cell-title="{ row }">
            <Link
                :href="DocumentController.show({ document: row.id })"
                class="font-medium underline-offset-4 hover:underline"
            >
                {{ row.title }}
            </Link>
            <div class="text-muted-foreground">
                {{ row.type.code }} · {{ row.department }} · {{ row.owner }}
            </div>
        </template>
        <template #cell-status="{ row }">
            <div v-if="row.latest_version" class="flex flex-col gap-1">
                <VersionStatusBadge
                    :status="row.latest_version.status"
                    :label="row.latest_version.status_label"
                />
                <span class="text-xs text-muted-foreground">
                    {{ versionLabel(row.latest_version) }}
                </span>
            </div>
            <span
                v-if="row.open_comments > 0"
                class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground"
            >
                <MessageSquare class="size-3" />
                {{ row.open_comments }} komentar terbuka
            </span>
        </template>
        <template #cell-effective="{ row }">
            <template v-if="row.current_version">
                <div>{{ formatDate(row.current_version.effective_at) }}</div>
                <div class="text-xs text-muted-foreground">
                    {{ versionLabel(row.current_version) }}
                </div>
            </template>
            <span v-else class="text-muted-foreground">Belum berlaku</span>
        </template>
        <template #cell-review="{ row }">
            <template v-if="row.current_version?.review_due_at">
                {{ formatDate(row.current_version.review_due_at) }}
                <Badge v-if="row.review_overdue" variant="destructive">
                    Terlambat
                </Badge>
            </template>
            <span v-else class="text-muted-foreground">—</span>
        </template>
        <template #cell-updated="{ row }">
            <span class="text-muted-foreground">
                {{ formatDate(row.updated_at) }}
            </span>
        </template>
    </DataTable>
</template>
