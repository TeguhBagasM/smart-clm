<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { autoTone, contrastRatio, parseColor } from '@/lib/headerGeometry';
import type { HeaderPalette } from '@/types';

const props = defineProps<{
    text: string;
    background: string | null;
    palette: HeaderPalette;
}>();

const ratio = computed(() => {
    const fg = parseColor(
        props.text === 'auto'
            ? autoTone(props.background) === 'light'
                ? props.palette.light
                : props.palette.dark
            : props.text,
    );
    const bg = parseColor(props.background ?? props.palette.light);

    return fg && bg ? contrastRatio(fg, bg) : null;
});
</script>

<template>
    <p
        v-if="ratio !== null && ratio < 4.5"
        class="flex items-start gap-1.5 text-xs text-warning-foreground"
        role="status"
    >
        <TriangleAlert class="mt-0.5 size-3.5 shrink-0" />
        Kontras teks dan latar {{ ratio.toFixed(1) }}:1, di bawah 4,5:1. Teks
        bisa sulit dibaca. Tetap boleh disimpan.
    </p>
</template>
