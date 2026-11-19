import { computed, ref, type Ref } from 'vue';
import {
    addCell,
    newRow,
    removeCell,
    setCellWidth,
} from '@/components/documents/config/header/headerOps';
import { moveItem, removeItem } from '@/lib/arrays';
import type { HeaderCell, HeaderLayout, HeaderRow } from '@/types';

export type Selection =
    | { kind: 'header' }
    | { kind: 'logo' }
    | { kind: 'cell'; row: number; cell: number };

/** The letterhead being edited and what is selected on the canvas. */
export function useHeaderEditor(layout: Ref<HeaderLayout>) {
    const selected = ref<Selection>({ kind: 'header' });

    /** The selection, or the whole header when what it pointed at is gone. */
    const selection = computed<Selection>(() => {
        const s = selected.value;

        if (s.kind === 'cell' && !layout.value.rows[s.row]?.cells[s.cell])
            return { kind: 'header' };
        if (s.kind === 'logo' && !layout.value.logo.enabled)
            return { kind: 'header' };

        return s;
    });
    const cell = computed(() => {
        const s = selection.value;

        return s.kind === 'cell'
            ? layout.value.rows[s.row].cells[s.cell]
            : null;
    });

    const setRows = (rows: HeaderRow[]): void => {
        layout.value = { ...layout.value, rows };
    };
    const setRow = (index: number, row: HeaderRow): void =>
        setRows(layout.value.rows.map((r, i) => (i === index ? row : r)));

    return {
        selection,
        cell,
        select: (next: Selection): void => {
            selected.value = next;
        },
        patchCell(values: Partial<HeaderCell>): void {
            const s = selection.value;

            if (s.kind !== 'cell') return;

            const row = layout.value.rows[s.row];
            setRow(s.row, {
                ...row,
                cells: row.cells.map((c, i) =>
                    i === s.cell ? { ...c, ...values } : c,
                ),
            });
        },
        setWidth(value: number): void {
            const s = selection.value;

            if (s.kind === 'cell')
                setRow(
                    s.row,
                    setCellWidth(layout.value.rows[s.row], s.cell, value),
                );
        },
        setHeight(row: number, height: number): void {
            setRow(row, { ...layout.value.rows[row], height });
        },
        addColumn: (row: number): void =>
            setRow(row, addCell(layout.value.rows[row])),
        /** Removes the selected cell; the last cell of a row takes the row with it. */
        deleteCell(): void {
            const s = selection.value;

            if (s.kind !== 'cell') return;

            const row = layout.value.rows[s.row];

            if (row.cells.length > 1) setRow(s.row, removeCell(row, s.cell));
            else if (layout.value.rows.length > 1)
                setRows(removeItem(layout.value.rows, s.row));
        },
        canDelete: computed(() => {
            const s = selection.value;

            return (
                s.kind === 'cell' &&
                (layout.value.rows[s.row].cells.length > 1 ||
                    layout.value.rows.length > 1)
            );
        }),
        addRowAfter(row: number): void {
            const rows = [...layout.value.rows];
            rows.splice(row + 1, 0, newRow());
            setRows(rows);
            selected.value = { kind: 'cell', row: row + 1, cell: 0 };
        },
        moveRow(row: number, step: -1 | 1): void {
            setRows(moveItem(layout.value.rows, row, step));
            selected.value = { kind: 'cell', row: row + step, cell: 0 };
        },
    };
}
