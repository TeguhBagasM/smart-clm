<script setup lang="ts">
import { computed, ref } from 'vue';
import CanvasAddButtons from '@/components/documents/config/header/CanvasAddButtons.vue';
import { edgePercent } from '@/components/documents/config/header/headerDragOps';
import { useCanvasHandles } from '@/components/documents/config/header/useCanvasHandles';
import type { Selection } from '@/components/documents/config/header/useHeaderEditor';
import { computeGeometry } from '@/lib/headerGeometry';
import type { HeaderLayout } from '@/types';

/**
 * Handles over the canvas, for the element in focus only (hovered or selected),
 * so they never take clicks from the other cells: the dividers and bottom edge
 * of its row, or the logo's inner edge, and the "+" buttons of a cell. Every
 * drag has an arrow-key twin; the side panel has number inputs.
 */
const layout = defineModel<HeaderLayout>({ required: true });
const props = defineProps<{ target: Selection | null }>();
defineEmits<{
    addRow: [row: number];
    addColumn: [row: number, cell: number];
}>();

const WIDTH = 483;
const root = ref<HTMLElement | null>(null);
const geometry = computed(() => computeGeometry(layout.value, WIDTH));
const pctX = (x: number): string => `${(x / geometry.value.width) * 100}%`;
const pctY = (y: number): string => `${(y / geometry.value.height) * 100}%`;

const rows = computed(() =>
    layout.value.rows.map((row, index) => {
        const cells = geometry.value.cells.filter((c) => c.rowIndex === index);
        const first = cells[0];
        const last = cells[cells.length - 1];

        return {
            index,
            row,
            cells,
            x: first.x,
            w: last.x + last.w - first.x,
            y: first.y,
            h: first.h,
        };
    }),
);
const focus = computed(() => {
    const t = props.target;

    return t?.kind === 'cell' && rows.value[t.row]
        ? { ...rows.value[t.row], cell: rows.value[t.row].cells[t.cell] }
        : null;
});

const { dragDivider, keyDivider, dragHeight, keyHeight, dragLogo, keyLogo } =
    useCanvasHandles(layout, root, rows);

const bar =
    'group/h pointer-events-auto absolute z-10 flex touch-none items-center justify-center outline-hidden';
const glow =
    'rounded-full bg-primary/40 group-hover/h:bg-primary group-focus-visible/h:bg-ring';
</script>

<template>
    <div ref="root" class="pointer-events-none absolute inset-0">
        <template v-if="focus">
            <button
                v-for="(cell, c) in focus.cells.slice(0, -1)"
                :key="`d${c}`"
                type="button"
                role="separator"
                aria-orientation="vertical"
                :aria-valuenow="edgePercent(focus.row, c)"
                aria-valuemin="5"
                aria-valuemax="95"
                :aria-label="`Garis pemisah kolom ${c + 1} dan ${c + 2}. Panah kiri atau kanan menggeser 5%, dengan Shift 1%.`"
                :class="[
                    bar,
                    'w-2 -translate-x-1/2 cursor-col-resize pointer-coarse:w-6',
                ]"
                :style="{
                    left: pctX(cell.x + cell.w),
                    top: pctY(focus.y),
                    height: pctY(focus.h),
                }"
                @pointerdown="dragDivider($event, focus.index, c)"
                @keydown="keyDivider($event, focus.index, c)"
                @click.stop
            >
                <span :class="[glow, 'h-3/5 w-0.5']" />
            </button>
            <button
                type="button"
                role="separator"
                aria-orientation="horizontal"
                :aria-valuenow="focus.row.height"
                aria-valuemin="12"
                aria-valuemax="60"
                :aria-label="`Tepi bawah baris ${focus.index + 1}. Panah atas atau bawah mengubah tinggi.`"
                :class="[
                    bar,
                    'h-2 -translate-y-1/2 cursor-row-resize pointer-coarse:h-6',
                ]"
                :style="{
                    left: pctX(focus.x),
                    width: pctX(focus.w),
                    top: pctY(focus.y + focus.h),
                }"
                @pointerdown="dragHeight($event, focus.index)"
                @keydown="keyHeight($event, focus.index)"
                @click.stop
            >
                <span :class="[glow, 'h-0.5 w-1/4']" />
            </button>
            <CanvasAddButtons
                :row-left="pctX(focus.x)"
                :row-bottom="pctY(focus.y + focus.h)"
                :cell-right="pctX(focus.cell.x + focus.cell.w)"
                :row-top="pctY(focus.y)"
                :can-add-row="layout.rows.length < 12"
                :can-add-column="focus.cells.length < 4"
                @add-row="$emit('addRow', focus.index)"
                @add-column="
                    $emit('addColumn', focus.index, focus.cell.cellIndex)
                "
            />
        </template>
        <button
            v-else-if="target?.kind === 'logo' && geometry.logo"
            type="button"
            role="separator"
            aria-orientation="vertical"
            :aria-valuenow="layout.logo.width"
            aria-valuemin="10"
            aria-valuemax="40"
            aria-label="Lebar logo. Panah kiri atau kanan mengubahnya, dengan Shift lebih halus."
            :class="[
                bar,
                'w-2 -translate-x-1/2 cursor-col-resize pointer-coarse:w-6',
            ]"
            :style="{
                left: pctX(
                    layout.logo.side === 'left'
                        ? geometry.logo.w
                        : geometry.logo.x,
                ),
                top: '0',
                height: pctY(geometry.logo.h),
            }"
            @pointerdown="dragLogo"
            @keydown="keyLogo"
            @click.stop
        >
            <span :class="[glow, 'h-3/5 w-0.5']" />
        </button>
    </div>
</template>
