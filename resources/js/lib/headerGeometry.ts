// Geometri kop (letterhead) versi 2: SATU sumber untuk PDF dan pratinjau editor.
// Fungsi murni tanpa import dan tanpa dependensi. Salinan identik ada di Laravel
// (resources/js/lib/headerGeometry.ts); ubah di sini dulu, lalu salin persis.
// Satuan: point PDF; y dihitung dari tepi ATAS kop ke bawah.

export type HeaderAlign = "left" | "center" | "right";
export interface HeaderCell {
  width: number; text: string; bold: boolean; italic: boolean; uppercase: boolean;
  align: HeaderAlign; fontSize: number | null; background: string | null; color: string;
}
export interface HeaderRow { height: number; cells: HeaderCell[] }
export interface HeaderLogo {
  enabled: boolean; source: "company" | "custom"; assetId: string | null;
  side: "left" | "right"; rows: number; width: number; valign: "top" | "middle" | "bottom"; padding: number;
}
export interface HeaderLayout { version: number; border: string; logo: HeaderLogo; rows: HeaderRow[] }

export interface Rgba { r: number; g: number; b: number; a: number }
export interface Box { x: number; y: number; w: number; h: number }
export interface CellBox extends Box { rowIndex: number; cellIndex: number; cell: HeaderCell }
export interface HeaderGeometry { width: number; height: number; cells: CellBox[]; logo: Box | null }

/** "#RRGGBB" atau "#RRGGBBAA" (r,g,b 0-255; a 0-1). Selain itu null. */
export function parseColor(value: string | null | undefined): Rgba | null {
  const m = /^#([0-9a-f]{6})([0-9a-f]{2})?$/i.exec(value ?? "");
  if (!m) return null;
  const n = parseInt(m[1], 16);
  return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255, a: m[2] ? parseInt(m[2], 16) / 255 : 1 };
}

/** Warna di atas kertas putih (alpha dicampur). */
export function overWhite(c: Rgba): Rgba {
  const mix = (v: number): number => Math.round(v * c.a + 255 * (1 - c.a));
  return { r: mix(c.r), g: mix(c.g), b: mix(c.b), a: 1 };
}

export function luminance(c: Rgba): number {
  const lin = (v: number): number => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  };
  return 0.2126 * lin(c.r) + 0.7152 * lin(c.g) + 0.0722 * lin(c.b);
}

/** Rasio kontras WCAG antara dua warna (1-21), alpha dicampur dengan kertas putih. */
export function contrastRatio(a: Rgba, b: Rgba): number {
  const la = luminance(overWhite(a)), lb = luminance(overWhite(b));
  return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}

/** Teks "otomatis": terang di atas latar gelap, gelap di atas latar terang atau kosong. */
export function autoTone(background: string | null): "light" | "dark" {
  const bg = parseColor(background);
  if (!bg) return "dark";
  const lum = luminance(overWhite(bg));
  return lum < 0.18 ? "light" : "dark";
}

/** Ukuran huruf: pilihan penulis, atau rumus lama dari tinggi baris. */
export function fontSizeOf(cell: HeaderCell, rowHeight: number): number {
  return cell.fontSize ?? Math.min(9, Math.max(6.5, rowHeight * 0.42));
}

export function headerHeight(layout: HeaderLayout): number {
  return layout.rows.reduce((sum, row) => sum + row.height, 0);
}

export function logoRowCount(layout: HeaderLayout): number {
  return layout.logo.enabled ? Math.min(layout.logo.rows, layout.rows.length) : 0;
}

/** Kotak setiap sel dan kotak logo untuk kop selebar `boxW`. */
export function computeGeometry(layout: HeaderLayout, boxW: number): HeaderGeometry {
  const logoRows = logoRowCount(layout);
  const logoW = logoRows > 0 ? (boxW * layout.logo.width) / 100 : 0;
  const logoLeft = layout.logo.side === "left";
  const cells: CellBox[] = [];
  let y = 0;
  let logoH = 0;
  layout.rows.forEach((row, rowIndex) => {
    const beside = rowIndex < logoRows;
    if (beside) logoH += row.height;
    const rowW = boxW - (beside ? logoW : 0);
    let x = beside && logoLeft ? logoW : 0;
    const sum = row.cells.reduce((s, c) => s + c.width, 0) || 1;
    row.cells.forEach((cell, cellIndex) => {
      const w = (rowW * cell.width) / sum;
      cells.push({ x, y, w, h: row.height, rowIndex, cellIndex, cell });
      x += w;
    });
    y += row.height;
  });
  const logo = logoRows > 0 ? { x: logoLeft ? 0 : boxW - logoW, y: 0, w: logoW, h: logoH } : null;
  return { width: boxW, height: y, cells, logo };
}

/** Gambar logo "contain" di dalam kotaknya (tidak terpotong, tidak terdistorsi, tidak diperbesar). */
export function fitLogo(box: Box, padding: number, imgW: number, imgH: number, valign: "top" | "middle" | "bottom"): Box {
  const availW = Math.max(box.w - 2 * padding, 1), availH = Math.max(box.h - 2 * padding, 1);
  const scale = Math.min(availW / imgW, availH / imgH, 1);
  const w = imgW * scale, h = imgH * scale;
  const y = valign === "top" ? box.y + padding : valign === "bottom" ? box.y + box.h - padding - h : box.y + (box.h - h) / 2;
  return { x: box.x + (box.w - w) / 2, y, w, h };
}

export type ReactiveField = "status" | "issuedDate";
export interface ResolvedText { text: string; reactive: ReactiveField | null }

/**
 * Teks sel dengan isian terpasang. Status dan tanggal terbit tidak diisi saat
 * compose (dicap saat dokumen dilihat): teks sebelum isian itu menjadi `text`,
 * `reactive` menyebut isian yang menyusul.
 */
export function resolveText(cell: HeaderCell, values: Record<string, string>, pageNo: number, pageTotal: number): ResolvedText {
  let reactive: ReactiveField | null = null;
  const text = cell.text.replace(/\{([A-Za-z]+)\}/g, (_m, name: string) => {
    if (name === "status" || name === "issuedDate") { reactive = name; return ""; }
    if (name === "page") return `Page ${pageNo} Of ${pageTotal}`;
    return values[name] ?? "";
  });
  return { text: cell.uppercase ? text.toUpperCase() : text, reactive };
}
