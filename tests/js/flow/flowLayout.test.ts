import { describe, expect, it } from 'vitest';
import { layoutFlow } from '@/components/documents/wizard/flow/flowLayout';
import type { FlowStep } from '@/types';

/** Points of an SVG path made of M/L commands. */
function points(path: string): { x: number; y: number }[] {
    return path.split(' ').map((part) => {
        const [x, y] = part.slice(1).split(',').map(Number);

        return { x, y };
    });
}

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

describe('lines', () => {
    const steps = [
        step('a', 'A', 0, { type: 'start', nextId: 'b' }),
        step('b', 'B', 1, { type: 'decision', yesId: 'c', noId: 'a' }),
        step('c', 'A', 2, { nextId: 'd' }),
        step('blocker', 'A', 1),
        step('d', 'A', 4, { type: 'end' }),
    ];
    const layout = layoutFlow(['A', 'B'], steps);

    it('draws every connection, with Ya and Tidak on the decision', () => {
        expect(layout.edges.map((edge) => `${edge.from}>${edge.to}`)).toEqual([
            'a>b',
            'b>c',
            'c>d',
            'b>a',
        ]);
        expect(
            layout.edges
                .filter((edge) => edge.label)
                .map((edge) => edge.label?.text),
        ).toEqual(['Ya', 'Tidak']);
    });

    it('only bends at right angles', () => {
        for (const edge of layout.edges) {
            const pts = points(edge.path);

            pts.slice(1).forEach((p, i) =>
                expect(p.x === pts[i].x || p.y === pts[i].y).toBe(true),
            );
        }
    });

    it('never runs through a symbol it does not connect', () => {
        for (const edge of layout.edges) {
            const pts = points(edge.path);
            const others = layout.steps.filter(
                (s) => s.id !== edge.from && s.id !== edge.to,
            );

            pts.slice(1).forEach((q, i) => {
                const p = pts[i];

                for (const s of others) {
                    const crossesX =
                        Math.max(p.x, q.x) > s.box.x &&
                        Math.min(p.x, q.x) < s.box.x + s.box.w;
                    const crossesY =
                        Math.max(p.y, q.y) > s.box.y &&
                        Math.min(p.y, q.y) < s.box.y + s.box.h;

                    expect(
                        crossesX && crossesY,
                        `${edge.from}>${edge.to} crosses ${s.id}`,
                    ).toBe(false);
                }
            });
        }
    });

    it('ends every line on the top of its target, or its side when beside it', () => {
        const target = layout.steps.find((s) => s.id === 'c');
        const end = points(layout.edges[1].path).at(-1);

        expect(end).toEqual({ x: target?.box.cx, y: target?.box.y });
    });

    it('keeps one spare row below the last step', () => {
        expect(layout.rows).toBe(6);
        expect(layoutFlow(['A'], steps, 0).rows).toBe(5);
    });
});

describe('decision branches', () => {
    it('leave the diamond from different points, so Ya and Tidak never overlap', () => {
        const layout = layoutFlow(
            ['Staf', 'Atasan', 'HRD'],
            [
                step('a', 'Staf', 0, { type: 'start', nextId: 'b' }),
                step('b', 'Staf', 1, { nextId: 'c' }),
                step('c', 'Atasan', 3, {
                    type: 'decision',
                    yesId: 'd',
                    noId: 'b',
                }),
                step('d', 'HRD', 3, { type: 'document' }),
            ],
        );
        const starts = layout.edges
            .filter((edge) => edge.from === 'c')
            .map((edge) => points(edge.path)[0]);

        expect(starts).toHaveLength(2);
        expect(starts[0]).not.toEqual(starts[1]);
    });
});

/** Straight pieces of every line, to compare lines with each other. */
function segments(path: string) {
    const pts = points(path);

    return pts.slice(1).map((q, i) => ({ p: pts[i], q }));
}

describe('lines that meet the same step (the screenshot case)', () => {
    const layout = layoutFlow(
        ['Penyusun', 'Mengetahui'],
        [
            step('a', 'Penyusun', 0, { type: 'start', nextId: 'b' }),
            step('b', 'Penyusun', 1, { nextId: 'c' }),
            step('c', 'Penyusun', 2, {
                type: 'decision',
                yesId: 'd',
                noId: 'b',
            }),
            step('d', 'Mengetahui', 4),
        ],
    );

    it('never lets two lines run along the same stretch', () => {
        const all = layout.edges.map((edge) => ({
            id: `${edge.from}>${edge.to}`,
            segs: segments(edge.path),
        }));

        for (const one of all) {
            for (const other of all.filter((item) => item.id !== one.id)) {
                for (const a of one.segs) {
                    for (const b of other.segs) {
                        const sameRow =
                            a.p.y === a.q.y &&
                            b.p.y === b.q.y &&
                            a.p.y === b.p.y;
                        const sameCol =
                            a.p.x === a.q.x &&
                            b.p.x === b.q.x &&
                            a.p.x === b.p.x;
                        const lo = (s: typeof a, k: 'x' | 'y') =>
                            Math.min(s.p[k], s.q[k]);
                        const hi = (s: typeof a, k: 'x' | 'y') =>
                            Math.max(s.p[k], s.q[k]);
                        const k = sameRow ? 'x' : 'y';
                        const shared =
                            (sameRow || sameCol) &&
                            Math.min(hi(a, k), hi(b, k)) -
                                Math.max(lo(a, k), lo(b, k)) >
                                1;

                        expect(shared, `${one.id} and ${other.id}`).toBe(false);
                    }
                }
            }
        }
    });

    it('brings the Tidak loop back into the side of the step, not on top of the arrow from Mulai', () => {
        const target = layout.steps.find((s) => s.id === 'b');
        const loop = layout.edges.find((edge) => edge.kind === 'no');
        const end = points(loop?.path ?? '').at(-1);

        expect(end?.x).toBe((target?.box.x ?? 0) + (target?.box.w ?? 0));
    });

    it('follows the sides the author picked', () => {
        const picked = layoutFlow(
            ['Penyusun', 'Mengetahui'],
            [
                step('a', 'Penyusun', 0, { type: 'start', nextId: 'b' }),
                step('b', 'Penyusun', 1, { nextId: 'c' }),
                step('c', 'Penyusun', 2, {
                    type: 'decision',
                    yesId: 'd',
                    noId: 'b',
                    ports: { no: { exit: 'left', entry: 'left' } },
                }),
                step('d', 'Mengetahui', 4),
            ],
        );
        const loop = picked.edges.find((edge) => edge.kind === 'no');
        const pts = points(loop?.path ?? '');
        const from = picked.steps.find((s) => s.id === 'c');
        const to = picked.steps.find((s) => s.id === 'b');

        expect(pts[0].x).toBe(from?.box.x);
        expect(pts.at(-1)?.x).toBe(to?.box.x);
    });
});
