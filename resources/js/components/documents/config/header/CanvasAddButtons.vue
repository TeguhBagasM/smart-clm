<script setup lang="ts">
import { Plus } from '@lucide/vue';

/**
 * The two "+" buttons of the cell in focus: a row below it (on the row's lower
 * left corner) and a column to its right (on the cell's right edge, at the row's top).
 */
defineProps<{
    rowLeft: string;
    rowBottom: string;
    cellRight: string;
    rowTop: string;
    canAddRow: boolean;
    canAddColumn: boolean;
}>();
defineEmits<{ addRow: []; addColumn: [] }>();

const button =
    'pointer-events-auto absolute z-20 flex size-6 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border bg-background text-muted-foreground shadow-sm hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/70 focus-visible:outline-hidden pointer-coarse:size-8';
</script>

<template>
    <button
        v-if="canAddRow"
        type="button"
        :class="button"
        :style="{ left: rowLeft, top: rowBottom }"
        title="Tambah baris di bawah"
        aria-label="Tambah baris di bawah"
        @click.stop="$emit('addRow')"
    >
        <Plus class="size-4" />
    </button>
    <button
        v-if="canAddColumn"
        type="button"
        :class="button"
        :style="{ left: cellRight, top: rowTop }"
        title="Tambah kolom di kanan"
        aria-label="Tambah kolom di kanan"
        @click.stop="$emit('addColumn')"
    >
        <Plus class="size-4" />
    </button>
</template>
