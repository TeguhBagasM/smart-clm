<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DocTypeConfigController from '@/actions/App/Http/Controllers/Documents/Config/DocTypeConfigController';
import DocTypeForm from '@/components/documents/config/types/DocTypeForm.vue';
import Heading from '@/components/Heading.vue';
import type {
    BrandColor,
    HeaderFieldOption,
    HeaderLayout,
    HeaderPalette,
    HeaderPreset,
    NumberFormat,
    SignerOption,
    ChoiceOption,
} from '@/types';

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
    signer_options: SignerOption[];
    number_format: NumberFormat;
    header_fields: HeaderFieldOption[];
    header_default: HeaderLayout;
    header_presets: HeaderPreset[];
    header_palette: HeaderPalette;
    brand_colors: BrandColor[];
    has_company_logo: boolean;
}>();
</script>

<template>
    <Head title="Tambah jenis dokumen" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-2 p-4 md:p-6">
        <Heading
            title="Tambah jenis dokumen"
            description="Tentukan kode, kerangka isi, dan siapa yang menandatangani."
        />
        <DocTypeForm
            :action="DocTypeConfigController.store.form()"
            submit-label="Simpan jenis dokumen"
            :role-options="role_options"
            :clause-options="clause_options"
            :signer-options="signer_options"
            :number-format="number_format"
            :header-fields="header_fields"
            :header-default="header_default"
            :header-presets="header_presets"
            :header-palette="header_palette"
            :brand-colors="brand_colors"
            :has-company-logo="has_company_logo"
        />
    </div>
</template>
