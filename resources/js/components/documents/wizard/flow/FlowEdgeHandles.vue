<script setup lang="ts">
import { computed } from 'vue';
import { segmentHandles } from '@/components/documents/wizard/flow/flowEdgeMath';
import type { DrawnEdge } from '@/components/documents/wizard/flow/flowLayout';

/**
 * Handles of the selected line (draw.io style): a square in the middle of
 * each segment to move it, a circle on each end to re-attach it, and a
 * dashed preview while an end is being dragged.
 */
const props = defineProps<{
    edge: DrawnEdge;
    dragging?: { which: 'start' | 'end'; at: { x: number; y: number } };
}>();

const segments = computed(() => segmentHandles(props.edge.points));
const ends = computed(() => ({
    start: props.edge.points[0],
    end: props.edge.points[props.edge.points.length - 1],
}));
const fixed = computed(() =>
    props.dragging?.which === 'start' ? ends.value.end : ends.value.start,
);
</script>

<template>
    <g>
        <line
            v-if="dragging"
            :x1="fixed.x"
            :y1="fixed.y"
            :x2="dragging.at.x"
            :y2="dragging.at.y"
            stroke="var(--primary)"
            stroke-width="2"
            stroke-dasharray="5 4"
            pointer-events="none"
        />
        <g
            v-for="segment in segments"
            :key="segment.k"
            :data-edge-seg="segment.k"
            :class="[
                'touch-none',
                segment.horizontal ? 'cursor-ns-resize' : 'cursor-ew-resize',
            ]"
        >
            <rect
                :x="segment.x - 10"
                :y="segment.y - 10"
                width="20"
                height="20"
                fill="transparent"
            />
            <rect
                :x="segment.x - 4"
                :y="segment.y - 4"
                width="8"
                height="8"
                rx="1.5"
                fill="var(--card)"
                stroke="var(--primary)"
                stroke-width="2"
            >
                <title>Seret untuk menggeser bagian garis ini</title>
            </rect>
        </g>
        <g
            v-for="(p, which) in ends"
            :key="which"
            :data-edge-end="which"
            class="cursor-move touch-none"
        >
            <circle :cx="p.x" :cy="p.y" r="12" fill="transparent" />
            <circle
                :cx="p.x"
                :cy="p.y"
                r="5"
                fill="var(--primary)"
                stroke="var(--card)"
                stroke-width="2"
            >
                <title>
                    {{
                        which === 'end'
                            ? 'Seret ke titik pada simbol lain untuk memindah tujuan'
                            : 'Seret ke titik lain pada simbol ini untuk mengganti sisi keluar'
                    }}
                </title>
            </circle>
        </g>
    </g>
</template>
