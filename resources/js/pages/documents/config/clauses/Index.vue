<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import ClauseConfigController from '@/actions/App/Http/Controllers/Documents/Config/ClauseConfigController';
import ClauseTable from '@/components/documents/config/clauses/ClauseTable.vue';
import ConfigHeader from '@/components/documents/config/ConfigHeader.vue';
import ListPage from '@/components/list/ListPage.vue';
import { Button } from '@/components/ui/button';
import type { ClauseRow, ListMeta, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pengaturan dokumen',
                href: ClauseConfigController.index(),
            },
        ],
    },
});

defineProps<{ clauses: Paginated<ClauseRow>; list: ListMeta }>();
</script>

<template>
    <Head title="Klausul" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <ConfigHeader
            active="clauses"
            title="Pengaturan dokumen"
            description="Pustaka klausul: teks baku yang bisa disertakan pada dokumen."
        >
            <template #action>
                <Button as-child>
                    <Link :href="ClauseConfigController.create()">
                        <Plus /> Tambah klausul
                    </Link>
                </Button>
            </template>
        </ConfigHeader>

        <ListPage
            :list="list"
            :paginated="clauses"
            :only="['clauses', 'list']"
            search-placeholder="Cari judul, isi, atau kategori klausul..."
            empty-text="Belum ada klausul."
        >
            <ClauseTable :rows="clauses.data" :list="list" />
        </ListPage>
    </div>
</template>
