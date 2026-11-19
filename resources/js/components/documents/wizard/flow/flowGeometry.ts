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

export const ROW_PITCH = GRID.boxH + GRID.gap;

/** Rows the PDF page holds with default margins; more may be cut off. */
export const PRINT_ROWS = 9;

export type Cell = { lane: number; row: number };

export type Rect = { x: number; y: number; w: number; h: number; cx: number };

export function laneLeft(lane: number): number {
    return GRID.pad + lane * GRID.laneW;
}

export function rowTop(row: number): number {
    return GRID.pad + GRID.head + GRID.gap / 2 + row * ROW_PITCH;
}

/** The box of a step standing in this cell. */
export function cellBox({ lane, row }: Cell): Rect {
    const cx = laneLeft(lane) + GRID.laneW / 2;

    return {
        x: cx - GRID.boxW / 2,
        y: rowTop(row),
        w: GRID.boxW,
        h: GRID.boxH,
        cx,
    };
}

/** The cell under a canvas point, or null outside the grid. */
export function cellAt(
    x: number,
    y: number,
    lanes: number,
    rows: number,
): Cell | null {
    const lane = Math.floor((x - GRID.pad) / GRID.laneW);
    const row = Math.floor((y - GRID.pad - GRID.head) / ROW_PITCH);

    if (lane < 0 || lane >= lanes || row < 0 || row >= rows) {
        return null;
    }

    return { lane, row };
}

export function canvasSize(
    lanes: number,
    rows: number,
): { width: number; height: number } {
    return {
        width: GRID.pad * 2 + lanes * GRID.laneW,
        height: GRID.pad * 2 + GRID.head + rows * ROW_PITCH,
    };
}
