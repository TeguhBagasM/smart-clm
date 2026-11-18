import type { FlowStep } from '@/types';

export const BOX = { w: 150, h: 54, gapY: 34, gapX: 40, pad: 16, head: 34 };

export type Placed = FlowStep & { x: number; y: number };
export type Edge = { path: string; label?: string; lx: number; ly: number };

export type Layout = {
    width: number;
    height: number;
    lanes: string[];
    placed: Placed[];
    edges: Edge[];
};

/** Places steps top to bottom in their order, one column per lane. */
export function layoutFlow(lanes: string[], steps: FlowStep[]): Layout {
    const columns = lanes.length ? lanes : [''];
    const sorted = [...steps].sort((a, b) => a.order - b.order);
    const colWidth = BOX.w + BOX.gapX;
    const placed: Placed[] = sorted.map((step, index) => ({
        ...step,
        x: BOX.pad + Math.max(0, columns.indexOf(step.actor ?? '')) * colWidth,
        y: BOX.pad + BOX.head + index * (BOX.h + BOX.gapY),
    }));
    const byId = new Map(placed.map((step) => [step.id, step]));
    const edges: Edge[] = [];

    for (const from of placed) {
        const links: [string | undefined, string?][] =
            from.type === 'decision'
                ? [
                      [from.yesId, 'Ya'],
                      [from.noId, 'Tidak'],
                  ]
                : [[from.nextId]];

        for (const [id, label] of links) {
            const to = id ? byId.get(id) : undefined;

            if (!to) continue;

            const ax = from.x + BOX.w / 2;
            const ay = from.y + BOX.h;
            const bx = to.x + BOX.w / 2;
            const by = to.y;
            const mid = ay + BOX.gapY / 2;
            const forward = by > ay;

            edges.push({
                path: forward
                    ? `M${ax},${ay} V${mid} H${bx} V${by}`
                    : `M${from.x + BOX.w},${from.y + BOX.h / 2} h14 V${to.y + BOX.h / 2} H${to.x + BOX.w}`,
                label,
                lx: ax + 6,
                ly: forward ? ay + 14 : from.y + BOX.h / 2 - 4,
            });
        }
    }

    return {
        width: BOX.pad * 2 + columns.length * colWidth - BOX.gapX,
        height: BOX.pad * 2 + BOX.head + placed.length * (BOX.h + BOX.gapY),
        lanes: columns,
        placed,
        edges,
    };
}
