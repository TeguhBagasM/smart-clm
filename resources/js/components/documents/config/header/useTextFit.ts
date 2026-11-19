let context: CanvasRenderingContext2D | null | undefined;

function measure(text: string, font: string): number {
    context ??= document.createElement('canvas').getContext('2d');

    if (!context) return text.length * 5;

    context.font = font;

    return context.measureText(text).width;
}

/**
 * The text as the PDF engine prints it: cut with an ellipsis until it fits
 * `maxWidth` (the same loop as Node's `fit`), plus whether it had to be cut.
 * Widths come from the browser's Helvetica/Arial, which match the PDF font.
 */
export function fitText(
    text: string,
    size: number,
    bold: boolean,
    italic: boolean,
    maxWidth: number,
): { text: string; cut: boolean } {
    const font = `${italic ? 'italic ' : ''}${bold ? 'bold ' : ''}${size}px Helvetica, Arial, sans-serif`;

    if (measure(text, font) <= maxWidth) return { text, cut: false };

    let result = text;

    while (result.length > 1 && measure(`${result}…`, font) > maxWidth) {
        result = result.slice(0, -1);
    }

    return { text: `${result}…`, cut: true };
}
