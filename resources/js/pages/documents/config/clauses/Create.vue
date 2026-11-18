<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ClauseConfigController from '@/actions/App/Http/Controllers/Documents/Config/ClauseConfigController';
import ClauseForm from '@/components/documents/config/clauses/ClauseForm.vue';
import Heading from '@/components/Heading.vue';
import type { ChoiceOption } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pengaturan dokumen',
                href: ClauseConfigController.index(),
            },
            { title: 'Tambah klausul', href: ClauseConfigController.create() },
        ],
    },
});

defineProps<{ type_options: ChoiceOption[]; category_options: string[] }>();
</script>

<template>
    <Head title="Tambah klausul" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-2 p-4 md:p-6">
        <Heading
            title="Tambah klausul"
            description="Teks baku yang bisa dipakai ulang di banyak dokumen."
        />
        <ClauseForm
            :action="ClauseConfigController.store.form()"
            submit-label="Simpan klausul"
            :type-options="type_options"
            :category-options="category_options"
        />
    </div>
</template>
