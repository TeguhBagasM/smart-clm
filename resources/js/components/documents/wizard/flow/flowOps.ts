import { laneIndex } from '@/components/documents/wizard/flow/flowGraph';
import type { DraftFlow, FlowStep, FlowStepType } from '@/types';

/**
 * Edits of a placed flow (every step has its `row` and lane written down,
 * see `placeSteps`). Each returns a new flow, or null when the edit is refused.
 */
export type FlowEdit = (flow: DraftFlow) => DraftFlow | null;

export type LinkKind = 'next' | 'yes' | 'no';

const LINK_KEY = { next: 'nextId', yes: 'yesId', no: 'noId' } as const;

const DEFAULT_TEXT: Partial<Record<FlowStepType, string>> = {
    start: 'Mulai',
    end: 'Selesai',
};

export function stepAt(
    flow: DraftFlow,
    lane: number,
    row: number,
): FlowStep | undefined {
    return flow.steps.find(
        (step) =>
            step.row === row && laneIndex(flow.lanes, step.actor) === lane,
    );
}

function laneName(flow: DraftFlow, lane: number): string | undefined {
    return flow.lanes[lane];
}

function withSteps(flow: DraftFlow, steps: FlowStep[]): DraftFlow {
    return { ...flow, steps };
}

/** Numbers the steps top to bottom, then left to right, for the table view and the engine. */
export function normalizeOrder(flow: DraftFlow): DraftFlow {
    const sorted = [...flow.steps].sort(
        (a, b) =>
            (a.row ?? 0) - (b.row ?? 0) ||
            laneIndex(flow.lanes, a.actor) - laneIndex(flow.lanes, b.actor),
    );

    return withSteps(
        flow,
        sorted.map((step, i) => ({ ...step, order: i + 1 })),
    );
}

export function addStep(
    type: FlowStepType,
    lane: number,
    row: number,
    id: string = crypto.randomUUID(),
): FlowEdit {
    return (flow) =>
        stepAt(flow, lane, row)
            ? null
            : withSteps(flow, [
                  ...flow.steps,
                  {
                      id,
                      order: flow.steps.length + 1,
                      type,
                      text: DEFAULT_TEXT[type] ?? '',
                      actor: laneName(flow, lane),
                      row,
                  },
              ]);
}

export function moveStep(id: string, lane: number, row: number): FlowEdit {
    return (flow) => {
        const there = stepAt(flow, lane, row);

        if (there || lane < 0 || row < 0) {
            return null;
        }

        return updateStep(id, { actor: laneName(flow, lane), row })(flow);
    };
}

export function updateStep(id: string, patch: Partial<FlowStep>): FlowEdit {
    return (flow) =>
        withSteps(
            flow,
            flow.steps.map((step) =>
                step.id === id ? { ...step, ...patch } : step,
            ),
        );
}

/** A decision fills "Ya" first, then "Tidak"; other steps have one next step. */
export function connect(fromId: string, toId: string): FlowEdit {
    return (flow) => {
        const from = flow.steps.find((step) => step.id === fromId);

        if (!from || fromId === toId || from.type === 'end') {
            return null;
        }

        if (from.type !== 'decision') {
            return updateStep(fromId, { nextId: toId })(flow);
        }

        if (from.yesId === toId || from.noId === toId) {
            return null;
        }

        return updateStep(
            fromId,
            from.yesId ? { noId: toId } : { yesId: toId },
        )(flow);
    };
}

/** Removing a line also forgets the sides chosen for it. */
export function disconnect(fromId: string, kind: LinkKind): FlowEdit {
    return (flow) => {
        const step = flow.steps.find((item) => item.id === fromId);
        const ports = { ...step?.ports, [kind]: undefined };

        return updateStep(fromId, { [LINK_KEY[kind]]: undefined, ports })(flow);
    };
}

export function removeStep(id: string): FlowEdit {
    const clean = (value?: string) => (value === id ? undefined : value);

    return (flow) =>
        withSteps(
            flow,
            flow.steps
                .filter((step) => step.id !== id)
                .map((step) => ({
                    ...step,
                    nextId: clean(step.nextId),
                    yesId: clean(step.yesId),
                    noId: clean(step.noId),
                })),
        );
}
