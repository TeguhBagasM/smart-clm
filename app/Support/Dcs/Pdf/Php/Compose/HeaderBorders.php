<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

/**
 * Which sides of each letterhead box are drawn. A box (a cell or the logo block) says which of its four
 * sides it wants; a side shared with a neighbouring box is drawn only when that neighbour wants it too, so
 * a cell switched to "no lines" really shows none, with no line left over from the cell next to it.
 * y runs down from the top of the header, like {@see HeaderGeometry}. The editor preview has the same
 * rule in `headerBorders.ts`.
 */
final class HeaderBorders
{
    private const EPSILON = 0.01;

    private const OPPOSITE = ['top' => 'bottom', 'right' => 'left', 'bottom' => 'top', 'left' => 'right'];

    /**
     * @param  list<array{x: float, y: float, w: float, h: float, want: array{top: bool, right: bool, bottom: bool, left: bool}}>  $boxes
     * @return list<array{top: bool, right: bool, bottom: bool, left: bool}>
     */
    public static function resolve(array $boxes): array
    {
        $resolved = [];

        foreach ($boxes as $i => $box) {
            $sides = [];

            foreach (self::OPPOSITE as $side => $opposite) {
                $sides[$side] = $box['want'][$side] && self::neighboursAllow($boxes, $i, $side, $opposite);
            }

            $resolved[] = $sides;
        }

        return $resolved;
    }

    /**
     * @param  list<array{x: float, y: float, w: float, h: float, want: array<string, bool>}>  $boxes
     */
    private static function neighboursAllow(array $boxes, int $index, string $side, string $opposite): bool
    {
        $a = $boxes[$index];

        foreach ($boxes as $j => $b) {
            if ($j !== $index && ! $b['want'][$opposite] && self::touches($a, $b, $side)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether `$b` lies against the `$side` edge of `$a`, sharing some length of it.
     *
     * @param  array{x: float, y: float, w: float, h: float}  $a
     * @param  array{x: float, y: float, w: float, h: float}  $b
     */
    private static function touches(array $a, array $b, string $side): bool
    {
        $overlapX = min($a['x'] + $a['w'], $b['x'] + $b['w']) - max($a['x'], $b['x']) > self::EPSILON;
        $overlapY = min($a['y'] + $a['h'], $b['y'] + $b['h']) - max($a['y'], $b['y']) > self::EPSILON;

        return match ($side) {
            'right' => abs($b['x'] - ($a['x'] + $a['w'])) < self::EPSILON && $overlapY,
            'left' => abs($b['x'] + $b['w'] - $a['x']) < self::EPSILON && $overlapY,
            'bottom' => abs($b['y'] - ($a['y'] + $a['h'])) < self::EPSILON && $overlapX,
            default => abs($b['y'] + $b['h'] - $a['y']) < self::EPSILON && $overlapX,
        };
    }
}
