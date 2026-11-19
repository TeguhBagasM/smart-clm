import type { Ref } from 'vue';
import { computed, ref } from 'vue';
import {
    cellAt,
    cellBox,
} from '@/components/documents/wizard/flow/flowGeometry';
import type { Cell } from '@/components/documents/wizard/flow/flowGeometry';
import { connect, moveStep } from '@/components/documents/wizard/flow/flowOps';
import type { LinkKind } from '@/components/documents/wizard/flow/flowOps';
import type { FlowEditor } from '@/components/documents/wizard/flow/useFlowEditor';

type Drag =
    | { mode: 'move'; id: string; from: Cell; moved: boolean }
    | { mode: 'link'; from: string; x: number; y: number }
    | null;

/**
 * Mouse, pen and touch on the canvas: click selects, drag moves a step to
 * another cell, the round handle (or link mode from the menu) connects to the
 * step clicked next, double click edits the text.
 */
export function useCanvasPointer(
    editor: FlowEditor,
    svg: Ref<SVGSVGElement | null>,
) {
    const drag = ref<Drag>(null);
    const hover = ref<Cell | null>(null);
    const { layout, selection, editing, linking, apply } = editor;

    function point(event: { clientX: number; clientY: number }) {
        const matrix = svg.value?.getScreenCTM()?.inverse();
        const p = new DOMPoint(event.clientX, event.clientY).matrixTransform(
            matrix,
        );

        return { x: p.x, y: p.y };
    }

    function cellOf(event: { clientX: number; clientY: number }): Cell | null {
        const p = point(event);

        return cellAt(p.x, p.y, layout.value.lanes.length, layout.value.rows);
    }

    const stepIn = (cell: Cell | null) =>
        cell
            ? layout.value.steps.find(
                  (s) => s.lane === cell.lane && s.row === cell.row,
              )
            : undefined;

    function down(event: PointerEvent): void {
        const target = event.target as Element;
        const handle = target.closest('[data-handle]');
        const step = target.closest('[data-step]')?.getAttribute('data-step');
        const edge = target.closest('[data-edge]')?.getAttribute('data-edge');

        if (linking.value) {
            if (step) {
                apply(connect(linking.value, step));
            }

            linking.value = null;

            return;
        }

        if (handle && selection.value?.kind === 'step') {
            drag.value = {
                mode: 'link',
                from: selection.value.id,
                ...point(event),
            };
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

        if (drag.value) {
            svg.value?.setPointerCapture(event.pointerId);
        }
    }

    function move(event: PointerEvent): void {
        hover.value = cellOf(event);
        const current = drag.value;

        if (current?.mode === 'link') {
            drag.value = { ...current, ...point(event) };
        } else if (current?.mode === 'move' && hover.value) {
            current.moved ||=
                hover.value.lane !== current.from.lane ||
                hover.value.row !== current.from.row;
        }
    }

    function up(event: PointerEvent): void {
        const current = drag.value;
        const cell = cellOf(event);
        drag.value = null;

        if (current?.mode === 'move' && current.moved && cell) {
            apply(moveStep(current.id, cell.lane, cell.row));
        } else if (current?.mode === 'link') {
            const target = stepIn(cell);

            if (target) {
                apply(connect(current.from, target.id));
            }
        }
    }

    /** Found by cell, not target: pointer capture can retarget the event to the svg. */
    function doubleClick(event: MouseEvent): void {
        const step = stepIn(cellOf(event));

        if (step) {
            editing.value = step.id;
        }
    }

    /** Where a dragged step would land, and whether that cell is free. */
    const target = computed(() => {
        const current = drag.value;
        const cell = hover.value;

        if (current?.mode !== 'move' || !current.moved || !cell) {
            return null;
        }

        const occupant = stepIn(cell);

        return {
            box: cellBox(cell),
            free: !occupant || occupant.id === current.id,
        };
    });

    return {
        drag,
        target,
        handlers: {
            pointerdown: down,
            pointermove: move,
            pointerup: up,
            pointerleave: () => (hover.value = null),
            dblclick: doubleClick,
        },
    };
}
