<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

/**
 * Routes the lines of a flow diagram (port of dcs/pdf-flow-route.ts): right-angled
 * lines that avoid the step boxes, never share a stretch with another line, and obey
 * the sides and bends an author chose. Pure geometry; PDF y grows upward.
 *
 * Boxes: id, cx, top, w, h, lane, row. Edges: from, to, kind (next|yes|no), label, exit, entry, points.
 */
final class FlowRouter
{
    /** @var array<string, array<string, mixed>> */
    private array $byId = [];

    /** @var array<string, list<string>> */
    private array $use = [];

    /** @var list<array{x1: float, y1: float, x2: float, y2: float, owner: string}> */
    private array $used = [];

    /**
     * @param  list<array<string, mixed>>  $boxes
     * @param  list<array<string, mixed>>  $edges
     * @param  array{laneLeft: callable(int): float, laneW: float, gap: float, place: callable(array{x: float, y: float}): array{x: float, y: float}}  $ctx
     * @return list<array{points: list<array{x: float, y: float}>, label: array{text: string, x: float, y: float}|null}>
     */
    public function route(array $boxes, array $edges, array $ctx): array
    {
        $this->byId = [];

        foreach ($boxes as $box) {
            $this->byId[$box['id']] = $box;
        }

        $this->use = [];
        $this->used = [];
        $sideBranch = $this->sideBranches($edges);
        $options = $this->sideOptions($edges, $sideBranch);
        $lanes = array_values(array_unique(array_map(fn (array $b): int => $b['lane'], $boxes)));
        $xs = [];

        foreach ($lanes as $lane) {
            $xs[] = $ctx['laneLeft']($lane) + 7;
            $xs[] = $ctx['laneLeft']($lane) + $ctx['laneW'] - 7;
        }

        $ys = [];

        foreach ($boxes as $b) {
            foreach ([$b['top'] + $ctx['gap'] / 2, $b['top'] - $b['h'] - $ctx['gap'] / 2] as $y) {
                if (! in_array($y, $ys, false)) {
                    $ys[] = $y;
                }
            }
        }

        $reserved = [];

        foreach ($edges as $i => $e) {
            foreach ([isset($e['exit']) ? "{$e['from']}:{$e['exit']}" : null, isset($e['entry']) ? "{$e['to']}:{$e['entry']}" : null] as $key) {
                if ($key !== null) {
                    $reserved[$key][] = $i;
                }
            }
        }

        $manual = $this->manualRoutes($edges, $ctx);
        $result = [];

        foreach ($edges as $index => $e) {
            if (isset($manual[$index])) {
                $result[] = ['points' => $manual[$index], 'label' => $this->labelFor($e, $manual[$index])];

                continue;
            }

            $a = $this->byId[$e['from']] ?? null;
            $b = $this->byId[$e['to']] ?? null;

            if ($a === null || $b === null || $options[$index] === []) {
                continue;
            }

            $owner = "{$e['from']}>{$e['to']}#{$index}";
            $chosen = null;

            foreach ($options[$index] as $rank => [$exit, $entry]) {
                $r = $this->path($a, $b, $exit, $entry, $owner, $ctx, $xs, $ys);

                if ($r === null) {
                    continue;
                }

                $penalty = (! isset($e['exit']) && $this->busy($a['id'], $exit, $index, $reserved) ? 300 : 0) + (! isset($e['entry']) && $this->busy($b['id'], $entry, $index, $reserved) ? 300 : 0);
                $score = $r['score'] + $penalty + 90 * $rank;

                if ($chosen === null || $score < $chosen['score']) {
                    $chosen = ['pts' => $r['pts'], 'score' => $score, 'exit' => $exit, 'entry' => $entry];
                }
            }

            if ($chosen === null) {
                continue;
            }

            $this->take($a['id'], $chosen['exit']);
            $this->take($b['id'], $chosen['entry']);
            $this->claim($chosen['pts'], $owner);
            $result[] = ['points' => $chosen['pts'], 'label' => $this->labelFor($e, $chosen['pts'])];
        }

        return $result;
    }

    /**
     * Branches of a decision that leave from a side; one branch to the box right below leaves from the bottom.
     *
     * @param  list<array<string, mixed>>  $edges
     * @return array<int, true>
     */
    private function sideBranches(array $edges): array
    {
        $decisions = [];

        foreach ($edges as $i => $e) {
            if ($e['kind'] !== 'next') {
                $decisions[$e['from']][] = $i;
            }
        }

        $side = [];

        foreach ($decisions as $from => $indexes) {
            $src = $this->byId[$from] ?? null;
            $below = array_values(array_filter($indexes, function (int $i) use ($edges, $src): bool {
                $t = $this->byId[$edges[$i]['to']] ?? null;

                return $src !== null && $t !== null && $t['lane'] === $src['lane'] && $t['row'] > $src['row'];
            }));
            $yes = null;

            foreach ($indexes as $i) {
                if ($edges[$i]['kind'] === 'yes') {
                    $yes = $i;
                    break;
                }
            }

            $down = count($below) === 1 ? $below[0] : ($yes ?? $indexes[0]);

            foreach ($indexes as $i) {
                if ($i !== $down) {
                    $side[$i] = true;
                }
            }
        }

        return $side;
    }

