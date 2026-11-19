import type {
    FlowEdit,
    LinkKind,
} from '@/components/documents/wizard/flow/flowOps';
import { updateStep } from '@/components/documents/wizard/flow/flowOps';
import type { FlowPorts, FlowStepType } from '@/types';

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

/**
 * Sets the sides a line leaves and enters its steps; `null` for a side
 * hands it back to the router. A line with no side chosen drops its entry.
 */
export function setPorts(
    fromId: string,
    kind: LinkKind,
    patch: Partial<Record<keyof FlowPorts, FlowPorts['exit'] | null>>,
): FlowEdit {
    return (flow) => {
        const step = flow.steps.find((item) => item.id === fromId);

        if (!step) {
            return null;
        }

        const merged = { ...step.ports?.[kind] };

        for (const [key, side] of Object.entries(patch) as [
            keyof FlowPorts,
            FlowPorts['exit'] | null,
        ][]) {
            if (side) {
                merged[key] = side;
            } else {
                delete merged[key];
            }
        }

        const ports = {
            ...step.ports,
            [kind]: Object.keys(merged).length ? merged : undefined,
        };

        return updateStep(fromId, { ports })(flow);
    };
}
