<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import DocumentCreateController from '@/actions/App/Http/Controllers/Documents/DocumentCreateController';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import DocumentTable from '@/components/documents/DocumentTable.vue';
import Heading from '@/components/Heading.vue';
import ListPage from '@/components/list/ListPage.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import type { DocumentRow, ListMeta, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dokumen Terkontrol', href: DocumentController.index() },
        ],
    },
});

const { can } = usePermissions();

defineProps<{
    documents: Paginated<DocumentRow>;
    list: ListMeta;
}>();
</script>

<template>
    <Head title="Dokumen Terkontrol" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-wrap items-start justify-between gap-3 [&>header]:mb-0"
        >
            <Heading
                title="Dokumen Terkontrol"
                description="SOP, instruksi kerja, kebijakan, dan memo: cari, saring, dan urutkan seluruh dokumen perusahaan."
            />
            <Button v-if="can('dcs.edit')" as-child>
                <Link :href="DocumentCreateController.create()">
                    <Plus /> Dokumen baru
                </Link>
            </Button>
        </div>

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
