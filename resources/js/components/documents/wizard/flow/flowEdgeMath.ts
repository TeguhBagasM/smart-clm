import type { Rect } from '@/components/documents/wizard/flow/flowGeometry';
import type { FlowSide } from '@/types';

type Pt = { x: number; y: number };

/** A point `len` along the way from `a` towards `b`. */
function toward(a: Pt, b: Pt, len: number): Pt {
    const d = Math.hypot(b.x - a.x, b.y - a.y) || 1;
    const t = Math.min(len, d / 3) / d;

    return { x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t };
}

/**
 * Moves segment `k` of an orthogonal line across its direction to `to`, as
 * draw.io does. The two ends stay on their steps: when the first or last
 * segment moves, a short stub is kept at the step and a new bend appears.
 */
export function moveSegment(points: Pt[], k: number, to: Pt): Pt[] {
    const pts = points.map((p) => ({ ...p }));
    let i = k;

    if (i < 0 || i > pts.length - 2) {
        return pts;
    }

    const horizontal = pts[i].y === pts[i + 1].y;

    if (i === pts.length - 2) {
        const stub = toward(pts[i + 1], pts[i], 16);
        pts.splice(i + 1, 0, stub, { ...stub });
    }

    if (i === 0) {
        const stub = toward(pts[0], pts[1], 16);
        pts.splice(1, 0, stub, { ...stub });
        i = 2;
    }

    if (horizontal) {
        pts[i].y = to.y;
        pts[i + 1].y = to.y;
    } else {
        pts[i].x = to.x;
        pts[i + 1].x = to.x;
    }

    return pts;
}

/** Which side of a box a line end touches. */
export function sideOf(box: Rect, p: Pt): FlowSide {
    const near = (a: number, b: number) => Math.abs(a - b) < 0.5;

    if (near(p.y, box.y)) return 'top';
    if (near(p.y, box.y + box.h)) return 'bottom';

    return near(p.x, box.x) ? 'left' : 'right';
}

/** Middle of each segment long enough to grab, with its direction. */
export function segmentHandles(
    points: Pt[],
): { k: number; x: number; y: number; horizontal: boolean }[] {
    return points.slice(1).flatMap((q, i) => {
        const p = points[i];

        return Math.hypot(q.x - p.x, q.y - p.y) < 14
            ? []
            : [
                  {
                      k: i,
                      x: (p.x + q.x) / 2,
                      y: (p.y + q.y) / 2,
                      horizontal: p.y === q.y,
                  },
              ];
    });
}
