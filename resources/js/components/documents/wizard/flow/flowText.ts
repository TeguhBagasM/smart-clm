/** Wraps step text into at most three lines that fit a box. */
export function wrapText(text: string | null, width = 22): string[] {
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

    if (lines.length > 3) {
        lines.length = 3;
        lines[2] = `${lines[2].slice(0, width - 1)}…`;
    }

    return lines;
}
