<script setup lang="ts">
import { computed } from 'vue';
import type { Rect } from '@/components/documents/wizard/flow/flowGeometry';
import type { FlowStepType } from '@/types';

const props = defineProps<{
    type: FlowStepType;
    box: Pick<Rect, 'x' | 'y' | 'w' | 'h'>;
    /** The step's own fill colour, chosen in the old builder. */
    fill?: string;
}>();

const terminator = computed(
    () => props.type === 'start' || props.type === 'end',
);
const background = computed(
    () => props.fill ?? (terminator.value ? 'var(--primary)' : 'var(--card)'),
);

/** Outline of the shapes that are not a plain box. */
const path = computed(() => {
    const { x, y, w, h } = props.box;
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
        <path v-if="path" :d="path" :fill="background" />
        <circle
            v-else-if="type === 'connector'"
            :cx="box.x + box.w / 2"
            :cy="box.y + box.h / 2"
            :r="box.h / 2"
            :fill="background"
        />
        <template v-else>
            <rect
                :x="box.x"
                :y="box.y"
                :width="box.w"
                :height="box.h"
                :rx="terminator ? box.h / 2 : type === 'process' ? 6 : 0"
                :fill="background"
            />
            <template v-if="type === 'predefined'">
                <line
                    :x1="box.x + 8"
                    :y1="box.y"
                    :x2="box.x + 8"
                    :y2="box.y + box.h"
                />
                <line
                    :x1="box.x + box.w - 8"
                    :y1="box.y"
                    :x2="box.x + box.w - 8"
                    :y2="box.y + box.h"
                />
            </template>
        </template>
    </g>
</template>
