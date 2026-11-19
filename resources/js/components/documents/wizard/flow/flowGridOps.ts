import type {
    FlowEdit,
    LinkKind,
} from '@/components/documents/wizard/flow/flowOps';
import {
    addStep,
    stepAt,
    updateStep,
} from '@/components/documents/wizard/flow/flowOps';
import type { DraftFlow, FlowStepType } from '@/types';

/** Grid edits: lanes (actors; steps follow their lane because they hold its name) and rows. */
export function addLane(name: string): FlowEdit {
    return (flow) => {
        const clean = name.trim();

        if (!clean || flow.lanes.includes(clean)) {
            return null;
        }

        // The first lane adopts the steps that had none.
        const steps = flow.lanes.length
            ? flow.steps
            : flow.steps.map((step) => ({ ...step, actor: clean }));

        return { ...flow, lanes: [...flow.lanes, clean], steps };
    };
}

export function renameLane(index: number, name: string): FlowEdit {
    return (flow) => {
        const clean = name.trim();
        const old = flow.lanes[index];

        if (
            !clean ||
            old === undefined ||
            flow.lanes.some((lane, i) => i !== index && lane === clean)
        ) {
            return null;
        }

        return {
            ...flow,
            lanes: flow.lanes.map((lane, i) => (i === index ? clean : lane)),
            steps: flow.steps.map((step) =>
                step.actor === old ? { ...step, actor: clean } : step,
            ),
        };
    };
}

export function moveLane(index: number, by: -1 | 1): FlowEdit {
    return (flow) => {
        const to = index + by;

        if (to < 0 || to >= flow.lanes.length) {
            return null;
        }

        const lanes = [...flow.lanes];
        [lanes[index], lanes[to]] = [lanes[to], lanes[index]];

        return { ...flow, lanes };
    };
}

/**
 * Removes a lane; its steps move to a neighbouring lane, each in the nearest
 * free row at or below where it stood.
 */
export function removeLane(index: number): FlowEdit {
    return (flow) => {
        const removed = flow.lanes[index];

        if (removed === undefined) {
            return null;
        }

        const lanes = flow.lanes.filter((_, i) => i !== index);
        const target = lanes.length ? Math.max(0, index - 1) : 0;
        let next: DraftFlow = {
            ...flow,
            lanes,
            steps: flow.steps.filter((step) => step.actor !== removed),
        };

        for (const step of flow.steps.filter(
            (item) => item.actor === removed,
        )) {
            let row = step.row ?? 0;

            while (stepAt(next, target, row)) {
                row += 1;
            }

            next = {
                ...next,
                steps: [...next.steps, { ...step, actor: lanes[target], row }],
            };
        }

        return next;
    };
}

/** Opens an empty row at `at`, pushing everything from there down. */
export function insertRow(at: number): FlowEdit {
    return (flow) => ({
        ...flow,
        steps: flow.steps.map((step) =>
            (step.row ?? 0) >= at
                ? { ...step, row: (step.row ?? 0) + 1 }
                : step,
        ),
    });
}

/** Closes row `at`; refused while a step still stands in it. */
export function removeRow(at: number): FlowEdit {
    return (flow) =>
        flow.steps.some((step) => step.row === at)
            ? null
            : {
                  ...flow,
                  steps: flow.steps.map((step) =>
                      (step.row ?? 0) > at
                          ? { ...step, row: (step.row ?? 0) - 1 }
                          : step,
                  ),
              };
}

/**
 * "Langkah berikutnya": a new step one row below `fromId` in `lane`, linked
 * from it (a decision's `branch` picks Ya or Tidak, replacing an existing
 * one). A taken cell first gets an empty row opened, so nothing overlaps.
 */
export function appendStep(
    fromId: string,
    type: FlowStepType,
    lane: number,
    branch: LinkKind = 'next',
    id: string = crypto.randomUUID(),
): FlowEdit {
    return (flow) => {
        const from = flow.steps.find((step) => step.id === fromId);

        if (!from || from.type === 'end') {
            return null;
        }

        const row = (from.row ?? 0) + 1;
        const opened = stepAt(flow, lane, row) ? insertRow(row)(flow) : flow;
        const added = opened && addStep(type, lane, row, id)(opened);
        const key =
            from.type !== 'decision'
                ? 'nextId'
                : branch === 'no'
                  ? 'noId'
                  : 'yesId';

        return added ? updateStep(fromId, { [key]: id })(added) : null;
    };
}
