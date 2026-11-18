<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DocTypeConfigController from '@/actions/App/Http/Controllers/Documents/Config/DocTypeConfigController';
import DocTypeForm from '@/components/documents/config/types/DocTypeForm.vue';
import Heading from '@/components/Heading.vue';
import type { HeaderFieldOption, HeaderLayout, ChoiceOption } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pengaturan dokumen',
                href: DocTypeConfigController.index(),
            },
            {
                title: 'Tambah jenis dokumen',
                href: DocTypeConfigController.create(),
            },
        ],
    },
});

defineProps<{
    role_options: ChoiceOption[];
    clause_options: ChoiceOption[];
    header_fields: HeaderFieldOption[];
    header_default: HeaderLayout;
}>();
</script>

<template>
    <Head title="Tambah jenis dokumen" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-2 p-4 md:p-6">
        <Heading
            title="Tambah jenis dokumen"
            description="Tentukan kode, kerangka isi, dan siapa yang menandatangani."
        />
        <DocTypeForm
            :action="DocTypeConfigController.store.form()"
            submit-label="Simpan jenis dokumen"
            :role-options="role_options"
            :clause-options="clause_options"
            :header-fields="header_fields"
            :header-default="header_default"
        />
    </div>
</template>
