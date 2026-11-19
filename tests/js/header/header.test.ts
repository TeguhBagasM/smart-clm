import { describe, expect, it } from 'vitest';
import {
    addCell,
    addCellAfter,
    removeCell,
    setCellWidth,
} from '@/components/documents/config/header/headerOps';
import {
    hexToHueSat,
    hslToHex,
    normalizeHex,
    safeColor,
} from '@/components/documents/config/header/color/colorMath';
import {
    autoTone,
    computeGeometry,
    contrastRatio,
    fitLogo,
    parseColor,
    resolveText,
    type HeaderLayout,
} from '@/lib/headerGeometry';
import type { HeaderRow } from '@/types';

const cell = (width: number, text = 'x') => ({
    width,
    text,
    bold: false,
    italic: false,
    uppercase: false,
    align: 'left' as const,
    fontSize: null,
    background: null,
    color: 'auto',
});
const sum = (row: HeaderRow): number =>
    Math.round(row.cells.reduce((s, c) => s + c.width, 0) * 100) / 100;

describe('row widths', () => {
    const row: HeaderRow = { height: 18, cells: [cell(60), cell(40)] };

    it('keeps a row at 100 when a width changes, adds and removes a column', () => {
        expect(sum(setCellWidth(row, 0, 75))).toBe(100);
        expect(setCellWidth(row, 0, 75).cells[1].width).toBe(25);
        expect(setCellWidth(row, 1, 70).cells[0].width).toBe(30);
        expect(setCellWidth(row, 0, 99).cells[1].width).toBe(5);
        expect(sum(addCell(row))).toBe(100);
        expect(addCell(row).cells).toHaveLength(3);
        expect(removeCell(addCell(row), 2).cells.map((c) => c.width)).toEqual([
            60, 40,
        ]);
    });
});

describe('adding a column beside a cell', () => {
    it('splits the chosen cell and puts the new one to its right', () => {
        const next = addCellAfter(
            { height: 18, cells: [cell(60), cell(40)] },
            0,
        );

        expect(next.cells.map((c) => c.width)).toEqual([30, 30, 40]);
    });
});

describe('colours', () => {
    it('reads only valid hex', () => {
        expect(parseColor('#12AB34')).toMatchObject({
            r: 18,
            g: 171,
            b: 52,
            a: 1,
        });
        expect(parseColor('#FF880080')?.a).toBeCloseTo(0.5, 1);
        expect(parseColor('red')).toBeNull();
        expect(safeColor('url(x)')).toBeUndefined();
        expect(normalizeHex('abc')).toBe('#AABBCC');
        expect(normalizeHex('#12ab34')).toBe('#12AB34');
        expect(normalizeHex('12')).toBeNull();
    });

    it('converts between hsl and hex and rates contrast', () => {
        expect(hslToHex(hexToHueSat('#336699'))).toBe('#336699');
        expect(
            contrastRatio(parseColor('#000000')!, parseColor('#FFFFFF')!),
        ).toBeGreaterThan(20);
        expect(
            contrastRatio(parseColor('#777777')!, parseColor('#888888')!),
        ).toBeLessThan(4.5);
        expect(autoTone('#243661')).toBe('light');
        expect(autoTone('#EBEDF2')).toBe('dark');
    });
});

describe('geometry', () => {
    const layout: HeaderLayout = {
        version: 2,
        border: '#404552',
        logo: {
            enabled: true,
            source: 'company',
            assetId: null,
            side: 'right',
            rows: 2,
            width: 25,
            valign: 'top',
            padding: 6,
        },
        rows: [
            { height: 20, cells: [cell(100)] },
            { height: 18, cells: [cell(60), cell(40)] },
            { height: 18, cells: [cell(100)] },
        ],
    };

    it('puts the logo beside the first rows and shortens those rows', () => {
        const geo = computeGeometry(layout, 400);

        expect(geo.height).toBe(56);
        expect(geo.logo).toEqual({ x: 300, y: 0, w: 100, h: 38 });
        expect(geo.cells[0].w).toBe(300);
        expect(geo.cells[1].w).toBe(180);
        expect(geo.cells[3].w).toBe(400);
    });

    it('fits a logo without distortion, and fills in text with the stamp left for later', () => {
        expect(
            fitLogo({ x: 0, y: 0, w: 100, h: 42 }, 6, 200, 50, 'top'),
        ).toEqual({ x: 6, y: 6, w: 88, h: 22 });

        const c = { ...cell(100, 'Status : {status}') };

        expect(resolveText(c, {}, 1, 3)).toEqual({
            text: 'Status : ',
            reactive: 'status',
        });
        expect(
            resolveText(
                { ...c, text: 'Hal {page} {title}', uppercase: true },
                { title: 'abc' },
                2,
                3,
            ).text,
        ).toBe('HAL PAGE 2 OF 3 ABC');
    });
});
