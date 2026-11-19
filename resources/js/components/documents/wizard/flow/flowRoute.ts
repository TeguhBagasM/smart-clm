// Copy of management-contract/dcs/pdf-flow-route.ts, the router the PDF engine
// uses, so the canvas draws the same lines that get printed. Keep both files
// identical (not reformatted). It works in PDF coordinates (y grows upwards):
// flowLayout.ts flips y on the way in and out.
// Penentu jalur garis diagram alir (swimlane): garis siku-siku yang menghindari kotak langkah,
// tidak menumpuk satu sama lain, dan bisa diarahkan penyusun lewat sisi keluar/masuk.
// Murni geometri (tanpa pdf-lib) supaya mudah diuji. Koordinat PDF: y bertambah ke atas.

export type Side = "top" | "right" | "bottom" | "left";
export interface FlowBox { id: string; cx: number; top: number; w: number; h: number; lane: number; row: number }
export interface FlowEdge { from: string; to: string; kind: "next" | "yes" | "no"; label?: string; exit?: Side; entry?: Side }
export interface Pt { x: number; y: number }
export interface RoutedEdge { points: Pt[]; label?: { text: string; x: number; y: number } }
export interface RouteCtx { laneLeft: (lane: number) => number; laneW: number; gap: number }

interface Seg { x1: number; y1: number; x2: number; y2: number; owner: string }

const PAD = 3;          // jarak aman dari kotak
const STEP = 4;         // geser per percobaan agar dua garis tidak berhimpit
const TRIES = [0, 1, -1, 2, -2, 3, -3];
const OUT: Record<Side, Pt> = { top: { x: 0, y: 1 }, bottom: { x: 0, y: -1 }, right: { x: 1, y: 0 }, left: { x: -1, y: 0 } };
const FLIP: Record<Side, Side> = { top: "bottom", bottom: "top", left: "right", right: "left" };

