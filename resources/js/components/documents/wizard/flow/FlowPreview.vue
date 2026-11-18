<script setup lang="ts">
import { computed } from 'vue';
import { BOX, layoutFlow } from '@/components/documents/wizard/flow/flowLayout';
import type { FlowStep } from '@/types';

const props = defineProps<{ lanes: string[]; steps: FlowStep[] }>();

const svg = defineModel<SVGSVGElement | null>('svg', { default: null });
const layout = computed(() => layoutFlow(props.lanes, props.steps));
const colWidth = BOX.w + BOX.gapX;

function lines(text: string | null): string[] {
    const words = (text ?? '').split(/\s+/).filter(Boolean);
    const out: string[] = [];

    for (const word of words) {
        const last = out[out.length - 1];

        if (last && (last + ' ' + word).length <= 22) {
            out[out.length - 1] = `${last} ${word}`;
        } else {
            out.push(word);
        }
    }

    return out.slice(0, 3);
}
</script>

<template>
    <div class="overflow-auto rounded-lg border bg-card">
        <svg
            :ref="(el) => (svg = el as SVGSVGElement | null)"
            :viewBox="`0 0 ${layout.width} ${layout.height}`"
            :width="layout.width"
            :height="layout.height"
            font-family="sans-serif"
            font-size="12"
            role="img"
            aria-label="Pratinjau diagram alir"
        >
            <rect width="100%" height="100%" fill="var(--card)" />
            <g v-if="lanes.length">
                <g v-for="(lane, i) in layout.lanes" :key="lane">
                    <rect
                        :x="BOX.pad + i * colWidth - 8"
                        y="8"
                        :width="BOX.w + 16"
                        :height="layout.height - 16"
                        fill="var(--muted)"
                        rx="8"
                    />
                    <text
                        :x="BOX.pad + i * colWidth + BOX.w / 2"
                        y="30"
                        text-anchor="middle"
                        font-weight="600"
                        fill="var(--foreground)"
                    >
                        {{ lane }}
                    </text>
                </g>
            </g>
            <defs>
                <marker
                    id="arrow"
                    viewBox="0 0 10 10"
                    refX="9"
                    refY="5"
                    markerWidth="7"
                    markerHeight="7"
                    orient="auto"
                >
                    <path d="M0,0 L10,5 L0,10 z" fill="var(--foreground)" />
                </marker>
            </defs>
            <g v-for="(edge, i) in layout.edges" :key="i">
                <path
                    :d="edge.path"
                    fill="none"
                    stroke="var(--foreground)"
                    stroke-width="1.5"
                    marker-end="url(#arrow)"
                />
                <text
                    v-if="edge.label"
                    :x="edge.lx"
                    :y="edge.ly"
                    fill="var(--foreground)"
                >
                    {{ edge.label }}
                </text>
            </g>
            <g v-for="step in layout.placed" :key="step.id">
                <polygon
                    v-if="step.type === 'decision'"
                    :points="`${step.x + BOX.w / 2},${step.y} ${step.x + BOX.w},${step.y + BOX.h / 2} ${step.x + BOX.w / 2},${step.y + BOX.h} ${step.x},${step.y + BOX.h / 2}`"
                    fill="var(--card)"
                    stroke="var(--primary)"
                    stroke-width="2"
                />
                <rect
                    v-else
                    :x="step.x"
                    :y="step.y"
                    :width="BOX.w"
                    :height="BOX.h"
                    :rx="step.type === 'process' ? 6 : BOX.h / 2"
                    :fill="
                        step.type === 'process'
                            ? 'var(--card)'
                            : 'var(--primary)'
                    "
                    stroke="var(--primary)"
                    stroke-width="2"
                />
                <text
                    :x="step.x + BOX.w / 2"
                    :y="
                        step.y +
                        BOX.h / 2 -
                        (lines(step.text || '…').length - 1) * 7 +
                        4
                    "
                    text-anchor="middle"
                    :fill="
                        step.type === 'start' || step.type === 'end'
                            ? 'var(--primary-foreground)'
                            : 'var(--foreground)'
                    "
                >
                    <tspan
                        v-for="(line, i) in lines(step.text || '…')"
                        :key="i"
                        :x="step.x + BOX.w / 2"
                        :dy="i ? 14 : 0"
                    >
                        {{ line }}
                    </tspan>
                </text>
            </g>
        </svg>
    </div>
</template>
