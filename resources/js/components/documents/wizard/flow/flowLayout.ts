import {
    canvasSize,
    cellBox,
    GRID,
} from '@/components/documents/wizard/flow/flowGeometry';
import type { Rect } from '@/components/documents/wizard/flow/flowGeometry';
import {
    flowLinks,
    laneIndex,
    placeSteps,
} from '@/components/documents/wizard/flow/flowGraph';
import type { FlowLink } from '@/components/documents/wizard/flow/flowGraph';
import { routeEdges } from '@/components/documents/wizard/flow/flowRoute';
import type { FlowBox } from '@/components/documents/wizard/flow/flowRoute';
import type { FlowStep } from '@/types';

export type PlacedStep = FlowStep & { lane: number; row: number; box: Rect };

export type DrawnEdge = Omit<FlowLink, 'label'> & {
    path: string;
    label?: { text: string; x: number; y: number };
};

export type Layout = {
    lanes: string[];
    rows: number;
    width: number;
    height: number;
    steps: PlacedStep[];
    edges: DrawnEdge[];
};

/**
 * Where every step and line goes. Lines come from the PDF engine's router,
 * which works with y growing upwards, so y is negated going in and out.
 */
export function layoutFlow(
    lanes: string[],
    steps: FlowStep[],
    spareRows = 1,
): Layout {
    const columns = lanes.length ? lanes : [''];
    const placed: PlacedStep[] = placeSteps(lanes, steps).map((step) => {
        const cell = { lane: laneIndex(lanes, step.actor), row: step.row ?? 0 };

        return { ...step, ...cell, box: cellBox(cell) };
    });
    const boxes: FlowBox[] = placed.map((step) => ({
        id: step.id,
        cx: step.box.cx,
        top: -step.box.y,
        w: step.box.w,
        h: step.box.h,
        lane: step.lane,
        row: step.row,
    }));
    // Same order as the engine: lines going down first, then lines going back up.
    const rowOf = new Map(placed.map((step) => [step.id, step.row]));
    const all = flowLinks(placed);
    const down = (link: FlowLink) =>
        (rowOf.get(link.to) ?? 0) > (rowOf.get(link.from) ?? 0);
    const links = [...all.filter(down), ...all.filter((link) => !down(link))];
    const routed = routeEdges(boxes, links, {
        laneLeft: (lane) => GRID.pad + lane * GRID.laneW,
        laneW: GRID.laneW,
        gap: GRID.gap,
    });
    const rows = Math.max(0, ...placed.map((step) => step.row + 1)) + spareRows;

    return {
        lanes: columns,
        rows,
        ...canvasSize(columns.length, rows),
        steps: placed,
        edges: links.map((link, i) => ({
            ...link,
            path: routed[i].points
                .map((p, j) => `${j ? 'L' : 'M'}${p.x},${-p.y}`)
                .join(' '),
            label: routed[i].label && {
                text: routed[i].label.text,
                x: routed[i].label.x,
                y: -routed[i].label.y,
            },
        })),
    };
}