export function routeEdges(boxes: FlowBox[], edges: FlowEdge[], ctx: RouteCtx): RoutedEdge[] {
  const byId = new Map(boxes.map((b) => [b.id, b]));
  const bottom = (b: FlowBox) => b.top - b.h;
  const mid = (b: FlowBox) => b.top - b.h / 2;

  // 1. Sisi keluar dan masuk tiap garis. Pilihan penyusun dipakai apa adanya; sisanya dipilih
  //    otomatis dari sisi yang belum dipakai garis lain pada kotak itu, supaya tidak ada dua garis
  //    yang berbagi titik (mis. Ya dan Tidak, atau garis balik yang masuk ke kotak yang sudah dituju).
  const use = new Map<string, Side[]>();
  const taken = (id: string, s: Side) => (use.get(id) ?? []).includes(s);
  const take = (id: string, s: Side) => use.set(id, [...(use.get(id) ?? []), s]);
  const toward = (a: FlowBox, b: FlowBox): Side => (b.cx >= a.cx ? "right" : "left");

  // Cabang keputusan yang menuju kotak tepat di bawah (satu lajur) keluar lewat bawah, cabang lain lewat sisi.
  const sideBranch = new Set<number>();
  const decisions = new Map<string, number[]>();
  edges.forEach((e, i) => { if (e.kind !== "next") decisions.set(e.from, [...(decisions.get(e.from) ?? []), i]); });
  for (const [from, idx] of decisions) {
    const src = byId.get(from);
    const below = idx.filter((i) => { const t = byId.get(edges[i].to); return src && t && t.lane === src.lane && t.row > src.row; });
    const down = below.length === 1 ? below[0] : idx.find((i) => edges[i].kind === "yes") ?? idx[0];
    idx.forEach((i) => { if (i !== down) sideBranch.add(i); });
  }

  // Pilihan sisi per garis, urut dari yang paling lazim. Pilihan penyusun hanya satu.
  const options = edges.map((e, i): [Side, Side][] => {
    const a = byId.get(e.from), b = byId.get(e.to);
    if (!a || !b) return [];
    const forward = b.row > a.row;
    const lateral = b.row === a.row && a.lane !== b.lane;
    const out = a.lane === b.lane ? "right" : toward(a, b);
    const exits: Side[] = e.exit ? [e.exit] : lateral ? [toward(a, b), "bottom"]
      : forward && !sideBranch.has(i) ? ["bottom", out, FLIP[out]]
      : [out, FLIP[out], "bottom"];
    return exits.flatMap((exit): [Side, Side][] => {
      const back = a.lane === b.lane ? (exit === "left" ? "left" : "right") : toward(b, a);
      const entries: Side[] = e.entry ? [e.entry] : lateral ? [toward(b, a), "top"]
        : forward ? ["top", toward(b, a), FLIP[toward(b, a)]]
        : [back, "top", FLIP[back]];
      return entries.map((entry): [Side, Side] => [exit, entry]);
    });
  });

  // Titik pada sisi kotak: garis pertama di tengah sisi, garis berikutnya pada sisi yang sama bergeser.
  const port = (b: FlowBox, side: Side): Pt => {
    const k = (use.get(b.id) ?? []).filter((x) => x === side).length;
    const vertical = side === "top" || side === "bottom";
    const shift = (k % 2 ? 1 : -1) * Math.ceil(k / 2) * (vertical ? Math.min(14, b.w / 4) : Math.min(8, b.h / 4));
    if (side === "top") return { x: b.cx + shift, y: b.top };
    if (side === "bottom") return { x: b.cx + shift, y: bottom(b) };
    return { x: b.cx + (side === "right" ? b.w / 2 : -b.w / 2), y: mid(b) - shift };
  };
  // Garis keluar lurus dari sisi: ke tengah celah antarbaris, atau ke koridor di tepi lajur.
  const stub = (b: FlowBox, side: Side, p: Pt): Pt => {
    const len = side === "top" || side === "bottom" ? ctx.gap / 2 : Math.max(6, ctx.laneW / 2 - b.w / 2 - 7);
    return { x: p.x + OUT[side].x * len, y: p.y + OUT[side].y * len };
  };

  // Jalur tengah lewat koridor tepi lajur atau celah antarbaris.
  const lanes = new Set(boxes.map((b) => b.lane));
  const xs = [...lanes].flatMap((l) => [ctx.laneLeft(l) + 7, ctx.laneLeft(l) + ctx.laneW - 7]);
  const ys = [...new Set(boxes.flatMap((b) => [b.top + ctx.gap / 2, bottom(b) - ctx.gap / 2]))];

  const used: Seg[] = [];
  const hitsBox = (p: Pt, q: Pt, skip: string[]): boolean => {
    const x1 = Math.min(p.x, q.x), x2 = Math.max(p.x, q.x), y1 = Math.min(p.y, q.y), y2 = Math.max(p.y, q.y);
    return boxes.some((b) => !skip.includes(b.id)
      && x2 > b.cx - b.w / 2 - PAD && x1 < b.cx + b.w / 2 + PAD && y2 > bottom(b) - PAD && y1 < b.top + PAD);
  };
  const overlaps = (p: Pt, q: Pt, owner: string): number => used.filter((s) => {
    if (s.owner === owner) return false;
    if (p.y === q.y && s.y1 === s.y2) return Math.abs(s.y1 - p.y) < 2.5 && Math.min(s.x1, s.x2) < Math.max(p.x, q.x) - 1 && Math.max(s.x1, s.x2) > Math.min(p.x, q.x) + 1;
    if (p.x === q.x && s.x1 === s.x2) return Math.abs(s.x1 - p.x) < 2.5 && Math.min(s.y1, s.y2) < Math.max(p.y, q.y) - 1 && Math.max(s.y1, s.y2) > Math.min(p.y, q.y) + 1;
    return false;
  }).length;

  const crossings = (p: Pt, q: Pt, owner: string): number => used.filter((s) => {
    if (s.owner === owner) return false;
    const h = p.y === q.y ? { y: p.y, x1: Math.min(p.x, q.x), x2: Math.max(p.x, q.x) } : s.y1 === s.y2 ? { y: s.y1, x1: Math.min(s.x1, s.x2), x2: Math.max(s.x1, s.x2) } : null;
    const v = p.x === q.x ? { x: p.x, y1: Math.min(p.y, q.y), y2: Math.max(p.y, q.y) } : s.x1 === s.x2 ? { x: s.x1, y1: Math.min(s.y1, s.y2), y2: Math.max(s.y1, s.y2) } : null;
    if (!h || !v || (p.y === q.y) === (s.y1 === s.y2)) return false;
    return v.x > h.x1 + 1 && v.x < h.x2 - 1 && h.y > v.y1 + 1 && h.y < v.y2 - 1;
  }).length;

  // Rapikan: buang titik kembar dan titik di tengah ruas lurus; null bila jalur berbalik arah.
  const tidy = (pts: Pt[]): Pt[] | null => {
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
  };

  // Satu jalur untuk pasangan sisi tertentu: keluar lurus, lalu lewat koridor/celah, lalu masuk lurus.
  const route = (a: FlowBox, b: FlowBox, exit: Side, entry: Side, owner: string): { pts: Pt[]; score: number } | null => {
    const p = port(a, exit), q = port(b, entry);
    const p1 = stub(a, exit, p), q1 = stub(b, entry, q);
    const middles: Pt[][] = [[], [{ x: q1.x, y: p1.y }], [{ x: p1.x, y: q1.y }]];
    for (const t of TRIES) {
      for (const x of xs) middles.push([{ x: x + t * STEP, y: p1.y }, { x: x + t * STEP, y: q1.y }]);
      for (const y of ys) middles.push([{ x: p1.x, y: y + t * STEP }, { x: q1.x, y: y + t * STEP }]);
    }
    let best: { pts: Pt[]; score: number } | null = null;
    for (const m of middles) {
      const pts = tidy([p, p1, ...m, q1, q]);
      if (!pts || pts.length < 2) continue;
      if (pts.some((v, i) => i > 0 && v.x !== pts[i - 1].x && v.y !== pts[i - 1].y)) continue; // hanya siku-siku
      let score = 25 * (pts.length - 2);
      pts.forEach((v, i) => {
        if (i === 0) return;
        const u = pts[i - 1];
        const skip = [...(i === 1 ? [a.id] : []), ...(i === pts.length - 1 ? [b.id] : [])];
        if (hitsBox(u, v, skip)) score += 10000;
        score += Math.abs(v.x - u.x) + Math.abs(v.y - u.y) + 500 * overlaps(u, v, owner) + 150 * crossings(u, v, owner);
      });
      if (!best || score < best.score) best = { pts, score };
    }
    return best;
  };

  // Sisi pilihan penyusun dipesan untuk garisnya sendiri: garis otomatis lain menghindarinya.
  const reserved = new Map<string, number[]>();
  edges.forEach((e, i) => {
    for (const key of [e.exit && `${e.from}:${e.exit}`, e.entry && `${e.to}:${e.entry}`]) if (key) reserved.set(key, [...(reserved.get(key) ?? []), i]);
  });
  const busy = (id: string, side: Side, edge: number) => taken(id, side) || (reserved.get(`${id}:${side}`) ?? []).some((i) => i !== edge);

  return edges.flatMap((e, index): RoutedEdge[] => {
    const a = byId.get(e.from), b = byId.get(e.to);
    if (!a || !b || !options[index].length) return [];
    const owner = `${e.from}>${e.to}#${index}`;
    // Coba pasangan sisi yang lazim dulu; sisi yang sudah dipakai garis lain dan persilangan membuat skor naik.
    let chosen: { pts: Pt[]; score: number; exit: Side; entry: Side } | null = null;
    options[index].forEach(([exit, entry], rank) => {
      const r = route(a, b, exit, entry, owner);
      if (!r) return;
      const penalty = (!e.exit && busy(a.id, exit, index) ? 300 : 0) + (!e.entry && busy(b.id, entry, index) ? 300 : 0);
      const score = r.score + penalty + 90 * rank;
      if (!chosen || score < chosen.score) chosen = { ...r, score, exit, entry };
    });
    const pick = chosen as { pts: Pt[]; exit: Side; entry: Side } | null;
    if (!pick) return [];
    take(a.id, pick.exit);
    take(b.id, pick.entry);
    const points = pick.pts;

    points.forEach((v, i) => { if (i > 0) used.push({ x1: points[i - 1].x, y1: points[i - 1].y, x2: v.x, y2: v.y, owner }); });
    const first = points[0], second = points[1];
    const down = second.x === first.x;
    const label = e.label ? { text: e.label, x: down ? first.x + 5 : first.x + (second.x > first.x ? 4 : -22), y: down ? first.y - 11 : first.y + 3 } : undefined;
    return [{ points, label }];
  });
}
