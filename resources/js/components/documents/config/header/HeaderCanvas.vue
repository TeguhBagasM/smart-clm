<script setup lang="ts">
import { computed, ref } from 'vue';
import CanvasHandles from '@/components/documents/config/header/CanvasHandles.vue';
import { dragging } from '@/components/documents/config/header/useHandleDrag';
import HeaderPreview from '@/components/documents/config/header/HeaderPreview.vue';
import type { Selection } from '@/components/documents/config/header/useHeaderEditor';
import type { Zoom } from '@/components/documents/config/header/ZoomControl.vue';
import type { HeaderLayout } from '@/types';

/**
 * The letterhead, drawn at the width of its card (never below 60% of its real
 * size: under that the canvas scrolls sideways inside its frame). The gutter
 * around it is for the handles, so nothing at the edge is clipped.
 */
const model = defineModel<HeaderLayout>('layout', { required: true });
const props = defineProps<{ selection: Selection; zoom: Zoom }>();
const emit = defineEmits<{
    select: [selection: Selection];
    addRow: [row: number];
    addColumn: [row: number, cell: number];
}>();

const PAGE_CONTENT_WIDTH = 483;
const MIN_FIT_SCALE = 0.6;

/** What the pointer is over; kept until it leaves the canvas so the "+" buttons stay reachable. */
const hover = ref<Selection | null>(null);
const target = computed(() => hover.value ?? props.selection);
const size = computed(() =>
    props.zoom === 'fit'
        ? { width: '100%', minWidth: `${PAGE_CONTENT_WIDTH * MIN_FIT_SCALE}px` }
        : { width: `${PAGE_CONTENT_WIDTH * props.zoom}px` },
);
</script>

<template>
    <div class="overflow-x-auto rounded-md border bg-muted/40 p-6">
        <div
            class="group/canvas relative mx-auto"
            :style="size"
            @pointerleave="if (!dragging) hover = null;"
        >
            <HeaderPreview
                :layout="model"
                :selection="selection"
                @select="emit('select', $event)"
                @hover="if (!dragging) hover = $event;"
            />
            <CanvasHandles
                v-model="model"
                :target="target"
                @add-row="emit('addRow', $event)"
                @add-column="(row, cell) => emit('addColumn', row, cell)"
            />
        </div>
    </div>
</template>
