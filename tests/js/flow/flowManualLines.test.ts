import { describe, expect, it } from 'vitest';
import {
    moveSegment,
    sideOf,
} from '@/components/documents/wizard/flow/flowEdgeMath';
import {
    fromGrid,
    portPoint,
    toGrid,
} from '@/components/documents/wizard/flow/flowGeometry';
import { layoutFlow } from '@/components/documents/wizard/flow/flowLayout';
import type { DrawnEdge } from '@/components/documents/wizard/flow/flowLayout';
import {
    retarget,
    setPorts,
    setRoute,
} from '@/components/documents/wizard/flow/flowLinkOps';
import type { DraftFlow, FlowStep } from '@/types';

const step = (
    id: string,
    actor: string,
    row: number,
    extra: Partial<FlowStep> = {},
): FlowStep => ({
    id,
    order: 0,
    type: 'process',
    text: id,
    actor,
    row,
    ...extra,
});
const flow = (steps: FlowStep[], lanes = ['A', 'B', 'C']): DraftFlow => ({
    mode: 'builder',
    style: 'diagram',
    lanes,
    has_image: false,
    steps,
});
const orthogonal = (edge: DrawnEdge) =>
    edge.points
        .slice(1)
        .every((q, i) => q.x === edge.points[i].x || q.y === edge.points[i].y);

describe('dragging a segment', () => {
    it('moves an inner segment across its direction, keeping the line square', () => {
        const pts = [
            { x: 0, y: 0 },
            { x: 0, y: 50 },
            { x: 100, y: 50 },
            { x: 100, y: 100 },
        ];

        expect(moveSegment(pts, 1, { x: 40, y: 70 })).toEqual([
            { x: 0, y: 0 },
            { x: 0, y: 70 },
            { x: 100, y: 70 },
            { x: 100, y: 100 },
        ]);
    });

    it('keeps both ends on their steps when the only segment moves', () => {
        const moved = moveSegment(
            [
                { x: 0, y: 0 },
                { x: 0, y: 90 },
            ],
            0,
            { x: 40, y: 45 },
        );

        expect(moved[0]).toEqual({ x: 0, y: 0 });
        expect(moved.at(-1)).toEqual({ x: 0, y: 90 });
        expect(moved.some((p) => p.x === 40)).toBe(true);
        expect(
            moved
                .slice(1)
                .every((q, i) => q.x === moved[i].x || q.y === moved[i].y),
        ).toBe(true);
    });
});

describe('grid units', () => {
    const layout = layoutFlow(
        ['A', 'B'],
        [step('a', 'A', 0, { size: { w: 1, h: 2 } }), step('b', 'B', 2)],
    );

    it('turns a canvas point into lane and row fractions and back', () => {
        for (const p of [
            { x: 120, y: 90 },
            { x: 300, y: 260 },
            { x: 50, y: 700 },
        ]) {
            const back = fromGrid(toGrid(p, layout.grid), layout.grid);

            expect(back.x).toBeCloseTo(p.x, 0);
            expect(back.y).toBeCloseTo(p.y, 0);
        }
    });

    it('names the side of a box a line end touches', () => {
        const box = layout.steps[1].box;

        expect(sideOf(box, portPoint(box, 'left'))).toBe('left');
        expect(sideOf(box, portPoint(box, 'top'))).toBe('top');
    });
});

describe('a line bent by hand', () => {
    const base = [
        step('a', 'A', 0, { nextId: 'c' }),
        step('m', 'B', 1),
        step('c', 'A', 3),
    ];
    const auto = layoutFlow(['A', 'B', 'C'], base);
    const drawn = auto.edges[0];
    const bent = moveSegment(drawn.points, 0, {
        x: drawn.points[0].x + 300,
        y: 0,
    });
    const stored = bent.slice(1, -1).map((p) => toGrid(p, auto.grid));
    const edited = setRoute('a', 'next', stored, {
        exit: 'bottom',
        entry: 'top',
    })(flow(base));

    it('is drawn through the stored bends and stays attached to both steps', () => {
        const layout = layoutFlow(['A', 'B', 'C'], edited?.steps ?? []);
        const edge = layout.edges[0];

        expect(edge.manual).toBe(true);
        expect(orthogonal(edge)).toBe(true);
        expect(edge.points[0]).toEqual(
            portPoint(layout.steps[0].box, 'bottom'),
        );
        expect(edge.points.at(-1)).toEqual(
            portPoint(layout.steps[2].box, 'top'),
        );
        expect(Math.max(...edge.points.map((p) => p.x))).toBeCloseTo(
            drawn.points[0].x + 300,
            0,
        );
    });

    it('follows its steps when they move, still square', () => {
        const moved = (edited?.steps ?? []).map((s) =>
            s.id === 'c' ? { ...s, actor: 'C', row: 4 } : s,
        );
        const edge = layoutFlow(['A', 'B', 'C'], moved).edges[0];

        expect(orthogonal(edge)).toBe(true);
    });

    it('is flagged when it runs through a step it does not connect', () => {
        const through = setRoute(
            'a',
            'next',
            [
                toGrid(
                    { x: auto.steps[1].box.cx, y: auto.steps[0].box.y + 80 },
                    auto.grid,
                ),
                toGrid(
                    { x: auto.steps[1].box.cx, y: auto.steps[2].box.y - 20 },
                    auto.grid,
                ),
            ],
            { exit: 'bottom', entry: 'top' },
        )(flow(base));

        expect(
            layoutFlow(['A', 'B', 'C'], through?.steps ?? []).edges[0].blocked,
        ).toBe(true);
        expect(
            layoutFlow(['A', 'B', 'C'], edited?.steps ?? []).edges[0].blocked,
        ).toBe(false);
    });

    it('goes back to the router when its sides change, and keeps its sides when re-targeted', () => {
        const sides = setPorts('a', 'next', { exit: 'right' })(
            edited as DraftFlow,
        );
        expect(sides?.steps[0].ports?.next).toEqual({
            exit: 'right',
            entry: 'top',
        });

        const moved = retarget('a', 'next', 'm', 'left')(edited as DraftFlow);
        expect(moved?.steps[0]).toMatchObject({
            nextId: 'm',
            ports: { next: { exit: 'bottom', entry: 'left' } },
        });
        expect(moved?.steps[0].ports?.next?.points).toBeUndefined();
        expect(retarget('a', 'next', 'a')(edited as DraftFlow)).toBeNull();
    });
});
