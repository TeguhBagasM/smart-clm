import { describe, expect, it } from 'vitest';
import {
    clampHeight,
    clampLogoWidth,
    edgePercent,
    moveDivider,
} from '@/components/documents/config/header/headerDragOps';
import type { HeaderRow } from '@/types';

const cell = (width: number) => ({
    width,
    text: 'x',
    bold: false,
    italic: false,
    uppercase: false,
    align: 'left' as const,
    fontSize: null,
    background: null,
    color: 'auto',
});
const row = (...widths: number[]): HeaderRow => ({
    height: 18,
    cells: widths.map(cell),
});
const widths = (r: HeaderRow): number[] => r.cells.map((c) => c.width);

describe('dragging a divider', () => {
    it('snaps the edge to multiples of 5 and keeps the row at 100', () => {
        expect(widths(moveDivider(row(60, 40), 0, 7))).toEqual([65, 35]);
        expect(widths(moveDivider(row(60, 40), 0, 2))).toEqual([60, 40]);
        expect(widths(moveDivider(row(60, 40), 0, -13))).toEqual([45, 55]);
    });

    it('is free with snap off, and never squeezes a neighbour under 5%', () => {
        expect(widths(moveDivider(row(60, 40), 0, 7.3, 0))).toEqual([
            67.3, 32.7,
        ]);
        expect(widths(moveDivider(row(60, 40), 0, 80))).toEqual([95, 5]);
        expect(widths(moveDivider(row(60, 40), 0, -80))).toEqual([5, 95]);
    });

    it('moves only the two cells beside the divider', () => {
        const moved = moveDivider(row(30, 40, 30), 1, 10);

        expect(widths(moved)).toEqual([30, 50, 20]);
        expect(edgePercent(moved, 1)).toBe(80);
    });
});

describe('limits', () => {
    it('keeps heights in whole points between 12 and 60, and the logo between 10 and 40%', () => {
        expect(clampHeight(3)).toBe(12);
        expect(clampHeight(99)).toBe(60);
        expect(clampHeight(20.6)).toBe(21);
        expect(clampLogoWidth(5)).toBe(10);
        expect(clampLogoWidth(41)).toBe(40);
    });
});
