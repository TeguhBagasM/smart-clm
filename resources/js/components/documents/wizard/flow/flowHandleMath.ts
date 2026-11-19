import {
    cellBox,
    clampSize,
    GRID,
    portPoint,
    SIDES,
} from '@/components/documents/wizard/flow/flowGeometry';
import type {
    Cell,
    Rect,
} from '@/components/documents/wizard/flow/flowGeometry';
import type {
    Layout,
    PlacedStep,
} from '@/components/documents/wizard/flow/flowLayout';
import type { FlowSide, FlowStepSize } from '@/types';

export type Point = { x: number; y: number };

/** How close (canvas units) a drop must be to a connection point to snap to it. */
export const SNAP = 18;

/** The connection point nearest to p within SNAP, on any step but `skip`. */
export function nearestPort(
    steps: PlacedStep[],
    p: Point,
    skip: string,
): { id: string; side: FlowSide } | null {
    let best: { id: string; side: FlowSide; d: number } | null = null;

    for (const step of steps.filter((s) => s.id !== skip)) {
        for (const side of SIDES) {
            const q = portPoint(step.box, side);
            const d = Math.hypot(q.x - p.x, q.y - p.y);

            if (d <= SNAP && (!best || d < best.d)) {
                best = { id: step.id, side, d };
            }
        }
    }

    return best && { id: best.id, side: best.side };
}

/** New size from the corner handle: the box stays centred, so it grows twice the pointer's travel. */
export function resizedSize(
    start: { w: number; h: number },
    from: Point,
    to: Point,
): FlowStepSize {
    return clampSize({
        w: (start.w + 2 * (to.x - from.x)) / GRID.boxW,
        h: (start.h + 2 * (to.y - from.y)) / GRID.boxH,
    });
}

/** A pointer position in canvas units. */
export function svgPoint(
    svg: SVGSVGElement | null,
    event: { clientX: number; clientY: number },
): Point {
    const p = new DOMPoint(event.clientX, event.clientY).matrixTransform(
        svg?.getScreenCTM()?.inverse(),
    );

    return { x: p.x, y: p.y };
}

/** Where a step being moved would land, and whether that cell is free. */
export function dropTarget(
    layout: Layout,
    id: string,
    cell: Cell | null,
): { box: Rect; free: boolean } | null {
    if (!cell) return null;

    const occupant = layout.steps.find(
        (s) => s.lane === cell.lane && s.row === cell.row,
    );
    const moving = layout.steps.find((s) => s.id === id);

    return {
        box: cellBox(cell, layout.grid, moving?.size),
        free: !occupant || occupant.id === id,
    };
}
