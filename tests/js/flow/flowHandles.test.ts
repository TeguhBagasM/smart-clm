import { describe, expect, it } from 'vitest';
import {
    boxSize,
    cellAt,
    clampSize,
    GRID,
    portPoint,
} from '@/components/documents/wizard/flow/flowGeometry';
import {
    nearestPort,
    resizedSize,
} from '@/components/documents/wizard/flow/flowHandleMath';
import { layoutFlow } from '@/components/documents/wizard/flow/flowLayout';
import { connectFrom } from '@/components/documents/wizard/flow/flowLinkOps';
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

describe('resizing', () => {
    it('keeps sizes inside the limits and never wider than the lane allows', () => {
        expect(clampSize({ w: 9, h: 0.1 })).toEqual({ w: 1.3, h: 0.6 });
        expect(boxSize({ w: 1.3, h: 1 }).w).toBeLessThanOrEqual(
            GRID.laneW - 28,
        );
    });

    it('grows twice the pointer travel because the box stays centred', () => {
        const size = resizedSize(
            { w: GRID.boxW, h: GRID.boxH },
            { x: 0, y: 0 },
            { x: 0, y: GRID.boxH / 2 },
        );

        expect(size).toEqual({ w: 1, h: 2 });
    });

    it('makes a row as tall as its tallest step and keeps every box centred in its row', () => {
        const layout = layoutFlow(
            ['A', 'B'],
            [
                step('a', 'A', 0, { size: { w: 1, h: 2 } }),
                step('b', 'B', 0),
                step('c', 'A', 1),
            ],
        );
        const [a, b, c] = layout.steps;

        expect(layout.grid.heights[0]).toBe(GRID.boxH * 2 + GRID.gap);
        expect(a.box.y + a.box.h / 2).toBe(b.box.y + b.box.h / 2);
        expect(c.box.y).toBeGreaterThan(a.box.y + a.box.h);
        expect(cellAt(b.box.cx, b.box.y + b.box.h / 2, 2, layout.grid)).toEqual(
            { lane: 1, row: 0 },
        );
        expect(cellAt(c.box.cx, c.box.y + 1, 2, layout.grid)).toEqual({
            lane: 0,
            row: 1,
        });
    });
});

describe('connection points', () => {
    const layout = layoutFlow(
        ['A', 'B'],
        [step('a', 'A', 0), step('b', 'B', 1)],
    );
    const [a, b] = layout.steps;

    it('snaps a drop near a point of another step to that side', () => {
        const left = portPoint(b.box, 'left');

        expect(
            nearestPort(layout.steps, { x: left.x + 5, y: left.y - 4 }, 'a'),
        ).toEqual({ id: 'b', side: 'left' });
        expect(
            nearestPort(layout.steps, { x: left.x + 60, y: left.y }, 'a'),
        ).toBeNull();
        expect(
            nearestPort(layout.steps, portPoint(a.box, 'top'), 'a'),
        ).toBeNull();
    });

    it('draws a line between the two chosen sides, and the router follows them', () => {
        const flow: DraftFlow = {
            mode: 'builder',
            style: 'diagram',
            lanes: ['A', 'B'],
            has_image: false,
            steps: [a, b],
        };
        const linked = connectFrom('a', 'b', { exit: 'right', entry: 'left' })(
            flow,
        );

        expect(linked?.steps[0]).toMatchObject({
            nextId: 'b',
            ports: { next: { exit: 'right', entry: 'left' } },
        });

        const drawn = layoutFlow(['A', 'B'], linked?.steps ?? []);
        const path = drawn.edges[0].path
            .split(' ')
            .map((part) => part.slice(1).split(',').map(Number));

        expect(path[0]).toEqual([
            portPoint(a.box, 'right').x,
            portPoint(a.box, 'right').y,
        ]);
        expect(path.at(-1)).toEqual([
            portPoint(b.box, 'left').x,
            portPoint(b.box, 'left').y,
        ]);
    });

    it('fills Ya then Tidak from a decision, each keeping its own sides', () => {
        let flow: DraftFlow | null = {
            mode: 'builder',
            style: 'diagram',
            lanes: ['A', 'B'],
            has_image: false,
            steps: [
                step('d', 'A', 0, { type: 'decision' }),
                step('y', 'A', 1),
                step('n', 'B', 1),
            ],
        };
        flow = connectFrom('d', 'y', { exit: 'bottom' })(flow);
        flow =
            flow &&
            connectFrom('d', 'n', { exit: 'right', entry: 'top' })(flow);

        expect(flow?.steps[0]).toMatchObject({
            yesId: 'y',
            noId: 'n',
            ports: {
                yes: { exit: 'bottom' },
                no: { exit: 'right', entry: 'top' },
            },
        });
    });
});
