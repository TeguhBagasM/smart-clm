<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DocumentController from '@/actions/App/Http/Controllers/Documents/DocumentController';
import DocumentCreateController from '@/actions/App/Http/Controllers/Documents/DocumentCreateController';
import CreateDocumentForm from '@/components/documents/create/CreateDocumentForm.vue';
import Heading from '@/components/Heading.vue';
import type { ChoiceOption } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dokumen Terkontrol', href: DocumentController.index() },
            { title: 'Dokumen baru', href: DocumentCreateController.create() },
        ],
    },
});

defineProps<{
    types: { code: string; name: string; description: string | null }[];
    departments: ChoiceOption[];
    owners: ChoiceOption[];
    me: string;
}>();
</script>

<template>
    <Head title="Dokumen baru" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-2 p-4 md:p-6">
        <Heading
            title="Dokumen baru"
            description="Nomor dokumen diberikan otomatis. Setelah disimpan, Anda melanjutkan menyusun isinya."
        />
        <CreateDocumentForm
            :types="types"
            :departments="departments"
            :owners="owners"
            :me="me"
        />
    </div>
</template>
