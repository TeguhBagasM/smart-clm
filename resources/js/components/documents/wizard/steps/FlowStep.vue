<script setup lang="ts">
import FlowBuilder from '@/components/documents/wizard/flow/FlowBuilder.vue';
import { Input } from '@/components/ui/input';
import { useWizard } from '@/composables/useDraftWizard';
import type { DraftFlow } from '@/types';

const { state, touch } = useWizard();

const modes: { value: DraftFlow['mode']; label: string; hint: string }[] = [
    {
        value: 'none',
        label: 'Tanpa diagram',
        hint: 'Dokumen tidak memuat diagram alir.',
    },
    {
        value: 'builder',
        label: 'Susun di sini',
        hint: 'Buat langkah dan lihat diagramnya langsung.',
    },
    {
        value: 'image',
        label: 'Unggah gambar',
        hint: 'Pakai gambar diagram yang sudah ada.',
    },
];

function setMode(mode: DraftFlow['mode']): void {
    state.flow = { ...state.flow, mode };
    touch('flow');
}

function pickImage(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) return;

    state.flowImage = file;
    state.flow = { ...state.flow, has_image: true };
    touch('flow');
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <div
            role="radiogroup"
            aria-label="Cara membuat diagram"
            class="grid gap-2 sm:grid-cols-3"
        >
            <button
                v-for="mode in modes"
                :key="mode.value"
                type="button"
                role="radio"
                :aria-checked="state.flow.mode === mode.value"
                class="rounded-lg border p-3 text-left transition-colors hover:bg-accent aria-checked:border-primary aria-checked:bg-accent"
                @click="setMode(mode.value)"
            >
                <span class="block text-sm font-medium">{{ mode.label }}</span>
                <span class="text-xs text-muted-foreground">{{
                    mode.hint
                }}</span>
            </button>
        </div>

        <div v-if="state.flow.mode === 'image'" class="flex flex-col gap-2">
            <label class="text-sm font-medium" for="flow-image"
                >Gambar diagram (PNG atau JPG, maks. 10 MB)</label
            >
            <Input
                id="flow-image"
                type="file"
                accept="image/png,image/jpeg"
                @change="pickImage"
            />
            <p
                v-if="state.flow.has_image"
                class="text-sm text-muted-foreground"
            >
                {{
                    state.flowImage
                        ? `Akan disimpan: ${state.flowImage.name}`
                        : 'Gambar sudah tersimpan; pilih berkas baru untuk menggantinya.'
                }}
            </p>
        </div>

        <FlowBuilder v-if="state.flow.mode === 'builder'" />
    </div>
</template>
