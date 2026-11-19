import type { FlowSide, FlowStep } from '@/types';

export type FlowLink = {
    from: string;
    to: string;
    kind: 'next' | 'yes' | 'no';
    label?: string;
    exit?: FlowSide;
    entry?: FlowSide;
    points?: { x: number; y: number }[];
};

/** Lane index of a step; a step without a known lane sits in the first. */
export function laneIndex(lanes: string[], actor?: string): number {
    const index = lanes.indexOf(actor ?? '');

    return index >= 0 ? index : 0;
}

/** Every connection of the flow, decisions labelled Ya / Tidak. */
export function flowLinks(steps: FlowStep[]): FlowLink[] {
    const ids = new Set(steps.map((step) => step.id));
    const links: FlowLink[] = [];

    for (const step of steps) {
        const add = (
            to: string | undefined,
            kind: FlowLink['kind'],
            label?: string,
        ) => {
            if (to && ids.has(to)) {
                const ports = step.ports?.[kind];
                links.push({
                    from: step.id,
                    to,
                    kind,
                    label,
                    exit: ports?.exit,
                    entry: ports?.entry,
                    points: ports?.points,
                });
            }
        };

        if (step.type === 'decision') {
            add(step.yesId, 'yes', 'Ya');
            add(step.noId, 'no', 'Tidak');
        } else {
            add(step.nextId, 'next');
        }
    }

    return links;
}

/**
 * Grid row of every step, as the PDF engine decides it (`layoutFlowGraph` in
 * management-contract/dcs/pdf-compose.ts): a saved `row` is kept, the others
 * are counted from "Mulai" along the connections, unreached steps go below,
 * and a step landing on a taken cell moves down until the cell is free.
 */
export function flowRows(
    lanes: string[],
    steps: FlowStep[],
): Map<string, number> {
    const row = new Map<string, number>();
    const next = new Map<string, string[]>();

    for (const link of flowLinks(steps)) {
        next.set(link.from, [...(next.get(link.from) ?? []), link.to]);
    }

    for (const step of steps) {
        if (Number.isInteger(step.row) && (step.row as number) >= 0) {
            row.set(step.id, step.row as number);
        }
    }

    const start = steps.find((step) => step.type === 'start') ?? steps[0];

    if (start && !row.has(start.id)) {
        row.set(start.id, 0);
        const queue = [start.id];

        while (queue.length) {
            const current = queue.shift() as string;

            for (const target of next.get(current) ?? []) {
                if (!row.has(target)) {
                    row.set(target, (row.get(current) as number) + 1);
                    queue.push(target);
                }
            }
        }
    }

    let deepest = row.size ? Math.max(...row.values()) : -1;

    for (const step of steps) {
        if (!row.has(step.id)) {
            row.set(step.id, ++deepest);
        }
    }

    const taken = new Set<string>();
    const ordered = [...steps].sort(
        (a, b) =>
            (row.get(a.id) as number) - (row.get(b.id) as number) ||
            a.order - b.order,
    );

    for (const step of ordered) {
        const lane = laneIndex(lanes, step.actor);
        let r = row.get(step.id) as number;

        while (taken.has(`${lane}:${r}`)) {
            r += 1;
        }

        taken.add(`${lane}:${r}`);
        row.set(step.id, r);
    }

    return row;
}

/** The flow with every step's row and lane written down, as the canvas edits it. */
export function placeSteps(lanes: string[], steps: FlowStep[]): FlowStep[] {
    const rows = flowRows(lanes, steps);

    return steps.map((step) => ({
        ...step,
        actor: lanes.length ? lanes[laneIndex(lanes, step.actor)] : undefined,
        row: rows.get(step.id),
    }));
}
