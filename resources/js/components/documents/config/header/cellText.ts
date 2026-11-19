import { fitText } from '@/components/documents/config/header/useTextFit';
import { fontSizeOf, type HeaderCell } from '@/lib/headerGeometry';

/** Sample text of a cell: placeholders filled with example values, upper-cased when asked. */
export function sampleText(
    cell: HeaderCell,
    samples: Record<string, string>,
): string {
    const filled = cell.text.replace(
        /\{([A-Za-z]+)\}/g,
        (_m, name: string) => samples[name] ?? '',
    );

    return cell.uppercase ? filled.toUpperCase() : filled;
}

/** What the PDF prints in a cell of `width` x `height` points: cut with an ellipsis when it does not fit. */
export function displayCell(
    cell: HeaderCell,
    samples: Record<string, string>,
    width: number,
    height: number,
): { text: string; cut: boolean } {
    return fitText(
        sampleText(cell, samples),
        fontSizeOf(cell, height),
        cell.bold,
        cell.italic,
        width - 10,
    );
}
