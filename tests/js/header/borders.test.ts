import { describe, expect, it } from 'vitest';
import {
    ALL_SIDES,
    NO_SIDES,
    resolveBorders,
    sidesPath,
} from '@/components/documents/config/header/headerBorders';

const box = (x: number, y: number, w: number, h: number, want = ALL_SIDES) => ({
    x,
    y,
    w,
    h,
    want,
});

describe('letterhead lines', () => {
    it('draws every side when every box wants it', () => {
        expect(resolveBorders([box(0, 0, 50, 20), box(50, 0, 50, 20)])).toEqual(
            [ALL_SIDES, ALL_SIDES],
        );
    });

    it('hides a shared side when either box hides it', () => {
        expect(
            resolveBorders([box(0, 0, 50, 20, NO_SIDES), box(50, 0, 50, 20)]),
        ).toEqual([NO_SIDES, { ...ALL_SIDES, left: false }]);
    });

    it('keeps outer sides and hides the side shared below', () => {
        const sides = resolveBorders([
            box(0, 0, 100, 20),
            box(0, 20, 100, 12, { ...NO_SIDES, bottom: true }),
        ]);

        expect(sides[0]).toEqual({ ...ALL_SIDES, bottom: false });
        expect(sides[1]).toEqual({ ...NO_SIDES, bottom: true });
    });

    it('draws only the wanted sides as path segments', () => {
        expect(sidesPath({ x: 0, y: 0, w: 10, h: 5 }, NO_SIDES)).toBe('');
        expect(
            sidesPath(
                { x: 0, y: 0, w: 10, h: 5 },
                { ...NO_SIDES, bottom: true },
            ),
        ).toBe('M0 5H10');
    });
});
