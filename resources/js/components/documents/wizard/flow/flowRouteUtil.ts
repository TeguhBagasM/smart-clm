// Copy of management-contract/dcs/pdf-flow-route-util.ts (see flowRoute.ts). Keep identical, not reformatted.
// Bagian murni penentu jalur garis diagram alir: tipe, konstanta dan fungsi geometri tanpa keadaan.
// Dipakai pdf-flow-route.ts; Laravel menyalin keduanya (flowRouteUtil.ts, flowRoute.ts).

export type Side = "top" | "right" | "bottom" | "left";
export interface FlowBox { id: string; cx: number; top: number; w: number; h: number; lane: number; row: number }
// `points`: tekukan yang diatur penyusun, dalam satuan grid (x = lajur + pecahan, y = baris + pecahan),
// agar kanvas dan PDF yang berskala beda menggambar jalur yang sama.
export interface FlowEdge { from: string; to: string; kind: "next" | "yes" | "no"; label?: string; exit?: Side; entry?: Side; points?: Pt[] }
export interface Pt { x: number; y: number }
export interface RoutedEdge { points: Pt[]; label?: { text: string; x: number; y: number } }
// `place` mengubah titik grid menjadi koordinat; tanpa itu tekukan manual diabaikan.
export interface RouteCtx { laneLeft: (lane: number) => number; laneW: number; gap: number; place?: (p: Pt) => Pt }

export interface Seg { x1: number; y1: number; x2: number; y2: number; owner: string }

export const PAD = 3;          // jarak aman dari kotak
export const STEP = 4;         // geser per percobaan agar dua garis tidak berhimpit
export const TRIES = [0, 1, -1, 2, -2, 3, -3];
export const OUT: Record<Side, Pt> = { top: { x: 0, y: 1 }, bottom: { x: 0, y: -1 }, right: { x: 1, y: 0 }, left: { x: -1, y: 0 } };
export const FLIP: Record<Side, Side> = { top: "bottom", bottom: "top", left: "right", right: "left" };


// Rapikan: buang titik kembar dan titik di tengah ruas lurus; null bila jalur berbalik arah.
export function tidy(pts: Pt[]): Pt[] | null {
  const out: Pt[] = [];
  for (const p of pts) {
    if (out.length && out[out.length - 1].x === p.x && out[out.length - 1].y === p.y) continue;
    out.push(p);
    while (out.length >= 3) {
      const [a, b, c] = out.slice(-3);
      if (!((a.x === b.x && b.x === c.x) || (a.y === b.y && b.y === c.y))) break;
      const between = (b.x - a.x) * (c.x - b.x) >= 0 && (b.y - a.y) * (c.y - b.y) >= 0;
      if (!between) return null;
      out.splice(out.length - 2, 1);
    }
  }
  return out;
}

// Sisipkan sudut di antara dua titik yang tidak segaris, bergantian arah dengan ruas sebelumnya,
// supaya garis manual tetap siku-siku. `firstVertical`: garis keluar dari sisi atas/bawah.
export function orthogonal(raw: Pt[], firstVertical: boolean): Pt[] {
  const pts: Pt[] = [raw[0]];
  for (let i = 1; i < raw.length; i++) {
    const u = pts[pts.length - 1], v = raw[i];
    if (u.x !== v.x && u.y !== v.y) {
      const prev = pts[pts.length - 2];
      const wasVertical = prev ? prev.x === u.x : firstVertical;
      pts.push(wasVertical ? { x: v.x, y: u.y } : { x: u.x, y: v.y });
    }
    pts.push(v);
  }
  return pts;
}

// Berapa ruas garis lain yang berhimpit dengan ruas p-q.
export function overlapCount(used: Seg[], p: Pt, q: Pt, owner: string): number {
  return used.filter((s) => {
    if (s.owner === owner) return false;
    if (p.y === q.y && s.y1 === s.y2) return Math.abs(s.y1 - p.y) < 2.5 && Math.min(s.x1, s.x2) < Math.max(p.x, q.x) - 1 && Math.max(s.x1, s.x2) > Math.min(p.x, q.x) + 1;
    if (p.x === q.x && s.x1 === s.x2) return Math.abs(s.x1 - p.x) < 2.5 && Math.min(s.y1, s.y2) < Math.max(p.y, q.y) - 1 && Math.max(s.y1, s.y2) > Math.min(p.y, q.y) + 1;
    return false;
  }).length;
}

// Berapa ruas garis lain yang disilang ruas p-q.
export function crossingCount(used: Seg[], p: Pt, q: Pt, owner: string): number {
  return used.filter((s) => {
    if (s.owner === owner) return false;
    const h = p.y === q.y ? { y: p.y, x1: Math.min(p.x, q.x), x2: Math.max(p.x, q.x) } : s.y1 === s.y2 ? { y: s.y1, x1: Math.min(s.x1, s.x2), x2: Math.max(s.x1, s.x2) } : null;
    const v = p.x === q.x ? { x: p.x, y1: Math.min(p.y, q.y), y2: Math.max(p.y, q.y) } : s.x1 === s.x2 ? { x: s.x1, y1: Math.min(s.y1, s.y2), y2: Math.max(s.y1, s.y2) } : null;
    if (!h || !v || (p.y === q.y) === (s.y1 === s.y2)) return false;
    return v.x > h.x1 + 1 && v.x < h.x2 - 1 && h.y > v.y1 + 1 && h.y < v.y2 - 1;
  }).length;
}
