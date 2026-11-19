import { parseColor } from '@/lib/headerGeometry';

export type Hsl = { h: number; s: number; l: number };

const channel = (value: number): string =>
    Math.round(Math.min(255, Math.max(0, value)))
        .toString(16)
        .padStart(2, '0')
        .toUpperCase();

/** The colour when it is a valid hex, so a style binding never receives anything else. */
export function safeColor(
    value: string | null | undefined,
): string | undefined {
    return parseColor(value) ? (value as string) : undefined;
}

export function hslToHex({ h, s, l }: Hsl): string {
    const sat = s / 100;
    const light = l / 100;
    const k = (n: number): number => (n + h / 30) % 12;
    const a = sat * Math.min(light, 1 - light);
    const f = (n: number): number =>
        light - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));

    return `#${channel(f(0) * 255)}${channel(f(8) * 255)}${channel(f(4) * 255)}`;
}

export function hexToHueSat(hex: string): Hsl {
    const color = parseColor(hex);

    if (!color) return { h: 0, s: 0, l: 50 };

    const [r, g, b] = [color.r / 255, color.g / 255, color.b / 255];
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const l = (max + min) / 2;
    const d = max - min;

    if (d === 0) return { h: 0, s: 0, l: Math.round(l * 100) };

    const s = d / (1 - Math.abs(2 * l - 1));
    const h =
        max === r
            ? ((g - b) / d) % 6
            : max === g
              ? (b - r) / d + 2
              : (r - g) / d + 4;

    return {
        h: Math.round((h * 60 + 360) % 360),
        s: Math.round(s * 100),
        l: Math.round(l * 100),
    };
}

/** three or six digits typed by a person, normalised to upper-case six; null when it is not a colour. */
export function normalizeHex(input: string): string | null {
    const raw = input.trim().replace(/^#/, '');
    const full = /^[0-9a-f]{3}$/i.test(raw)
        ? raw
              .split('')
              .map((c) => c + c)
              .join('')
        : raw;

    return /^[0-9a-f]{6}([0-9a-f]{2})?$/i.test(full)
        ? `#${full.toUpperCase()}`
        : null;
}

/** CSS gradient through given colours (all valid hex made by hslToHex). */
export function gradient(stops: string[]): string {
    return `linear-gradient(to right, ${stops.join(', ')})`;
}
