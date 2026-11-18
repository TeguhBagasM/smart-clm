<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ClauseConfigController from '@/actions/App/Http/Controllers/Documents/Config/ClauseConfigController';
import ClauseForm from '@/components/documents/config/clauses/ClauseForm.vue';
import Heading from '@/components/Heading.vue';
import type { ChoiceOption, ClauseForm as ClauseData } from '@/types';

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

defineProps<{
    clause: ClauseData;
    type_options: ChoiceOption[];
    category_options: string[];
}>();
</script>

<template>
    <Head :title="`Ubah ${clause.title}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-2 p-4 md:p-6">
        <Heading
            title="Ubah klausul"
            description="Perubahan tidak mengubah dokumen yang sudah memakai klausul ini."
        />
        <ClauseForm
            :action="ClauseConfigController.update.form(clause.id)"
            submit-label="Simpan perubahan"
            :clause="clause"
            :type-options="type_options"
            :category-options="category_options"
        />
    </div>
</template>
