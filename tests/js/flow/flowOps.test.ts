import { describe, expect, it } from 'vitest';
import { placeSteps } from '@/components/documents/wizard/flow/flowGraph';
import {
    addLane,
    appendStep,
    insertRow,
    removeLane,
    removeRow,
    renameLane,
} from '@/components/documents/wizard/flow/flowGridOps';
import {
    setPorts,
    setType,
    swapBranches,
} from '@/components/documents/wizard/flow/flowLinkOps';
import {
    addStep,
    connect,
    disconnect,
    moveStep,
    normalizeOrder,
    removeStep,
    stepAt,
} from '@/components/documents/wizard/flow/flowOps';
import type { DraftFlow, FlowStep } from '@/types';

function flow(
    steps: Partial<FlowStep>[],
    lanes = ['Staf', 'Atasan'],
): DraftFlow {
    return {
        mode: 'builder',
        style: 'diagram',
        lanes,
        has_image: false,
        steps: steps.map((step, i) => ({
            id: `s${i + 1}`,
            order: i + 1,
            type: 'process',
            text: `Langkah ${i + 1}`,
            ...step,
        })),
    };
}

const done = (result: DraftFlow | null): DraftFlow => {
    expect(result).not.toBeNull();

    return result as DraftFlow;
};

describe('placing steps', () => {
    it('adds a step to a free cell, in the lane of that column', () => {
        const next = done(addStep('start', 1, 0, 'new')(flow([])));

        expect(next.steps[0]).toMatchObject({
            id: 'new',
            type: 'start',
            text: 'Mulai',
            actor: 'Atasan',
            row: 0,
        });
    });

    it('refuses to add or move a step onto a taken cell', () => {
        const base = flow([
            { actor: 'Staf', row: 0 },
            { actor: 'Atasan', row: 0 },
        ]);

        expect(addStep('process', 0, 0)(base)).toBeNull();
        expect(moveStep('s1', 1, 0)(base)).toBeNull();
        expect(done(moveStep('s1', 1, 1)(base)).steps[0]).toMatchObject({
            actor: 'Atasan',
            row: 1,
        });
    });

    it('numbers steps top to bottom, then left to right', () => {
        const next = normalizeOrder(
            flow([
                { actor: 'Atasan', row: 1 },
                { actor: 'Atasan', row: 0 },
                { actor: 'Staf', row: 1 },
            ]),
        );

        expect(next.steps.map((step) => `${step.id}:${step.order}`)).toEqual([
            's2:1',
            's3:2',
            's1:3',
        ]);
    });
});

describe('connections', () => {
    it('fills a decision with Ya first, then Tidak', () => {
        let next = flow([{ type: 'decision', row: 0 }, { row: 1 }, { row: 2 }]);
        next = done(connect('s1', 's2')(next));
        next = done(connect('s1', 's3')(next));

        expect(next.steps[0]).toMatchObject({ yesId: 's2', noId: 's3' });
        expect(connect('s1', 's2')(next)).toBeNull();
    });

    it('never links a step to itself or out of an end step', () => {
        const base = flow([{ type: 'end' }, {}]);

        expect(connect('s1', 's2')(base)).toBeNull();
        expect(connect('s2', 's2')(base)).toBeNull();
    });

    it('removing a step drops every line pointing at it', () => {
        const base = flow([
            { nextId: 's2' },
            { type: 'decision', yesId: 's3', noId: 's1' },
            {},
        ]);
        const next = done(removeStep('s1')(base));

        expect(next.steps.map((step) => step.id)).toEqual(['s2', 's3']);
        expect(next.steps[0].noId).toBeUndefined();
    });

    it('keeps the links that still make sense when the symbol changes', () => {
        const base = flow([{ nextId: 's2' }, {}]);

        expect(done(setType('s1', 'decision')(base)).steps[0]).toMatchObject({
            yesId: 's2',
            nextId: undefined,
        });
        expect(
            done(setType('s1', 'end')(base)).steps[0].nextId,
        ).toBeUndefined();
    });
});

describe('rows and lanes', () => {
    it('inserts a row and pushes the steps below it down', () => {
        const next = done(
            insertRow(1)(flow([{ row: 0 }, { row: 1 }, { row: 2 }])),
        );

        expect(next.steps.map((step) => step.row)).toEqual([0, 2, 3]);
    });

    it('removes only an empty row', () => {
        const base = flow([{ row: 0 }, { row: 2 }]);

        expect(removeRow(0)(base)).toBeNull();
        expect(done(removeRow(1)(base)).steps.map((step) => step.row)).toEqual([
            0, 1,
        ]);
    });

    it('renames a lane together with its steps', () => {
        const next = done(renameLane(0, 'Pegawai')(flow([{ actor: 'Staf' }])));

        expect(next.lanes).toEqual(['Pegawai', 'Atasan']);
        expect(next.steps[0].actor).toBe('Pegawai');
        expect(renameLane(0, 'Atasan')(next)).toBeNull();
    });

    it('moves the steps of a removed lane to a neighbour without overlapping', () => {
        const next = done(
            removeLane(1)(
                flow([
                    { actor: 'Staf', row: 0 },
                    { actor: 'Atasan', row: 0 },
                ]),
            ),
        );

        expect(next.lanes).toEqual(['Staf']);
        expect(stepAt(next, 0, 0)?.id).toBe('s1');
        expect(stepAt(next, 0, 1)?.id).toBe('s2');
    });

    it('gives the first lane to steps that had none', () => {
        const next = done(addLane('Staf')(flow([{}], [])));

        expect(next.steps[0].actor).toBe('Staf');
        expect(addLane('Staf')(next)).toBeNull();
    });
});

