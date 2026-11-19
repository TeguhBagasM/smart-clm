import type { Rect } from '@/components/documents/wizard/flow/flowGeometry';
import type { DrawnEdge } from '@/components/documents/wizard/flow/flowLayout';

export const STEP_MENU_WIDTH = 288;

type Style = { left: string; top: string };

const px = (x: number, y: number): Style => ({ left: `${x}px`, top: `${y}px` });

/** Beside the step, on its left when the right has no room, below it when neither has. */
export function stepMenuStyle(
    box: Rect,
    canvasWidth: number,
    zoom: number,
): Style {
    const right = (box.x + box.w + 14) * zoom;
    const left = (box.x - 14) * zoom - STEP_MENU_WIDTH;

    if (right + STEP_MENU_WIDTH <= canvasWidth * zoom) {
        return px(right, box.y * zoom);
    }

    if (left >= 0) {
        return px(left, box.y * zoom);
    }

    return px(box.x * zoom, (box.y + box.h + 14) * zoom);
}

/** Next to the line's label, or where the line starts. */
export function edgeMenuStyle(edge: DrawnEdge, zoom: number): Style {
    const [x, y] = edge.path.slice(1).split(' ')[0].split(',').map(Number);
    const at = edge.label ?? { x, y };

    return px((at.x + 12) * zoom, (at.y + 8) * zoom);
}
