// Copy of management-contract/dcs/pdf-flow-route.ts (with pdf-flow-route-util.ts as flowRouteUtil.ts),
// the router the PDF engine uses, so the canvas draws the same lines that get printed. Keep the files
// identical except for the import path (not reformatted). It works in PDF coordinates (y grows
// upwards): flowLayout.ts flips y on the way in and out.
// Penentu jalur garis diagram alir (swimlane): garis siku-siku yang menghindari kotak langkah,
// tidak menumpuk satu sama lain, dan bisa diarahkan penyusun lewat sisi keluar/masuk.
// Murni geometri (tanpa pdf-lib) supaya mudah diuji. Koordinat PDF: y bertambah ke atas.

import { crossingCount, FLIP, orthogonal, OUT, overlapCount, PAD, STEP, tidy, TRIES } from "./flowRouteUtil";
import type { FlowBox, FlowEdge, Pt, RouteCtx, RoutedEdge, Seg, Side } from "./flowRouteUtil";

export type { FlowBox, FlowEdge, Pt, RouteCtx, RoutedEdge, Side } from "./flowRouteUtil";

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
  const overlaps = (p: Pt, q: Pt, owner: string) => overlapCount(used, p, q, owner);
  const crossings = (p: Pt, q: Pt, owner: string) => crossingCount(used, p, q, owner);

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

  const claim = (pts: Pt[], owner: string) => pts.forEach((v, i) => { if (i > 0) used.push({ x1: pts[i - 1].x, y1: pts[i - 1].y, x2: v.x, y2: v.y, owner }); });

  // Garis yang tekukannya diatur penyusun digambar persis lebih dulu, supaya garis otomatis menghindarinya.
  // Titik pertama/terakhir diluruskan ke titik sisi (kotak bisa sudah dipindah atau diubah ukurannya)
  // dan sudut ditambahkan bila dua titik tidak segaris, sehingga garis tetap siku-siku.
  const manual = new Map<number, Pt[]>();
  edges.forEach((e, index) => {
    const a = byId.get(e.from), b = byId.get(e.to);
    if (!a || !b || !e.points?.length || !ctx.place) return;
    const exit = e.exit ?? "bottom", entry = e.entry ?? "top";
    const p = port(a, exit), q = port(b, entry);
    take(a.id, exit);
    take(b.id, entry);
    const mids = e.points.map((v) => ctx.place!(v));
    // Tekukan penyusun tidak digeser; hanya ditambah siku dari titik sisi (kotak bisa sudah dipindah
    // atau diubah ukurannya) supaya garis keluar dan masuk tegak lurus terhadap sisinya.
    const exitVertical = exit === "top" || exit === "bottom", entryVertical = entry === "top" || entry === "bottom";
    const first = mids[0], last = mids[mids.length - 1];
    const out = exitVertical ? { x: p.x, y: first.y } : { x: first.x, y: p.y };
    const into = entryVertical ? { x: q.x, y: last.y } : { x: last.x, y: q.y };
    const pts = orthogonal([p, out, ...mids, into, q], exitVertical);
    const clean = tidy(pts) ?? pts;
    claim(clean, `${e.from}>${e.to}#${index}`);
    manual.set(index, clean);
  });

  const labelFor = (e: FlowEdge, points: Pt[]) => {
    const first = points[0], second = points[1] ?? points[0];
    const down = second.x === first.x;
    return e.label ? { text: e.label, x: down ? first.x + 5 : first.x + (second.x > first.x ? 4 : -22), y: down ? first.y - 11 : first.y + 3 } : undefined;
  };

  return edges.flatMap((e, index): RoutedEdge[] => {
    const fixed = manual.get(index);
    if (fixed) return [{ points: fixed, label: labelFor(e, fixed) }];
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
    claim(pick.pts, owner);
    return [{ points: pick.pts, label: labelFor(e, pick.pts) }];
  });
}
