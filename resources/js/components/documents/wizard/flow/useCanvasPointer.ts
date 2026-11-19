import type { Ref } from 'vue';
import { computed, ref } from 'vue';
import { cellAt } from '@/components/documents/wizard/flow/flowGeometry';
import type { Cell } from '@/components/documents/wizard/flow/flowGeometry';
import {
    dropTarget,
    nearestPort,
    resizedSize,
    svgPoint,
} from '@/components/documents/wizard/flow/flowHandleMath';
import type { Point } from '@/components/documents/wizard/flow/flowHandleMath';

import { connectFrom } from '@/components/documents/wizard/flow/flowLinkOps';
import {
    connect,
    moveStep,
    updateStep,
} from '@/components/documents/wizard/flow/flowOps';
import type { LinkKind } from '@/components/documents/wizard/flow/flowOps';
import { useEdgeDrag } from '@/components/documents/wizard/flow/useEdgeDrag';
import type { FlowEditor } from '@/components/documents/wizard/flow/useFlowEditor';
import type { FlowSide } from '@/types';

type Drag =
    | { mode: 'move'; id: string; from: Cell; moved: boolean }
    | { mode: 'link'; from: string; exit: FlowSide; x: number; y: number }
    | { mode: 'resize'; id: string; w: number; h: number; start: Point }
    | null;

/**
 * Mouse, pen and touch on the canvas: click selects, drag moves a step to
 * another cell, a connection point dragged onto another step draws a line
 * between those sides (snapping to the nearest point), the corner handle
 * resizes, double click edits the text.
 */
export function useCanvasPointer(
    editor: FlowEditor,
    svg: Ref<SVGSVGElement | null>,
) {
    const drag = ref<Drag>(null);
    const hover = ref<Cell | null>(null);
    const { layout, selection, editing, linking, apply } = editor;

    const point = (event: { clientX: number; clientY: number }) =>
        svgPoint(svg.value, event);

    const cellOf = (p: Point) =>
        cellAt(p.x, p.y, layout.value.lanes.length, layout.value.grid);
    const lines = useEdgeDrag(editor, cellOf);
    const stepIn = (cell: Cell | null) =>
        cell
            ? layout.value.steps.find(
                  (s) => s.lane === cell.lane && s.row === cell.row,
              )
            : undefined;

    function down(event: PointerEvent): void {
        const target = event.target as Element;
        const port = target.closest('[data-port]');
        const resize = target
            .closest('[data-resize]')
            ?.getAttribute('data-resize');
        const step = target.closest('[data-step]')?.getAttribute('data-step');
        const edge = target.closest('[data-edge]')?.getAttribute('data-edge');

        if (linking.value) {
            if (step) apply(connect(linking.value, step));

            linking.value = null;

            return;
        }

        if (lines.start(target, point(event))) {
            svg.value?.setPointerCapture(event.pointerId);

            return;
        }

        if (port) {
            const from = port.getAttribute('data-port-step') as string;
            selection.value = { kind: 'step', id: from };
            drag.value = {
                mode: 'link',
                from,
                exit: port.getAttribute('data-port') as FlowSide,
                ...point(event),
            };
        } else if (resize) {
            const placed = layout.value.steps.find(
                (item) => item.id === resize,
            );
            drag.value = placed
                ? {
                      mode: 'resize',
                      id: resize,
                      w: placed.box.w,
                      h: placed.box.h,
                      start: point(event),
                  }
                : null;
        } else if (step) {
            const placed = layout.value.steps.find((item) => item.id === step);
            selection.value = { kind: 'step', id: step };
            drag.value = placed
                ? {
                      mode: 'move',
                      id: step,
                      from: { lane: placed.lane, row: placed.row },
                      moved: false,
                  }
                : null;
        } else if (edge) {
            const [from, link] = edge.split('|');
            selection.value = { kind: 'edge', from, link: link as LinkKind };
        } else {
            selection.value = null;
        }

        if (drag.value) svg.value?.setPointerCapture(event.pointerId);
    }

    function move(event: PointerEvent): void {
        const p = point(event);

        if (lines.drag.value) return lines.move(p);
        hover.value = cellOf(p);
        const current = drag.value;

        if (current?.mode === 'link') {
            drag.value = { ...current, ...p };
        } else if (current?.mode === 'move' && hover.value) {
            current.moved ||=
                hover.value.lane !== current.from.lane ||
                hover.value.row !== current.from.row;
        } else if (current?.mode === 'resize') {
            const size = resizedSize(current, current.start, p);
            apply(updateStep(current.id, { size }), `size:${current.id}`);
        }
    }

    function up(event: PointerEvent): void {
        if (lines.drag.value) return lines.end(point(event));

        const current = drag.value;
        const p = point(event);
        drag.value = null;

        if (current?.mode === 'move' && current.moved) {
            const cell = cellOf(p);

            if (cell) apply(moveStep(current.id, cell.lane, cell.row));
        } else if (current?.mode === 'link') {
            const snapped = nearestPort(layout.value.steps, p, current.from);
            const target = snapped?.id ?? stepIn(cellOf(p))?.id;

            if (target && target !== current.from) {
                apply(
                    connectFrom(current.from, target, {
                        exit: current.exit,
                        entry: snapped?.side,
                    }),
                );
            }
        }
    }

    /** Found by cell, not target: pointer capture can retarget the event to the svg. */
    function doubleClick(event: MouseEvent): void {
        const step = stepIn(cellOf(point(event)));

        if (step) editing.value = step.id;
    }

    /** Where a dragged step would land, and whether that cell is free. */
    const target = computed(() => {
        const current = drag.value;

        return current?.mode === 'move' && current.moved
            ? dropTarget(layout.value, current.id, hover.value)
            : null;
    });

    /** The step under the pointer, which shows its connection points. */
    const hovered = computed(() => stepIn(hover.value)?.id);

    return {
        drag,
        lineDrag: lines.drag,
        target,
        hovered,
        handlers: {
            pointerdown: down,
            pointermove: move,
            pointerup: up,
            pointerleave: () => (hover.value = null),
            dblclick: doubleClick,
        },
    };
}
