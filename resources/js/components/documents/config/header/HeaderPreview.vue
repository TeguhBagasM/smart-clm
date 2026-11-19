<script setup lang="ts">
import { computed, inject, useId } from 'vue';
import { safeColor } from '@/components/documents/config/header/color/colorMath';
import { headerContextKey } from '@/components/documents/config/header/headerContext';
import { displayCell } from '@/components/documents/config/header/cellText';
import { nextCellSelection } from '@/components/documents/config/header/canvasKeys';
import type { Selection } from '@/components/documents/config/header/useHeaderEditor';
import HeaderLogoShape from '@/components/documents/config/header/HeaderLogoShape.vue';
import { useLogoBox } from '@/components/documents/config/header/logo/useLogoBox';
import {
    autoTone,
    computeGeometry,
    fontSizeOf,
    type CellBox,
    type HeaderLayout,
} from '@/lib/headerGeometry';

/**
 * The letterhead as the PDF prints it: the same geometry (lib/headerGeometry.ts)
 * drawn as SVG in points, so a width, a height or a colour looks here as it will on paper.
 */
const props = defineProps<{ layout: HeaderLayout; selection?: Selection }>();
const emit = defineEmits<{
    select: [selection: Selection];
    hover: [selection: Selection | null];
}>();

const PAGE_CONTENT_WIDTH = 483;
const context = inject(headerContextKey);
const uid = useId();

const samples = computed(() =>
    Object.fromEntries((context?.fields ?? []).map((f) => [f.value, f.sample])),
);
const geometry = computed(() =>
    computeGeometry(props.layout, PAGE_CONTENT_WIDTH),
);
const { url: logoUrl, box: logoBox } = useLogoBox(
    () => props.layout,
    () => geometry.value.logo,
);

function isSelected(row: number, cell: number): boolean {
    const s = props.selection;

    return s?.kind === 'cell' && s.row === row && s.cell === cell;
}

function onKey(event: KeyboardEvent, row: number, cell: number): void {
    const next = nextCellSelection(event, props.layout, row, cell);

    if (next) emit('select', next);
}

const shown = (box: CellBox) =>
    displayCell(box.cell, samples.value, box.w, box.h).text;

function textFill(
    color: string,
    background: string | null,
): string | undefined {
    const palette = context?.palette;

    if (color !== 'auto') return safeColor(color);

    return safeColor(
        autoTone(background) === 'light' ? palette?.light : palette?.dark,
    );
}
</script>

<template>
    <svg
        :viewBox="`0 0 ${geometry.width} ${geometry.height}`"
        class="block h-auto w-full overflow-visible bg-card"
        role="img"
        aria-label="Pratinjau kop surat"
        font-family="Helvetica, Arial, sans-serif"
        @click="emit('select', { kind: 'header' })"
    >
        <defs>
            <clipPath
                v-for="(box, i) in geometry.cells"
                :id="`${uid}-${i}`"
                :key="i"
            >
                <rect
                    :x="box.x + 2"
                    :y="box.y"
                    :width="Math.max(box.w - 4, 1)"
                    :height="box.h"
                />
            </clipPath>
        </defs>
        <g
            v-for="(box, i) in geometry.cells"
            :key="i"
            :data-cell="`${box.rowIndex}-${box.cellIndex}`"
            class="group cursor-pointer outline-hidden"
            tabindex="0"
            role="button"
            :aria-label="`Sel ${box.cellIndex + 1} baris ${box.rowIndex + 1}: ${shown(box)}`"
            :aria-pressed="isSelected(box.rowIndex, box.cellIndex)"
            @click.stop="
                emit('select', {
                    kind: 'cell',
                    row: box.rowIndex,
                    cell: box.cellIndex,
                })
            "
            @keydown="onKey($event, box.rowIndex, box.cellIndex)"
            @pointerenter="
                emit('hover', {
                    kind: 'cell',
                    row: box.rowIndex,
                    cell: box.cellIndex,
                })
            "
        >
            <rect
                :x="box.x"
                :y="box.y"
                :width="box.w"
                :height="box.h"
                :fill="safeColor(box.cell.background) ?? 'none'"
                :stroke="safeColor(layout.border)"
                stroke-width="0.5"
                pointer-events="all"
            />
            <text
                :x="
                    box.cell.align === 'center'
                        ? box.x + box.w / 2
                        : box.cell.align === 'right'
                          ? box.x + box.w - 5
                          : box.x + 5
                "
                :y="box.y + box.h / 2"
                dominant-baseline="central"
                :text-anchor="
                    box.cell.align === 'center'
                        ? 'middle'
                        : box.cell.align === 'right'
                          ? 'end'
                          : 'start'
                "
                :font-size="fontSizeOf(box.cell, box.h)"
                :font-weight="box.cell.bold ? 700 : 400"
                :font-style="box.cell.italic ? 'italic' : 'normal'"
                :fill="textFill(box.cell.color, box.cell.background)"
                :clip-path="`url(#${uid}-${i})`"
            >
                {{ shown(box) }}
            </text>
            <rect
                :x="box.x"
                :y="box.y"
                :width="box.w"
                :height="box.h"
                fill="none"
                stroke-width="2"
                :class="
                    isSelected(box.rowIndex, box.cellIndex)
                        ? 'stroke-primary'
                        : 'stroke-transparent group-hover:stroke-primary/50 group-focus-visible:stroke-ring'
                "
                pointer-events="none"
            />
        </g>
        <HeaderLogoShape
            v-if="geometry.logo"
            :area="geometry.logo"
            :image="logoBox"
            :url="logoUrl"
            :border="layout.border"
            :selected="selection?.kind === 'logo'"
            @select="emit('select', { kind: 'logo' })"
            @hover="emit('hover', { kind: 'logo' })"
        />
        <rect
            x="0"
            y="0"
            :width="geometry.width"
            :height="geometry.height"
            fill="none"
            :stroke="safeColor(layout.border)"
            stroke-width="1"
        />
    </svg>
</template>
