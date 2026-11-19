<script setup lang="ts">
import FlowShape from '@/components/documents/wizard/flow/FlowShape.vue';
import {
    GRID,
    laneLeft,
    ROW_PITCH,
} from '@/components/documents/wizard/flow/flowGeometry';
import type { Layout } from '@/components/documents/wizard/flow/flowLayout';
import { wrapText } from '@/components/documents/wizard/flow/flowText';

/**
 * Draws a laid-out flow. The canvas adds its grid and handles through the
 * slots; the JPG export uses it bare.
 */
defineProps<{
    layout: Layout;
    selectedStep?: string;
    selectedEdge?: string;
    grid?: boolean;
}>();

const svg = defineModel<SVGSVGElement | null>('svg', { default: null });

function textFill(type: string, color?: string): string {
    if (color) return color;

    return type === 'start' || type === 'end'
        ? 'var(--primary-foreground)'
        : 'var(--foreground)';
}
</script>

<template>
    <svg
        :ref="(el) => (svg = el as SVGSVGElement | null)"
        :viewBox="`0 0 ${layout.width} ${layout.height}`"
        font-family="sans-serif"
        font-size="12"
        role="img"
        aria-label="Diagram alir"
    >
        <defs>
            <marker
                id="flow-arrow"
                viewBox="0 0 10 10"
                refX="9"
                refY="5"
                markerWidth="7"
                markerHeight="7"
                orient="auto"
            >
                <path d="M0,0 L10,5 L0,10 z" fill="var(--foreground)" />
            </marker>
            <marker
                id="flow-arrow-on"
                viewBox="0 0 10 10"
                refX="9"
                refY="5"
                markerWidth="7"
                markerHeight="7"
                orient="auto"
            >
                <path d="M0,0 L10,5 L0,10 z" fill="var(--primary)" />
            </marker>
        </defs>
        <rect width="100%" height="100%" fill="var(--card)" />
        <g v-for="(lane, i) in layout.lanes" :key="i">
            <rect
                :x="laneLeft(i)"
                :y="GRID.pad"
                :width="GRID.laneW"
                :height="layout.height - GRID.pad * 2"
                :fill="i % 2 ? 'var(--card)' : 'var(--muted)'"
                stroke="var(--border)"
            />
            <text
                :x="laneLeft(i) + GRID.laneW / 2"
                :y="GRID.pad + GRID.head / 2 + 4"
                text-anchor="middle"
                font-weight="600"
                fill="var(--foreground)"
            >
                {{ lane || 'Langkah' }}
            </text>
        </g>
        <g v-if="grid" stroke="var(--border)" stroke-dasharray="3 4">
            <line
                v-for="row in layout.rows"
                :key="row"
                :x1="GRID.pad"
                :x2="layout.width - GRID.pad"
                :y1="GRID.pad + GRID.head + row * ROW_PITCH"
                :y2="GRID.pad + GRID.head + row * ROW_PITCH"
            />
        </g>
        <slot name="under" />
        <g v-for="edge in layout.edges" :key="`${edge.from}|${edge.kind}`">
            <path
                :d="edge.path"
                fill="none"
                :stroke="
                    selectedEdge === `${edge.from}|${edge.kind}`
                        ? 'var(--primary)'
                        : 'var(--foreground)'
                "
                :stroke-width="
                    selectedEdge === `${edge.from}|${edge.kind}` ? 2.5 : 1.5
                "
                :marker-end="
                    selectedEdge === `${edge.from}|${edge.kind}`
                        ? 'url(#flow-arrow-on)'
                        : 'url(#flow-arrow)'
                "
            />
            <path
                v-if="grid"
                :d="edge.path"
                fill="none"
                stroke="transparent"
                stroke-width="12"
                class="cursor-pointer"
                :data-edge="`${edge.from}|${edge.kind}`"
            />
            <text
                v-if="edge.label"
                :x="edge.label.x"
                :y="edge.label.y"
                font-size="11"
                fill="var(--foreground)"
            >
                {{ edge.label.text }}
            </text>
        </g>
        <g
            v-for="step in layout.steps"
            :key="step.id"
            :data-step="step.id"
            :class="grid ? 'cursor-grab touch-none' : undefined"
        >
            <FlowShape :type="step.type" :box="step.box" :fill="step.color" />
            <rect
                v-if="selectedStep === step.id"
                :x="step.box.x - 5"
                :y="step.box.y - 5"
                :width="step.box.w + 10"
                :height="step.box.h + 10"
                rx="8"
                fill="none"
                stroke="var(--ring)"
                stroke-width="2"
                stroke-dasharray="4 3"
            />
            <text
                :y="
                    step.box.y +
                    step.box.h / 2 -
                    (wrapText(step.text || '…').length - 1) * 7 +
                    4
                "
                text-anchor="middle"
                :fill="textFill(step.type, step.textColor)"
                pointer-events="none"
            >
                <tspan
                    v-for="(line, i) in wrapText(step.text || '…')"
                    :key="i"
                    :x="step.box.cx"
                    :dy="i ? 14 : 0"
                >
                    {{ line }}
                </tspan>
            </text>
        </g>
        <slot />
    </svg>
</template>
