<script setup lang="ts">
import ChoiceButtons from '@/components/ChoiceButtons.vue';
import { useWizard } from '@/composables/useDraftWizard';

const { state, touch } = useWizard();
</script>

<template>
    <fieldset class="flex flex-col gap-1.5">
        <legend class="mb-1 text-sm font-medium">Bahasa dokumen</legend>
        <ChoiceButtons
            :model-value="state.language_mode"
            label="Bahasa dokumen"
            :options="[
                ['single', 'Bahasa Indonesia saja'],
                ['multi', 'Indonesia + Inggris'],
            ]"
            @update:model-value="
                state.language_mode = $event as 'single' | 'multi';
                touch('language');
            "
        />
        <p class="text-xs text-muted-foreground">
            Menandai dokumen sebagai dwibahasa. Salinan terjemahan Inggris
            dibuat terpisah sebagai referensi.
        </p>
    </fieldset>
</template>
