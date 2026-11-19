import type { Rect } from '@/components/documents/wizard/flow/flowGeometry';

/** Wraps step text into the lines that fit a box (about 6.8 units per character, 14 per line). */
export function wrapText(
    text: string | null,
    box?: Pick<Rect, 'w' | 'h'>,
): string[] {
    const width = box ? Math.max(6, Math.floor((box.w - 10) / 6.8)) : 22;
    const max = box ? Math.max(1, Math.floor((box.h - 10) / 14)) : 3;
    const words = (text ?? '').split(/\s+/).filter(Boolean);
    const lines: string[] = [];

    for (const word of words) {
        const last = lines.at(-1);

        if (last && `${last} ${word}`.length <= width) {
            lines[lines.length - 1] = `${last} ${word}`;
        } else {
            lines.push(word);
        }
    }

    if (lines.length > max) {
        lines.length = max;
        lines[max - 1] = `${lines[max - 1].slice(0, width - 1)}…`;
    }

    return lines;
}
