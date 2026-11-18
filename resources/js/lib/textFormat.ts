export type Edit = { value: string; start: number; end: number };

/** Wraps the selected text (or an empty spot) in `mark`, e.g. **bold**. */
export function wrapSelection(
    el: HTMLTextAreaElement,
    mark: string,
    placeholder: string,
): Edit {
    const { value, selectionStart: start, selectionEnd: end } = el;
    const chosen = value.slice(start, end) || placeholder;
    const next = `${value.slice(0, start)}${mark}${chosen}${mark}${value.slice(end)}`;

    return {
        value: next,
        start: start + mark.length,
        end: start + mark.length + chosen.length,
    };
}

/** Turns every selected line into a "- " list item. */
export function bulletLines(el: HTMLTextAreaElement): Edit {
    const { value, selectionStart: start, selectionEnd: end } = el;
    const from = value.lastIndexOf('\n', start - 1) + 1;
    const toIndex = value.indexOf('\n', end);
    const to = toIndex === -1 ? value.length : toIndex;
    const lines = value
        .slice(from, to)
        .split('\n')
        .map((line) => (line.startsWith('- ') ? line : `- ${line}`));
    const block = lines.join('\n');

    return {
        value: value.slice(0, from) + block + value.slice(to),
        start: from,
        end: from + block.length,
    };
}
