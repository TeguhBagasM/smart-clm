import type {
    FlowEdit,
    LinkKind,
} from '@/components/documents/wizard/flow/flowOps';
import {
    connect,
    updateStep,
} from '@/components/documents/wizard/flow/flowOps';
import type { FlowPorts, FlowSide, FlowStepType } from '@/types';

export function swapBranches(id: string): FlowEdit {
    return (flow) => {
        const step = flow.steps.find((item) => item.id === id);
        const ports = step?.ports && {
            ...step.ports,
            yes: step.ports.no,
            no: step.ports.yes,
        };

        return step?.type === 'decision'
            ? updateStep(id, { yesId: step.noId, noId: step.yesId, ports })(
                  flow,
              )
            : null;
    };
}

/** Changing the type keeps the links that still make sense, and the sides chosen for them. */
export function setType(id: string, type: FlowStepType): FlowEdit {
    return (flow) => {
        const step = flow.steps.find((item) => item.id === id);

        if (!step) {
            return null;
        }

        const none = { nextId: undefined, yesId: undefined, noId: undefined };

        if (type === 'decision') {
            return updateStep(id, {
                ...none,
                type,
                yesId: step.yesId ?? step.nextId,
                noId: step.noId,
                ports: {
                    yes: step.ports?.yes ?? step.ports?.next,
                    no: step.ports?.no,
                },
            })(flow);
        }

        if (type === 'end') {
            return updateStep(id, { ...none, type, ports: undefined })(flow);
        }

        return updateStep(id, {
            ...none,
            type,
            nextId: step.nextId ?? step.yesId,
            ports: { next: step.ports?.next ?? step.ports?.yes },
        })(flow);
    };
}

type SidePatch = { exit?: FlowSide | null; entry?: FlowSide | null };

/** The stored sides and bends of one line, changed by `fn`; an empty result drops the entry. */
function withPorts(
    fromId: string,
    kind: LinkKind,
    fn: (ports: FlowPorts) => FlowPorts,
): FlowEdit {
    return (flow) => {
        const step = flow.steps.find((item) => item.id === fromId);

        if (!step) {
            return null;
        }

        const next = fn({ ...step.ports?.[kind] });
        const clean = Object.fromEntries(
            Object.entries(next).filter(
                ([, value]) => value !== undefined && value !== null,
            ),
        );
        const ports = {
            ...step.ports,
            [kind]: Object.keys(clean).length ? clean : undefined,
        };

        return updateStep(fromId, { ports })(flow);
    };
}

/**
 * Sets the sides a line leaves and enters its steps; `null` hands a side back
 * to the router. Bends drawn by hand no longer fit and are dropped.
 */
export function setPorts(
    fromId: string,
    kind: LinkKind,
    patch: SidePatch,
): FlowEdit {
    return withPorts(fromId, kind, (ports) => ({
        exit: patch.exit === undefined ? ports.exit : (patch.exit ?? undefined),
        entry:
            patch.entry === undefined
                ? ports.entry
                : (patch.entry ?? undefined),
    }));
}

/**
 * Keeps a line exactly as drawn: its bend points (grid units) and the sides
 * they start and end on. `null` gives the line back to the router.
 */
export function setRoute(
    fromId: string,
    kind: LinkKind,
    points: FlowPorts['points'] | null,
    sides: { exit: FlowSide; entry: FlowSide },
): FlowEdit {
    return withPorts(fromId, kind, (ports) =>
        points
            ? { ...sides, points }
            : { exit: ports.exit, entry: ports.entry },
    );
}

/** Moves the arrow end of a line onto another step (or another side of the same one). */
export function retarget(
    fromId: string,
    kind: LinkKind,
    toId: string,
    entry?: FlowSide,
): FlowEdit {
    const key = kind === 'next' ? 'nextId' : kind === 'yes' ? 'yesId' : 'noId';

    return (flow) => {
        if (fromId === toId || !flow.steps.some((step) => step.id === toId)) {
            return null;
        }

        const linked = updateStep(fromId, { [key]: toId })(flow);

        return (
            linked &&
            withPorts(fromId, kind, (ports) => ({ exit: ports.exit, entry }))(
                linked,
            )
        );
    };
}

/**
 * A line drawn from a connection point: links like `connect` (a decision
 * fills Ya, then Tidak) and keeps the sides it was drawn between.
 */
export function connectFrom(
    fromId: string,
    toId: string,
    sides: { exit?: FlowSide; entry?: FlowSide },
): FlowEdit {
    return (flow) => {
        const from = flow.steps.find((step) => step.id === fromId);
        const linked = connect(fromId, toId)(flow);

        if (!from || !linked) {
            return null;
        }

        const kind: LinkKind =
            from.type !== 'decision' ? 'next' : from.yesId ? 'no' : 'yes';

        return setPorts(fromId, kind, {
            exit: sides.exit ?? null,
            entry: sides.entry ?? null,
        })(linked);
    };
}
