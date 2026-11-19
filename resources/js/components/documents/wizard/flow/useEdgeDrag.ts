import { ref } from 'vue';
import type { Cell } from '@/components/documents/wizard/flow/flowGeometry';
import { toGrid } from '@/components/documents/wizard/flow/flowGeometry';
import {
    moveSegment,
    sideOf,
} from '@/components/documents/wizard/flow/flowEdgeMath';
import { nearestPort } from '@/components/documents/wizard/flow/flowHandleMath';
import type { Point } from '@/components/documents/wizard/flow/flowHandleMath';
import type { DrawnEdge } from '@/components/documents/wizard/flow/flowLayout';
import {
    retarget,
    setPorts,
    setRoute,
} from '@/components/documents/wizard/flow/flowLinkOps';
import type { FlowEditor } from '@/components/documents/wizard/flow/useFlowEditor';

type EdgeDrag =
    | { mode: 'segment'; edge: DrawnEdge; k: number; base: Point[] }
    | { mode: 'end'; edge: DrawnEdge; which: 'start' | 'end'; at: Point }
    | null;

/**
 * Editing the selected line like draw.io: drag a segment's handle to move
 * that segment (the line keeps its new shape, stored in grid units), drag the
 * arrow end onto another step or side, or the start onto another side.
 */
export function useEdgeDrag(
    editor: FlowEditor,
    cellOf: (p: Point) => Cell | null,
) {
    const { layout, selection, apply } = editor;
    const drag = ref<EdgeDrag>(null);

    const selected = () => {
        const current = selection.value;

        return current?.kind === 'edge'
            ? layout.value.edges.find(
                  (e) => e.from === current.from && e.kind === current.link,
              )
            : undefined;
    };
    const stepOf = (id: string) =>
        layout.value.steps.find((step) => step.id === id);

    /** Starts a drag when the pointer went down on a handle of the selected line. */
    function start(target: Element, p: Point): boolean {
        const edge = selected();
        const segment = target
            .closest('[data-edge-seg]')
            ?.getAttribute('data-edge-seg');
        const end = target
            .closest('[data-edge-end]')
            ?.getAttribute('data-edge-end');

        if (!edge) return false;

        if (segment != null) {
            drag.value = {
                mode: 'segment',
                edge,
                k: Number(segment),
                base: edge.points,
            };
        } else if (end === 'start' || end === 'end') {
            drag.value = { mode: 'end', edge, which: end, at: p };
        }

        return drag.value !== null;
    }

    function move(p: Point): void {
        const current = drag.value;

        if (current?.mode === 'end') {
            drag.value = { ...current, at: p };

            return;
        }

        const from = current && stepOf(current.edge.from);
        const to = current && stepOf(current.edge.to);

        if (current?.mode !== 'segment' || !from || !to) return;

        const pts = moveSegment(current.base, current.k, p);
        const sides = {
            exit: sideOf(from.box, pts[0]),
            entry: sideOf(to.box, pts[pts.length - 1]),
        };
        const bends = pts.slice(1, -1).map((q) => toGrid(q, layout.value.grid));

        apply(
            setRoute(current.edge.from, current.edge.kind, bends, sides),
            `route:${current.edge.from}:${current.edge.kind}`,
        );
    }

    function end(p: Point): void {
        const current = drag.value;
        drag.value = null;

        if (current?.mode !== 'end') return;

        const { edge } = current;

        if (current.which === 'start') {
            const source = stepOf(edge.from);
            const snapped = source && nearestPort([source], p, '');

            if (snapped)
                apply(setPorts(edge.from, edge.kind, { exit: snapped.side }));

            return;
        }

        const snapped = nearestPort(layout.value.steps, p, edge.from);
        const cell = cellOf(p);
        const target =
            snapped?.id ??
            layout.value.steps.find(
                (s) => cell && s.lane === cell.lane && s.row === cell.row,
            )?.id;

        if (target)
            apply(retarget(edge.from, edge.kind, target, snapped?.side));
    }

    return { drag, start, move, end };
}
