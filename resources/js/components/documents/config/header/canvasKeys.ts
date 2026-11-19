import type { Selection } from '@/components/documents/config/header/useHeaderEditor';
import type { HeaderLayout } from '@/lib/headerGeometry';

const MOVES: Record<string, [number, number]> = {
    ArrowLeft: [0, -1],
    ArrowRight: [0, 1],
    ArrowUp: [-1, 0],
    ArrowDown: [1, 0],
};

/**
 * Keyboard on a canvas cell: arrows move to the neighbour cell (left/right in
 * the row, up/down across rows), Enter or Space selects the cell. The cell that
 * should now have focus is focused here; null means the key is not ours.
 */
export function nextCellSelection(
    event: KeyboardEvent,
    layout: HeaderLayout,
    row: number,
    cell: number,
): Selection | null {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();

        return { kind: 'cell', row, cell };
    }

    const move = MOVES[event.key];

    if (!move) return null;

    event.preventDefault();

    const nextRow = Math.min(
        layout.rows.length - 1,
        Math.max(0, row + move[0]),
    );
    const last = layout.rows[nextRow].cells.length - 1;
    const nextCell = Math.min(
        last,
        Math.max(0, move[0] === 0 ? cell + move[1] : cell),
    );
    const svg = (event.currentTarget as SVGElement | null)?.ownerSVGElement;

    queueMicrotask(() =>
        svg
            ?.querySelector<SVGElement>(`[data-cell="${nextRow}-${nextCell}"]`)
            ?.focus(),
    );

    return { kind: 'cell', row: nextRow, cell: nextCell };
}
