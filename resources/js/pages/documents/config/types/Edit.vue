<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DocTypeConfigController from '@/actions/App/Http/Controllers/Documents/Config/DocTypeConfigController';
import DocTypeForm from '@/components/documents/config/types/DocTypeForm.vue';
import Heading from '@/components/Heading.vue';
import type {
    BrandColor,
    HeaderFieldOption,
    HeaderPalette,
    HeaderPreset,
    HeaderLayout,
    ChoiceOption,
    DocTypeForm as DocTypeData,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pengaturan dokumen',
                href: DocTypeConfigController.index(),
            },
        ],
    },
});

defineProps<{
    type: DocTypeData;
    role_options: ChoiceOption[];
    clause_options: ChoiceOption[];
    header_fields: HeaderFieldOption[];
    header_default: HeaderLayout;
    header_presets: HeaderPreset[];
    header_palette: HeaderPalette;
    brand_colors: BrandColor[];
    has_company_logo: boolean;
}>();
</script>

<template>
    <Head :title="`Ubah ${type.code}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-2 p-4 md:p-6">
        <Heading
            :title="`Ubah ${type.code}`"
            description="Perubahan berlaku untuk dokumen baru; dokumen yang sudah dibuat tidak berubah."
        />
        <DocTypeForm
            :action="DocTypeConfigController.update.form(type.id)"
            submit-label="Simpan perubahan"
            :type="type"
            :role-options="role_options"
            :clause-options="clause_options"
            :header-fields="header_fields"
            :header-default="header_default"
            :header-presets="header_presets"
            :header-palette="header_palette"
            :brand-colors="brand_colors"
            :has-company-logo="has_company_logo"
        />
    </div>
</template>
