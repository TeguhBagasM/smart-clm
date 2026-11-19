import type { FlowSide, FlowStepSize } from '@/types';

/**
 * Sizes of the swimlane grid, in canvas units. Proportions follow the PDF
 * engine (a box narrower than its lane, rows spaced for the line channels).
 */
export const GRID = {
    laneW: 190,
    boxW: 150,
    boxH: 56,
    gap: 44,
    head: 40,
    pad: 12,
};

/** How far a step may be resized, as a factor of the default box (same limits in the engine). */
export const SIZE_LIMITS = { w: [0.5, 1.3], h: [0.6, 3] } as const;

/** Rows the PDF page holds with default margins; more may be cut off. */
export const PRINT_ROWS = 9;

export type Cell = { lane: number; row: number };

export type Rect = { x: number; y: number; w: number; h: number; cx: number };

/** Each row is as tall as its tallest step: `tops[r]` and `heights[r]` include the gap around the boxes. */
export type RowGrid = { tops: number[]; heights: number[] };

const clamp = (value: number, [min, max]: readonly [number, number]) =>
    Math.min(max, Math.max(min, value));

export function clampSize(size: FlowStepSize): FlowStepSize {
    return {
        w: Math.round(clamp(size.w, SIZE_LIMITS.w) * 100) / 100,
        h: Math.round(clamp(size.h, SIZE_LIMITS.h) * 100) / 100,
    };
}

/** Width and height of a step's box: never wider than its lane leaves room for the side corridors. */
export function boxSize(size?: FlowStepSize): { w: number; h: number } {
    const factor = clampSize(size ?? { w: 1, h: 1 });

    return {
        w: Math.min(GRID.boxW * factor.w, GRID.laneW - 28),
        h: GRID.boxH * factor.h,
    };
}

export function laneLeft(lane: number): number {
    return GRID.pad + lane * GRID.laneW;
}

/** Row tops and heights from the tallest box of each row. */
export function rowGrid(tallest: number[], rows: number): RowGrid {
    const tops: number[] = [];
    const heights: number[] = [];
    let y = GRID.pad + GRID.head;

    for (let r = 0; r < rows; r++) {
        tops.push(y);
        heights.push(Math.max(GRID.boxH, tallest[r] ?? 0) + GRID.gap);
        y += heights[r];
    }

    return { tops, heights };
}

/** The box of a step in this cell, centred in its row. */
export function cellBox(
    { lane, row }: Cell,
    grid: RowGrid,
    size?: FlowStepSize,
): Rect {
    const { w, h } = boxSize(size);
    const cx = laneLeft(lane) + GRID.laneW / 2;
    const top = grid.tops[row] ?? GRID.pad + GRID.head;
    const height = grid.heights[row] ?? GRID.boxH + GRID.gap;

    return { x: cx - w / 2, y: top + (height - h) / 2, w, h, cx };
}

/** The cell under a canvas point, or null outside the grid. */
export function cellAt(
    x: number,
    y: number,
    lanes: number,
    grid: RowGrid,
): Cell | null {
    const lane = Math.floor((x - GRID.pad) / GRID.laneW);
    const row = grid.tops.findIndex(
        (top, r) => y >= top && y < top + grid.heights[r],
    );

    if (lane < 0 || lane >= lanes || row < 0) {
        return null;
    }

    return { lane, row };
}

export function canvasSize(
    lanes: number,
    grid: RowGrid,
): { width: number; height: number } {
    const last = grid.tops.length - 1;
    const bottom =
        last >= 0 ? grid.tops[last] + grid.heights[last] : GRID.pad + GRID.head;

    return {
        width: GRID.pad * 2 + lanes * GRID.laneW,
        height: bottom + GRID.pad,
    };
}

/** The middle of one side of a box: where a line leaves or arrives. */
export function portPoint(box: Rect, side: FlowSide): { x: number; y: number } {
    const my = box.y + box.h / 2;

    switch (side) {
        case 'top':
            return { x: box.cx, y: box.y };
        case 'bottom':
            return { x: box.cx, y: box.y + box.h };
        case 'left':
            return { x: box.x, y: my };
        default:
            return { x: box.x + box.w, y: my };
    }
}

export const SIDES: FlowSide[] = ['top', 'right', 'bottom', 'left'];

/** Row band (top, height) of any row, past the last one too. */
function band(grid: RowGrid, row: number): { top: number; height: number } {
    const last = grid.tops.length - 1;

    if (row <= last) {
        return { top: grid.tops[row], height: grid.heights[row] };
    }

    const pitch = GRID.boxH + GRID.gap;
    const end =
        last >= 0 ? grid.tops[last] + grid.heights[last] : GRID.pad + GRID.head;

    return { top: end + (row - last - 1) * pitch, height: pitch };
}

/**
 * A canvas point in grid units (x = lane + fraction of its width, y = row +
 * fraction of its band), the way bend points are stored so the PDF, drawn
 * at another scale, puts them in the same place.
 */
export function toGrid(
    p: { x: number; y: number },
    grid: RowGrid,
): { x: number; y: number } {
    const x = (p.x - GRID.pad) / GRID.laneW;
    let row = 0;

    while (band(grid, row + 1).top <= p.y && row < 249) {
        row += 1;
    }

    const { top, height } = band(grid, row);
    const round = (v: number) => Math.round(v * 1000) / 1000;

    return { x: round(x), y: round(row + Math.max(0, p.y - top) / height) };
}

/** Back from grid units to a canvas point. */
export function fromGrid(
    p: { x: number; y: number },
    grid: RowGrid,
): { x: number; y: number } {
    const row = Math.max(0, Math.floor(p.y));
    const { top, height } = band(grid, row);

    return { x: GRID.pad + p.x * GRID.laneW, y: top + (p.y - row) * height };
}
