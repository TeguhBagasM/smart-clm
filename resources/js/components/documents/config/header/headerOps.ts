import type { HeaderCell, HeaderRow } from '@/types';

const MIN_WIDTH = 5;

const round = (value: number): number => Math.round(value * 100) / 100;

/** A cell for a new row or column: a sensible text, not an empty box. */
export function newCell(width: number, text = 'Teks'): HeaderCell {
    return {
        width,
        text,
        bold: false,
        italic: false,
        uppercase: false,
        align: 'left',
        fontSize: null,
        background: null,
        color: 'auto',
    };
}

export function newRow(): HeaderRow {
    return { height: 18, cells: [newCell(100)] };
}

/**
 * Sets the width of one cell and takes (or gives) the difference from its
 * neighbour, so a row always adds up to 100.
 */
export function setCellWidth(
    row: HeaderRow,
    index: number,
    wanted: number,
): HeaderRow {
    if (row.cells.length < 2) return row;

    const other = index < row.cells.length - 1 ? index + 1 : index - 1;
    const pair = row.cells[index].width + row.cells[other].width;
    const width = round(
        Math.min(pair - MIN_WIDTH, Math.max(MIN_WIDTH, wanted)),
    );

    return {
        ...row,
        cells: row.cells.map((cell, position) =>
            position === index
                ? { ...cell, width }
                : position === other
                  ? { ...cell, width: round(pair - width) }
                  : cell,
        ),
    };
}

/** Adds a cell to the right by splitting the last one in half. */
export function addCell(row: HeaderRow): HeaderRow {
    const last = row.cells[row.cells.length - 1];
    const half = round(last.width / 2);

    return {
        ...row,
        cells: [
            ...row.cells.slice(0, -1),
            { ...last, width: round(last.width - half) },
            newCell(half),
        ],
    };
}

/** Adds a cell to the right of `index` by splitting that cell in half. */
export function addCellAfter(row: HeaderRow, index: number): HeaderRow {
    const half = round(row.cells[index].width / 2);
    const cells = [...row.cells];
    cells[index] = { ...cells[index], width: round(cells[index].width - half) };
    cells.splice(index + 1, 0, newCell(half));

    return { ...row, cells };
}

/** Removes a cell; its neighbour takes the room. */
export function removeCell(row: HeaderRow, index: number): HeaderRow {
    if (row.cells.length < 2) return row;

    const other = index > 0 ? index - 1 : 1;

    return {
        ...row,
        cells: row.cells
            .map((cell, position) =>
                position === other
                    ? {
                          ...cell,
                          width: round(cell.width + row.cells[index].width),
                      }
                    : cell,
            )
            .filter((_, position) => position !== index),
    };
}
