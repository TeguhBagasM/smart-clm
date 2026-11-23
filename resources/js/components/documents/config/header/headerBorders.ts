import type { Box } from '@/lib/headerGeometry';
import type { HeaderSides } from '@/types';

const EPSILON = 0.01;
const OPPOSITE = {
    top: 'bottom',
    bottom: 'top',
    left: 'right',
    right: 'left',
} as const;

export const ALL_SIDES: HeaderSides = {
    top: true,
    right: true,
    bottom: true,
    left: true,
};
export const NO_SIDES: HeaderSides = {
    top: false,
    right: false,
    bottom: false,
    left: false,
};

export type BorderBox = Box & { want: HeaderSides };

const overlap = (a0: number, a1: number, b0: number, b1: number): boolean =>
    Math.min(a1, b1) - Math.max(a0, b0) > EPSILON;

/** Whether `b` lies against the `side` edge of `a`, sharing some length of it. */
function touches(a: Box, b: Box, side: keyof HeaderSides): boolean {
    const sharesX = overlap(a.x, a.x + a.w, b.x, b.x + b.w);
    const sharesY = overlap(a.y, a.y + a.h, b.y, b.y + b.h);

    if (side === 'right')
        return Math.abs(b.x - (a.x + a.w)) < EPSILON && sharesY;
    if (side === 'left') return Math.abs(b.x + b.w - a.x) < EPSILON && sharesY;
    if (side === 'bottom')
        return Math.abs(b.y - (a.y + a.h)) < EPSILON && sharesX;

    return Math.abs(b.y + b.h - a.y) < EPSILON && sharesX;
}

/**
 * The sides drawn for each box. A side shared with another box is drawn only
 * when both boxes want it. Same rule as App\Support\Dcs\Pdf\Php\Compose\HeaderBorders.
 */
export function resolveBorders(boxes: BorderBox[]): HeaderSides[] {
    return boxes.map((box, i) => {
        const sides = { ...NO_SIDES };

        for (const side of Object.keys(OPPOSITE) as (keyof HeaderSides)[]) {
            sides[side] =
                box.want[side] &&
                !boxes.some(
                    (other, j) =>
                        j !== i &&
                        !other.want[OPPOSITE[side]] &&
                        touches(box, other, side),
                );
        }

        return sides;
    });
}

/** SVG path drawing the wanted sides of a box (open segments, so a missing side leaves a gap). */
export function sidesPath(box: Box, sides: HeaderSides): string {
    const { x, y, w, h } = box;
    const segments: string[] = [];

    if (sides.top) segments.push(`M${x} ${y}H${x + w}`);
    if (sides.right) segments.push(`M${x + w} ${y}V${y + h}`);
    if (sides.bottom) segments.push(`M${x} ${y + h}H${x + w}`);
    if (sides.left) segments.push(`M${x} ${y}V${y + h}`);

    return segments.join('');
}
