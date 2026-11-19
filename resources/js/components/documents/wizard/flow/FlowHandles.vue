<script setup lang="ts">
import { computed } from 'vue';
import {
    portPoint,
    SIDES,
} from '@/components/documents/wizard/flow/flowGeometry';
import type { Layout } from '@/components/documents/wizard/flow/flowLayout';

/**
 * Overlay of the editable canvas: connection points (draw.io style) on the
 * hovered and selected step, on every step while a line is being drawn, the
 * line being drawn, and the resize handle of the selected step.
 */
const props = defineProps<{
    layout: Layout;
    selected?: string;
    hovered?: string;
    drawing?: { from: string; exit: string; x: number; y: number };
    busy: boolean;
}>();

const SIDE_NAME = {
    top: 'atas',
    right: 'kanan',
    bottom: 'bawah',
    left: 'kiri',
} as const;

const withPorts = computed(() =>
    props.layout.steps.filter((step) =>
        props.drawing
            ? step.id !== props.drawing.from
            : !props.busy &&
              step.type !== 'end' &&
              (step.id === props.selected || step.id === props.hovered),
    ),
);
const source = computed(() =>
    props.layout.steps.find((step) => step.id === props.drawing?.from),
);
const start = computed(() =>
    source.value && props.drawing
        ? portPoint(
              source.value.box,
              props.drawing.exit as (typeof SIDES)[number],
          )
        : null,
);
const resizing = computed(() =>
    props.busy || props.drawing
        ? undefined
        : props.layout.steps.find((step) => step.id === props.selected),
);
</script>

<template>
    <g>
        <line
            v-if="start && drawing"
            :x1="start.x"
            :y1="start.y"
            :x2="drawing.x"
            :y2="drawing.y"
            stroke="var(--primary)"
            stroke-width="2"
            stroke-dasharray="5 4"
            pointer-events="none"
        />
        <template v-for="step in withPorts" :key="step.id">
            <g
                v-for="side in SIDES"
                :key="side"
                :data-port="side"
                :data-port-step="step.id"
                class="cursor-crosshair touch-none"
            >
                <circle
                    :cx="portPoint(step.box, side).x"
                    :cy="portPoint(step.box, side).y"
                    r="12"
                    fill="transparent"
                />
                <circle
                    :cx="portPoint(step.box, side).x"
                    :cy="portPoint(step.box, side).y"
                    :r="drawing ? 6 : 5"
                    fill="var(--card)"
                    stroke="var(--primary)"
                    stroke-width="2"
                >
                    <title>
                        Tarik dari sisi {{ SIDE_NAME[side] }} ke titik pada
                        simbol lain
                    </title>
                </circle>
            </g>
        </template>
        <g
            v-if="resizing"
            :data-resize="resizing.id"
            class="cursor-nwse-resize touch-none"
        >
            <rect
                :x="resizing.box.x + resizing.box.w - 2"
                :y="resizing.box.y + resizing.box.h - 2"
                width="18"
                height="18"
                fill="transparent"
            />
            <rect
                :x="resizing.box.x + resizing.box.w + 2"
                :y="resizing.box.y + resizing.box.h + 2"
                width="9"
                height="9"
                rx="2"
                fill="var(--primary)"
                stroke="var(--card)"
                stroke-width="1.5"
            >
                <title>Seret untuk mengubah ukuran</title>
            </rect>
        </g>
    </g>
</template>
