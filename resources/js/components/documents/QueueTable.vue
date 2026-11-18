<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import VersionStatusBadge from '@/components/documents/VersionStatusBadge.vue';
import DataTable from '@/components/list/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    daysSince,
    formatDate,
    versionLabel,
    waitingLabel,
} from '@/lib/format';
import type { ListColumn, ListMeta, QueueItem } from '@/types';

defineProps<{ rows: QueueItem[]; list: ListMeta; action: string }>();

const columns: ListColumn[] = [
    { key: 'document', label: 'Dokumen', sort: 'number' },
    { key: 'version', label: 'Versi' },
    { key: 'detail', label: 'Keterangan' },
    { key: 'waiting', label: 'Menunggu', sort: 'waiting' },
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
        <template #cell-document="{ row }">
            <Link
                :href="
                    DocumentController.show(
                        { document: row.document.id },
                        { query: { version: row.id } },
                    )
                "
                class="font-medium underline-offset-4 hover:underline"
            >
                {{ row.document.title }}
            </Link>
            <div class="text-muted-foreground">
                <span class="font-mono text-xs">{{ row.document.number }}</span>
                · {{ row.document.type }} · {{ row.document.department }}
            </div>
            <div class="text-xs text-muted-foreground">
                Pemilik: {{ row.document.owner }}
            </div>
        </template>
        <template #cell-version="{ row }">
            <div class="flex flex-col gap-1">
                <VersionStatusBadge
                    :status="row.status"
                    :label="row.status_label"
                />
                <span class="text-xs text-muted-foreground">
                    {{ versionLabel(row) }}
                </span>
            </div>
        </template>
        <template #cell-detail="{ row }">
            <span :class="row.urgent && 'font-medium text-destructive'">
                {{ row.detail || '—' }}
            </span>
        </template>
        <template #cell-waiting="{ row }">
            <div class="flex items-center gap-2">
                <span>{{ waitingLabel(row.waiting_since) }}</span>
                <Badge
                    v-if="daysSince(row.waiting_since) > 7"
                    variant="destructive"
                >
                    Lama
                </Badge>
            </div>
            <div class="text-xs text-muted-foreground">
                sejak {{ formatDate(row.waiting_since) }}
            </div>
        </template>
        <template #cell-open="{ row }">
            <Button as-child size="sm" class="w-full md:w-auto">
                <Link
                    :href="
                        DocumentController.show(
                            { document: row.document.id },
                            { query: { version: row.id } },
                        )
                    "
                >
                    {{ action }}
                </Link>
            </Button>
        </template>
    </DataTable>
</template>