describe('older drafts', () => {
    it('places steps without a row as the PDF engine does: along the links from Mulai', () => {
        const steps = placeSteps(
            ['Staf', 'Atasan'],
            flow([
                { id: 'a', type: 'start', actor: 'Staf', nextId: 'b' },
                {
                    id: 'b',
                    type: 'decision',
                    actor: 'Atasan',
                    yesId: 'c',
                    noId: 'd',
                },
                { id: 'c', type: 'end', actor: 'Atasan' },
                { id: 'd', actor: 'Atasan' },
                { id: 'e', actor: 'Unknown' },
            ]).steps,
        );

        expect(
            steps.map((step) => `${step.id}:${step.actor}:${step.row}`),
        ).toEqual([
            'a:Staf:0',
            'b:Atasan:1',
            'c:Atasan:2',
            'd:Atasan:3',
            'e:Staf:3',
        ]);
    });
});

describe('next step (Bizagi style)', () => {
    it('adds the next step right below and links it', () => {
        const next = done(
            appendStep(
                's1',
                'process',
                0,
                'next',
                'n',
            )(flow([{ actor: 'Staf', row: 0, type: 'start' }])),
        );

        expect(next.steps[0].nextId).toBe('n');
        expect(stepAt(next, 0, 1)?.id).toBe('n');
    });

    it('opens a row when the cell below is taken, pushing the rest down', () => {
        const base = flow([
            { actor: 'Staf', row: 0 },
            { actor: 'Staf', row: 1 },
            { actor: 'Atasan', row: 2 },
        ]);
        const next = done(appendStep('s1', 'document', 0, 'next', 'n')(base));

        expect(stepAt(next, 0, 1)?.id).toBe('n');
        expect(stepAt(next, 0, 2)?.id).toBe('s2');
        expect(stepAt(next, 1, 3)?.id).toBe('s3');
    });

    it('links a decision on Ya and Tidak without stacking the branches', () => {
        let next = flow([{ type: 'decision', actor: 'Staf', row: 0 }]);
        next = done(appendStep('s1', 'process', 0, 'yes', 'y')(next));
        next = done(appendStep('s1', 'process', 0, 'no', 'n')(next));

        expect(next.steps[0]).toMatchObject({ yesId: 'y', noId: 'n' });
        expect(
            new Set(next.steps.map((step) => `${step.actor}:${step.row}`)).size,
        ).toBe(3);
    });

    it('replaces a branch that was already linked', () => {
        const base = flow([
            { type: 'decision', actor: 'Staf', row: 0, yesId: 's2' },
            { actor: 'Staf', row: 1 },
        ]);

        expect(
            done(appendStep('s1', 'end', 1, 'yes', 'n')(base)).steps[0].yesId,
        ).toBe('n');
    });

    it('has nothing after Selesai', () => {
        expect(
            appendStep('s1', 'process', 0)(flow([{ type: 'end', row: 0 }])),
        ).toBeNull();
    });
});

describe('line sides', () => {
    it('sets one side and hands it back to the router with null', () => {
        let next = done(
            setPorts('s1', 'next', { exit: 'right', entry: 'left' })(
                flow([{ nextId: 's2' }, {}]),
            ),
        );
        expect(next.steps[0].ports?.next).toEqual({
            exit: 'right',
            entry: 'left',
        });

        next = done(setPorts('s1', 'next', { exit: null })(next));
        expect(next.steps[0].ports?.next).toEqual({ entry: 'left' });

        next = done(setPorts('s1', 'next', { entry: null })(next));
        expect(next.steps[0].ports?.next).toBeUndefined();
    });

    it('forgets the sides of a removed line, and swaps them with Ya / Tidak', () => {
        const base = flow([
            {
                type: 'decision',
                yesId: 's2',
                noId: 's3',
                ports: { yes: { exit: 'bottom' }, no: { exit: 'left' } },
            },
            {},
            {},
        ]);

        expect(done(swapBranches('s1')(base)).steps[0].ports).toEqual({
            yes: { exit: 'left' },
            no: { exit: 'bottom' },
        });
        expect(
            done(disconnect('s1', 'no')(base)).steps[0].ports?.no,
        ).toBeUndefined();
    });

    it('carries the sides along when the symbol changes', () => {
        const base = flow([
            { nextId: 's2', ports: { next: { exit: 'right' } } },
            {},
        ]);

        expect(
            done(setType('s1', 'decision')(base)).steps[0].ports?.yes,
        ).toEqual({ exit: 'right' });
        expect(done(setType('s1', 'end')(base)).steps[0].ports).toBeUndefined();
    });
});
