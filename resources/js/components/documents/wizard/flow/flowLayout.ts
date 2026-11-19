import {
    boxSize,
    canvasSize,
    cellBox,
    GRID,
    fromGrid,
    rowGrid,
} from '@/components/documents/wizard/flow/flowGeometry';
import type {
    Rect,
    RowGrid,
} from '@/components/documents/wizard/flow/flowGeometry';
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
    /** The drawn corners, in canvas units. */
    points: { x: number; y: number }[];
    /** Bent by hand: kept as drawn, not avoided by the router. */
    manual: boolean;
    /** A hand-bent line that runs through a step it does not connect. */
    blocked: boolean;
    label?: { text: string; x: number; y: number };
};

export type Layout = {
    lanes: string[];
    rows: number;
    grid: RowGrid;
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
    const positioned = placeSteps(lanes, steps).map((step) => ({
        ...step,
        lane: laneIndex(lanes, step.actor),
        row: step.row ?? 0,
    }));
    const rows =
        Math.max(0, ...positioned.map((step) => step.row + 1)) + spareRows;
    const tallest: number[] = [];

    for (const step of positioned) {
        tallest[step.row] = Math.max(
            tallest[step.row] ?? 0,
            boxSize(step.size).h,
        );
    }

    const grid = rowGrid(tallest, rows);
    const placed: PlacedStep[] = positioned.map((step) => ({
        ...step,
        box: cellBox(step, grid, step.size),
    }));
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
        place: (p) => {
            const at = fromGrid(p, grid);

            return { x: at.x, y: -at.y };
        },
    });

    return {
        lanes: columns,
        rows,
        grid,
        ...canvasSize(columns.length, grid),
        steps: placed,
        edges: links.map((link, i) => {
            const points = routed[i].points.map((p) => ({ x: p.x, y: -p.y }));

            return {
                ...link,
                path: points
                    .map((p, j) => `${j ? 'L' : 'M'}${p.x},${p.y}`)
                    .join(' '),
                points,
                manual: Boolean(link.points?.length),
                blocked:
                    Boolean(link.points?.length) &&
                    crossesStep(points, placed, link),
                label: routed[i].label && {
                    text: routed[i].label.text,
                    x: routed[i].label.x,
                    y: -routed[i].label.y,
                },
            };
        }),
    };
}

/** Whether a line runs through a step other than its two ends. */
function crossesStep(
    points: { x: number; y: number }[],
    steps: PlacedStep[],
    link: FlowLink,
): boolean {
    return points.slice(1).some((q, i) => {
        const p = points[i];

        return steps.some(
            ({ id, box }) =>
                id !== link.from &&
                id !== link.to &&
                Math.max(p.x, q.x) > box.x &&
                Math.min(p.x, q.x) < box.x + box.w &&
                Math.max(p.y, q.y) > box.y &&
                Math.min(p.y, q.y) < box.y + box.h,
        );
    });
}
