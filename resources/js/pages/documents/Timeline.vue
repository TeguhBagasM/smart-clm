<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import DocumentHeader from '@/components/documents/DocumentHeader.vue';
import EventTable from '@/components/documents/EventTable.vue';
import ListPage from '@/components/list/ListPage.vue';
import type { DocumentDetail, EventRow, ListMeta, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dokumen Terkontrol', href: DocumentController.index() },
        ],
    },
});

defineProps<{
    document: DocumentDetail;
    events: Paginated<EventRow>;
    list: ListMeta;
}>();
</script>

<template>
    <Head :title="`Riwayat ${document.number}`" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <DocumentHeader :document="document" />

        <h2 class="text-lg font-semibold">Riwayat dokumen</h2>
        <ListPage
            :list="list"
            :paginated="events"
            :only="['events', 'list']"
            search-placeholder="Cari nama pelaku..."
            empty-text="Belum ada riwayat."
        >
            <EventTable :rows="events.data" :list="list" />
        </ListPage>
    </div>
</template>
