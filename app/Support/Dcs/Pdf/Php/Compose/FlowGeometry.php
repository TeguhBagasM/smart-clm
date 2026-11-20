<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

/**
 * The stateless geometry of flow-diagram lines (port of dcs/pdf-flow-route-util.ts):
 * sides, tidying a polyline, forcing right angles, and counting how many other lines
 * a segment overlaps or crosses. Points are ['x' => float, 'y' => float].
 */
final class FlowGeometry
{
    public const PAD = 3;

    public const STEP = 4;

    public const TRIES = [0, 1, -1, 2, -2, 3, -3];

    public const OUT = ['top' => ['x' => 0, 'y' => 1], 'bottom' => ['x' => 0, 'y' => -1], 'right' => ['x' => 1, 'y' => 0], 'left' => ['x' => -1, 'y' => 0]];

    public const FLIP = ['top' => 'bottom', 'bottom' => 'top', 'left' => 'right', 'right' => 'left'];

    /**
     * Drops repeated points and points in the middle of a straight run; null when the path doubles back.
     *
     * @param  list<array{x: float, y: float}>  $points
     * @return list<array{x: float, y: float}>|null
     */
    public static function tidy(array $points): ?array
    {
        $out = [];

        foreach ($points as $p) {
            $last = $out[count($out) - 1] ?? null;

            if ($last !== null && $last['x'] == $p['x'] && $last['y'] == $p['y']) {
                continue;
            }

            $out[] = $p;

            while (count($out) >= 3) {
                [$a, $b, $c] = array_slice($out, -3);

                if (! (($a['x'] == $b['x'] && $b['x'] == $c['x']) || ($a['y'] == $b['y'] && $b['y'] == $c['y']))) {
                    break;
                }

                if (! (($b['x'] - $a['x']) * ($c['x'] - $b['x']) >= 0 && ($b['y'] - $a['y']) * ($c['y'] - $b['y']) >= 0)) {
                    return null;
                }

                array_splice($out, count($out) - 2, 1);
            }
        }

        return $out;
    }

    /**
     * Adds a corner between two points that are not in line, alternating direction with the previous run.
     *
     * @param  list<array{x: float, y: float}>  $raw
     * @return list<array{x: float, y: float}>
     */
    public static function orthogonal(array $raw, bool $firstVertical): array
    {
        $points = [$raw[0]];

        for ($i = 1; $i < count($raw); $i++) {
            $u = $points[count($points) - 1];
            $v = $raw[$i];

            if ($u['x'] != $v['x'] && $u['y'] != $v['y']) {
                $prev = $points[count($points) - 2] ?? null;
                $wasVertical = $prev !== null ? $prev['x'] == $u['x'] : $firstVertical;
                $points[] = $wasVertical ? ['x' => $v['x'], 'y' => $u['y']] : ['x' => $u['x'], 'y' => $v['y']];
            }

            $points[] = $v;
        }

        return $points;
    }

    /**
     * @param  list<array{x1: float, y1: float, x2: float, y2: float, owner: string}>  $used
     * @param  array{x: float, y: float}  $p
     * @param  array{x: float, y: float}  $q
     */
    public static function overlapCount(array $used, array $p, array $q, string $owner): int
    {
        $count = 0;

        foreach ($used as $s) {
            if ($s['owner'] === $owner) {
                continue;
            }

            if ($p['y'] == $q['y'] && $s['y1'] == $s['y2']) {
                $count += abs($s['y1'] - $p['y']) < 2.5 && min($s['x1'], $s['x2']) < max($p['x'], $q['x']) - 1 && max($s['x1'], $s['x2']) > min($p['x'], $q['x']) + 1 ? 1 : 0;
            } elseif ($p['x'] == $q['x'] && $s['x1'] == $s['x2']) {
                $count += abs($s['x1'] - $p['x']) < 2.5 && min($s['y1'], $s['y2']) < max($p['y'], $q['y']) - 1 && max($s['y1'], $s['y2']) > min($p['y'], $q['y']) + 1 ? 1 : 0;
            }
        }

        return $count;
    }

    /**
     * @param  list<array{x1: float, y1: float, x2: float, y2: float, owner: string}>  $used
     * @param  array{x: float, y: float}  $p
     * @param  array{x: float, y: float}  $q
     */
    public static function crossingCount(array $used, array $p, array $q, string $owner): int
    {
        $count = 0;

        foreach ($used as $s) {
            if ($s['owner'] === $owner) {
                continue;
            }

            $h = $p['y'] == $q['y'] ? ['y' => $p['y'], 'x1' => min($p['x'], $q['x']), 'x2' => max($p['x'], $q['x'])] : ($s['y1'] == $s['y2'] ? ['y' => $s['y1'], 'x1' => min($s['x1'], $s['x2']), 'x2' => max($s['x1'], $s['x2'])] : null);
            $v = $p['x'] == $q['x'] ? ['x' => $p['x'], 'y1' => min($p['y'], $q['y']), 'y2' => max($p['y'], $q['y'])] : ($s['x1'] == $s['x2'] ? ['x' => $s['x1'], 'y1' => min($s['y1'], $s['y2']), 'y2' => max($s['y1'], $s['y2'])] : null);

            if ($h === null || $v === null || ($p['y'] == $q['y']) === ($s['y1'] == $s['y2'])) {
                continue;
            }

            $count += $v['x'] > $h['x1'] + 1 && $v['x'] < $h['x2'] - 1 && $h['y'] > $v['y1'] + 1 && $h['y'] < $v['y2'] - 1 ? 1 : 0;
        }

        return $count;
    }
}