    /**
     * @param  list<array<string, mixed>>  $edges
     * @param  array<int, true>  $sideBranch
     * @return list<list<array{0: string, 1: string}>>
     */
    private function sideOptions(array $edges, array $sideBranch): array
    {
        $options = [];
        $toward = fn (array $a, array $b): string => $b['cx'] >= $a['cx'] ? 'right' : 'left';

        foreach ($edges as $i => $e) {
            $a = $this->byId[$e['from']] ?? null;
            $b = $this->byId[$e['to']] ?? null;

            if ($a === null || $b === null) {
                $options[$i] = [];

                continue;
            }

            $forward = $b['row'] > $a['row'];
            $lateral = $b['row'] === $a['row'] && $a['lane'] !== $b['lane'];
            $out = $a['lane'] === $b['lane'] ? 'right' : $toward($a, $b);
            $flip = FlowGeometry::FLIP;
            $exits = isset($e['exit']) ? [$e['exit']] : ($lateral ? [$toward($a, $b), 'bottom'] : ($forward && ! isset($sideBranch[$i]) ? ['bottom', $out, $flip[$out]] : [$out, $flip[$out], 'bottom']));
            $pairs = [];

            foreach ($exits as $exit) {
                $back = $a['lane'] === $b['lane'] ? ($exit === 'left' ? 'left' : 'right') : $toward($b, $a);
                $entries = isset($e['entry']) ? [$e['entry']] : ($lateral ? [$toward($b, $a), 'top'] : ($forward ? ['top', $toward($b, $a), $flip[$toward($b, $a)]] : [$back, 'top', $flip[$back]]));

                foreach ($entries as $entry) {
                    $pairs[] = [$exit, $entry];
                }
            }

            $options[$i] = $pairs;
        }

        return $options;
    }

    /**
     * Lines whose bends the author set are drawn exactly, first, so automatic lines avoid them.
     *
     * @param  list<array<string, mixed>>  $edges
     * @return array<int, list<array{x: float, y: float}>>
     */
    private function manualRoutes(array $edges, array $ctx): array
    {
        $manual = [];

        foreach ($edges as $index => $e) {
            $a = $this->byId[$e['from']] ?? null;
            $b = $this->byId[$e['to']] ?? null;

            if ($a === null || $b === null || empty($e['points'])) {
                continue;
            }

            $exit = $e['exit'] ?? 'bottom';
            $entry = $e['entry'] ?? 'top';
            $p = $this->port($a, $exit);
            $q = $this->port($b, $entry);
            $this->take($a['id'], $exit);
            $this->take($b['id'], $entry);
            $mids = array_map($ctx['place'], $e['points']);
            $exitVertical = $exit === 'top' || $exit === 'bottom';
            $entryVertical = $entry === 'top' || $entry === 'bottom';
            $first = $mids[0];
            $last = $mids[count($mids) - 1];
            $out = $exitVertical ? ['x' => $p['x'], 'y' => $first['y']] : ['x' => $first['x'], 'y' => $p['y']];
            $into = $entryVertical ? ['x' => $q['x'], 'y' => $last['y']] : ['x' => $last['x'], 'y' => $q['y']];
            $points = FlowGeometry::orthogonal([$p, $out, ...$mids, $into, $q], $exitVertical);
            $clean = FlowGeometry::tidy($points) ?? $points;
            $this->claim($clean, "{$e['from']}>{$e['to']}#{$index}");
            $manual[$index] = $clean;
        }

        return $manual;
    }

