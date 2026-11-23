<script setup lang="ts">
import { provide, ref } from 'vue';
import { headerContextKey } from '@/components/documents/config/header/headerContext';
import HeaderPreview from '@/components/documents/config/header/HeaderPreview.vue';
import Panel from '@/components/documents/Panel.vue';
import type {
    BrandColor,
    HeaderFieldOption,
    HeaderPalette,
    HeaderPreset,
} from '@/types';

/**
 * The first look at the letterhead of a new document type: no table yet, only a choice of
 * starting points, each drawn as it will print. Nothing chosen means the company's default.
 */
const props = defineProps<{
    presets: HeaderPreset[];
    fields: HeaderFieldOption[];
    palette: HeaderPalette;
    brandColors: BrandColor[];
    hasCompanyLogo: boolean;
}>();
defineEmits<{ choose: [preset: HeaderPreset] }>();

provide(headerContextKey, {
    fields: props.fields,
    palette: props.palette,
    brandColors: ref(props.brandColors),
    hasCompanyLogo: props.hasCompanyLogo,
});
</script>

<template>
    <Panel title="Kop surat">
        <div class="flex flex-col gap-4">
            <p class="text-sm text-muted-foreground">
                Pilih titik awal kop surat. Setelah dipilih, seluruh isinya bisa
                diubah: teks, garis, logo, dan ukuran. Kalau tidak memilih,
                jenis dokumen ini memakai kop bawaan perusahaan.
            </p>
            <ul class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <li v-for="preset in presets" :key="preset.key" class="flex">
                    <button
                        type="button"
                        class="flex min-h-11 w-full flex-col gap-2 rounded-lg border p-3 text-left transition-colors hover:border-primary hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/70 focus-visible:outline-hidden"
                        :aria-label="`Mulai dari ${preset.name}`"
                        @click="$emit('choose', preset)"
                    >
                        <span
                            class="pointer-events-none block rounded-md border bg-card p-2"
                            inert
                            aria-hidden="true"
                        >
                            <HeaderPreview :layout="preset.layout" />
                        </span>
                        <span class="text-sm font-medium">{{
                            preset.name
                        }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            preset.description
                        }}</span>
                    </button>
                </li>
            </ul>
        </div>
    </Panel>
</template>
