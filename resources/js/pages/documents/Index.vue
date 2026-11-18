<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import DocumentTable from '@/components/documents/DocumentTable.vue';
import Heading from '@/components/Heading.vue';
import ListPage from '@/components/list/ListPage.vue';
import type { DocumentRow, ListMeta, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dokumen Terkontrol', href: DocumentController.index() },
        ],
    },
});

defineProps<{
    documents: Paginated<DocumentRow>;
    list: ListMeta;
}>();
</script>

<template>
    <Head title="Dokumen Terkontrol" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Dokumen Terkontrol"
            description="SOP, instruksi kerja, kebijakan, dan memo: cari, saring, dan urutkan seluruh dokumen perusahaan."
        />

        <ListPage
            :list="list"
            :paginated="documents"
            :only="['documents', 'list']"
            search-placeholder="Cari nomor atau judul dokumen..."
            empty-text="Belum ada dokumen terkontrol."
        >
            <DocumentTable :rows="documents.data" :list="list" />
        </ListPage>
    </div>
</template>
