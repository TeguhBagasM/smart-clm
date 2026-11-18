<script setup lang="ts">
import { computed } from 'vue';
import { BOX } from '@/components/documents/wizard/flow/flowLayout';
import type { FlowStepType } from '@/types';

const props = defineProps<{ type: FlowStepType; x: number; y: number }>();

const { w, h } = BOX;
const filled = computed(() => props.type === 'start' || props.type === 'end');

/** Outline of the shapes that are not a plain box, in the step's own box. */
const path = computed(() => {
    const { x, y } = props;
    const k = h * 0.35;
    const a = h * 0.12;

    switch (props.type) {
        case 'decision':
            return `M${x + w / 2},${y} L${x + w},${y + h / 2} L${x + w / 2},${y + h} L${x},${y + h / 2} Z`;
        case 'data':
            return `M${x + k},${y} L${x + w},${y} L${x + w - k},${y + h} L${x},${y + h} Z`;
        case 'document':
            return `M${x},${y} L${x + w},${y} L${x + w},${y + h - a} C${x + w * 0.7},${y + h + a * 0.8} ${x + w * 0.3},${y + h - a * 2.6} ${x},${y + h - a} Z`;
        default:
            return null;
    }
});
</script>

<template>
    <g stroke="var(--primary)" stroke-width="2">
        <path v-if="path" :d="path" fill="var(--card)" />
        <circle
            v-else-if="type === 'connector'"
            :cx="x + w / 2"
            :cy="y + h / 2"
            :r="h / 2"
            fill="var(--card)"
        />
        <template v-else>
            <rect
                :x="x"
                :y="y"
                :width="w"
                :height="h"
                :rx="filled ? h / 2 : type === 'process' ? 6 : 0"
                :fill="filled ? 'var(--primary)' : 'var(--card)'"
            />
            <template v-if="type === 'predefined'">
                <line :x1="x + 8" :y1="y" :x2="x + 8" :y2="y + h" />
                <line :x1="x + w - 8" :y1="y" :x2="x + w - 8" :y2="y + h" />
            </template>
        </template>
    </g>
</template>
