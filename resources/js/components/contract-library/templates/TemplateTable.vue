<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Copy, ExternalLink } from '@lucide/vue';
import DataTable from '@/components/list/DataTable.vue';
import RowActions from '@/components/master-data/RowActions.vue';
import StatusBadge from '@/components/master-data/StatusBadge.vue';
import TemplateSheet from '@/components/contract-library/templates/TemplateSheet.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import TemplateController from '@/actions/App/Http/Controllers/Templates/TemplateController';
import type {
    LibraryPageInfo,
    LibraryTemplateRow,
    ListColumn,
    ListMeta,
} from '@/types';

/** Templates as a table, and as cards below md. Opening, copying and hiding are here too. */
const props = defineProps<{
    rows: LibraryTemplateRow[];
    list: ListMeta;
    info: LibraryPageInfo;
    categories: { value: string; label: string }[];
    busy: number | null;
}>();

defineEmits<{
    toggle: [row: LibraryTemplateRow];
    copy: [row: LibraryTemplateRow];
}>();

const columns = (): ListColumn[] => [
    ...props.info.columns,
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
        <template #cell-name="{ row }">
            <span class="font-medium">{{ row.name }}</span>
        </template>
        <template #cell-category="{ row }">{{
            row.category?.name ?? '—'
        }}</template>
        <template #cell-module="{ row }">{{ row.module.label }}</template>
        <template #cell-published="{ row }">
            <Badge v-if="row.published_version" variant="secondary"
                >Versi {{ row.published_version.version_no }}</Badge
            >
            <span v-else class="text-muted-foreground">Belum terbit</span>
        </template>
        <template #cell-draft="{ row }">
            <span v-if="row.draft">Draf {{ row.draft.version_no }}</span>
            <span v-else class="text-muted-foreground">Tidak ada</span>
        </template>
        <template #cell-status="{ row }"
            ><StatusBadge :active="row.is_active"
        /></template>
        <template #cell-actions="{ row }">
            <RowActions
                :active="row.is_active"
                :can-toggle="row.can.deactivate"
                :busy="busy === row.id"
                @toggle="$emit('toggle', row)"
            >
                <Button
                    as-child
                    variant="ghost"
                    class="h-11 w-full md:h-9 md:w-auto"
                >
                    <Link :href="TemplateController.show(row.id).url"
                        ><ExternalLink />Buka</Link
                    >
                </Button>
                <TemplateSheet
                    v-if="row.can.update"
                    :info="info"
                    :categories="categories"
                    :row="row"
                />
                <Button
                    v-if="row.can.duplicate"
                    variant="ghost"
                    class="h-11 w-full md:h-9 md:w-auto"
                    :disabled="busy === row.id"
                    @click="$emit('copy', row)"
                >
                    <Copy />Salin
                </Button>
            </RowActions>
        </template>
    </DataTable>
</template>