    /**
     * The best path for one pair of sides: straight out, along a lane edge or row gap, straight in.
     *
     * @param  list<float>  $xs
     * @param  list<float>  $ys
     * @return array{pts: list<array{x: float, y: float}>, score: float}|null
     */
    private function path(array $a, array $b, string $exit, string $entry, string $owner, array $ctx, array $xs, array $ys): ?array
    {
        $p = $this->port($a, $exit);
        $q = $this->port($b, $entry);
        $p1 = $this->stub($a, $exit, $p, $ctx);
        $q1 = $this->stub($b, $entry, $q, $ctx);
        $middles = [[], [['x' => $q1['x'], 'y' => $p1['y']]], [['x' => $p1['x'], 'y' => $q1['y']]]];

        foreach (FlowGeometry::TRIES as $t) {
            foreach ($xs as $x) {
                $middles[] = [['x' => $x + $t * FlowGeometry::STEP, 'y' => $p1['y']], ['x' => $x + $t * FlowGeometry::STEP, 'y' => $q1['y']]];
            }

            foreach ($ys as $y) {
                $middles[] = [['x' => $p1['x'], 'y' => $y + $t * FlowGeometry::STEP], ['x' => $q1['x'], 'y' => $y + $t * FlowGeometry::STEP]];
            }
        }

        $best = null;

        foreach ($middles as $m) {
            $pts = FlowGeometry::tidy([$p, $p1, ...$m, $q1, $q]);

            if ($pts === null || count($pts) < 2) {
                continue;
            }

            $square = true;

            foreach ($pts as $i => $v) {
                if ($i > 0 && $v['x'] != $pts[$i - 1]['x'] && $v['y'] != $pts[$i - 1]['y']) {
                    $square = false;
                    break;
                }
            }

            if (! $square) {
                continue;
            }

            $score = 25 * (count($pts) - 2);

            foreach ($pts as $i => $v) {
                if ($i === 0) {
                    continue;
                }

                $u = $pts[$i - 1];
                $skip = [...($i === 1 ? [$a['id']] : []), ...($i === count($pts) - 1 ? [$b['id']] : [])];

                if ($this->hitsBox($u, $v, $skip)) {
                    $score += 10000;
                }

                $score += abs($v['x'] - $u['x']) + abs($v['y'] - $u['y']) + 500 * FlowGeometry::overlapCount($this->used, $u, $v, $owner) + 150 * FlowGeometry::crossingCount($this->used, $u, $v, $owner);
            }

            if ($best === null || $score < $best['score']) {
                $best = ['pts' => $pts, 'score' => $score];
            }
        }

        return $best;
    }

    /** @param  list<string>  $skip */
    private function hitsBox(array $p, array $q, array $skip): bool
    {
        [$x1, $x2, $y1, $y2] = [min($p['x'], $q['x']), max($p['x'], $q['x']), min($p['y'], $q['y']), max($p['y'], $q['y'])];

        foreach ($this->byId as $b) {
            if (in_array($b['id'], $skip, true)) {
                continue;
            }

            if ($x2 > $b['cx'] - $b['w'] / 2 - FlowGeometry::PAD && $x1 < $b['cx'] + $b['w'] / 2 + FlowGeometry::PAD && $y2 > $b['top'] - $b['h'] - FlowGeometry::PAD && $y1 < $b['top'] + FlowGeometry::PAD) {
                return true;
            }
        }

        return false;
    }

    /** The point on a box side: the first line in the middle, the next ones on the same side shifted. */
    private function port(array $b, string $side): array
    {
        $k = count(array_filter($this->use[$b['id']] ?? [], fn (string $s): bool => $s === $side));
        $vertical = $side === 'top' || $side === 'bottom';
        $shift = ($k % 2 ? 1 : -1) * ceil($k / 2) * ($vertical ? min(14, $b['w'] / 4) : min(8, $b['h'] / 4));

        return match ($side) {
            'top' => ['x' => $b['cx'] + $shift, 'y' => $b['top']],
            'bottom' => ['x' => $b['cx'] + $shift, 'y' => $b['top'] - $b['h']],
            default => ['x' => $b['cx'] + ($side === 'right' ? $b['w'] / 2 : -$b['w'] / 2), 'y' => $b['top'] - $b['h'] / 2 - $shift],
        };
    }

    private function stub(array $b, string $side, array $p, array $ctx): array
    {
        $length = $side === 'top' || $side === 'bottom' ? $ctx['gap'] / 2 : max(6, $ctx['laneW'] / 2 - $b['w'] / 2 - 7);

        return ['x' => $p['x'] + FlowGeometry::OUT[$side]['x'] * $length, 'y' => $p['y'] + FlowGeometry::OUT[$side]['y'] * $length];
    }

    private function take(string $id, string $side): void
    {
        $this->use[$id][] = $side;
    }

    /** @param  array<string, list<int>>  $reserved */
    private function busy(string $id, string $side, int $edge, array $reserved): bool
    {
        return in_array($side, $this->use[$id] ?? [], true) || count(array_filter($reserved["{$id}:{$side}"] ?? [], fn (int $i): bool => $i !== $edge)) > 0;
    }

    /** @param  list<array{x: float, y: float}>  $points */
    private function claim(array $points, string $owner): void
    {
        foreach ($points as $i => $v) {
            if ($i > 0) {
                $this->used[] = ['x1' => $points[$i - 1]['x'], 'y1' => $points[$i - 1]['y'], 'x2' => $v['x'], 'y2' => $v['y'], 'owner' => $owner];
            }
        }
    }

    /** @param  list<array{x: float, y: float}>  $points */
    private function labelFor(array $e, array $points): ?array
    {
        if (empty($e['label'])) {
            return null;
        }

        $first = $points[0];
        $second = $points[1] ?? $points[0];
        $down = $second['x'] == $first['x'];

        return ['text' => $e['label'], 'x' => $down ? $first['x'] + 5 : $first['x'] + ($second['x'] > $first['x'] ? 4 : -22), 'y' => $down ? $first['y'] - 11 : $first['y'] + 3];
    }
}
