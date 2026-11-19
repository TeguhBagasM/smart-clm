import type { HeaderLayout, HeaderRow } from '@/types';

const MIN_WIDTH = 5;
const MIN_HEIGHT = 12;
const MAX_HEIGHT = 60;
const round = (value: number): number => Math.round(value * 100) / 100;

/**
 * Moves the divider after cell `index` by `delta` percent of the row. The edge
 * snaps to a multiple of `snap` (5 by default; 0 = free, as with Shift held).
 * The cells either side keep at least 5% and the row stays at 100.
 */
export function moveDivider(
    row: HeaderRow,
    index: number,
    delta: number,
    snap = 5,
): HeaderRow {
    const before = row.cells.slice(0, index).reduce((s, c) => s + c.width, 0);
    const start = before + row.cells[index].width;
    const end = start + row.cells[index + 1].width;
    const wanted = start + delta;
    const snapped = snap > 0 ? Math.round(wanted / snap) * snap : wanted;
    const edge = Math.min(
        end - MIN_WIDTH,
        Math.max(before + MIN_WIDTH, snapped),
    );

    return {
        ...row,
        cells: row.cells.map((cell, i) =>
            i === index
                ? { ...cell, width: round(edge - before) }
                : i === index + 1
                  ? { ...cell, width: round(end - edge) }
                  : cell,
        ),
    };
}

/** A fixed row height in points, whole points between 12 and 60. */
export function clampHeight(height: number): number {
    return Math.min(MAX_HEIGHT, Math.max(MIN_HEIGHT, Math.round(height)));
}

/** Logo width as percent of the header, 10 to 40. */
export function clampLogoWidth(width: number): number {
    return Math.min(40, Math.max(10, Math.round(width)));
}

/** The edge after `index` as percent of its row, for screen readers. */
export function edgePercent(row: HeaderRow, index: number): number {
    return round(
        row.cells.slice(0, index + 1).reduce((s, c) => s + c.width, 0),
    );
}

export function setRowAt(
    layout: HeaderLayout,
    index: number,
    row: HeaderRow,
): HeaderLayout {
    return {
        ...layout,
        rows: layout.rows.map((r, i) => (i === index ? row : r)),
    };
}
