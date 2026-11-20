<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

/** Places the steps of a diagram that uses explicit edges (port of layoutFlowGraph in dcs/pdf-compose.ts). */
final class FlowGraph
{
    /**
     * @param  list<array<string, mixed>>  $steps
     * @param  list<string>  $lanes
     * @return array{positions: array<string, array{laneIndex: int, row: int}>, edges: list<array{from: string, to: string, label: string|null}>}
     */
    public static function layout(array $steps, array $lanes): array
    {
        $byId = [];

        foreach ($steps as $s) {
            if (! empty($s['id'])) {
                $byId[$s['id']] = $s;
            }
        }

        $edges = [];

        foreach ($steps as $s) {
            if (empty($s['id'])) {
                continue;
            }

            if ($s['type'] === 'decision') {
                if (! empty($s['yesId']) && isset($byId[$s['yesId']])) {
                    $edges[] = ['from' => $s['id'], 'to' => $s['yesId'], 'label' => 'Ya'];
                }

                if (! empty($s['noId']) && isset($byId[$s['noId']])) {
                    $edges[] = ['from' => $s['id'], 'to' => $s['noId'], 'label' => 'Tidak'];
                }
            } elseif (! empty($s['nextId']) && isset($byId[$s['nextId']])) {
                $edges[] = ['from' => $s['id'], 'to' => $s['nextId'], 'label' => null];
            }
        }

        $adjacency = [];

        foreach ($edges as $e) {
            $adjacency[$e['from']][] = $e['to'];
        }

        $start = null;

        foreach ($steps as $s) {
            if ($s['type'] === 'start' && ! empty($s['id'])) {
                $start = $s;
                break;
            }
        }

        if ($start === null) {
            foreach ($steps as $s) {
                if (! empty($s['id'])) {
                    $start = $s;
                    break;
                }
            }
        }

        $row = [];

        foreach ($steps as $s) {
            if (! empty($s['id']) && isset($s['row']) && is_numeric($s['row']) && floor((float) $s['row']) == $s['row'] && $s['row'] >= 0) {
                $row[$s['id']] = (int) $s['row'];
            }
        }

        if ($start !== null && ! isset($row[$start['id']])) {
            $row[$start['id']] = 0;
            $queue = [$start['id']];

            while ($queue !== []) {
                $current = array_shift($queue);

                foreach ($adjacency[$current] ?? [] as $next) {
                    if (! isset($row[$next])) {
                        $row[$next] = $row[$current] + 1;
                        $queue[] = $next;
                    }
                }
            }
        }

        $maxRow = $row === [] ? -1 : max($row);

        foreach ($steps as $s) {
            if (! empty($s['id']) && ! isset($row[$s['id']])) {
                $row[$s['id']] = ++$maxRow;
            }
        }

        $laneIndex = function (?string $actor) use ($lanes): int {
            $i = $lanes === [] ? false : array_search($actor ?? '', $lanes, true);

            return $i === false ? 0 : $i;
        };

        $withId = array_values(array_filter($steps, fn (array $x): bool => ! empty($x['id'])));
        usort($withId, fn (array $a, array $b): int => (($row[$a['id']] ?? 0) <=> ($row[$b['id']] ?? 0)) ?: ($a['order'] <=> $b['order']));
        $taken = [];

        foreach ($withId as $s) {
            $r = $row[$s['id']] ?? 0;

            while (isset($taken[$laneIndex($s['actor'] ?? null).':'.$r])) {
                $r++;
            }

            $taken[$laneIndex($s['actor'] ?? null).':'.$r] = true;
            $row[$s['id']] = $r;
        }

        $positions = [];

        foreach ($steps as $s) {
            if (! empty($s['id'])) {
                $positions[$s['id']] = ['laneIndex' => $laneIndex($s['actor'] ?? null), 'row' => $row[$s['id']] ?? 0];
            }
        }

        $forward = [];
        $back = [];

        foreach ($edges as $e) {
            if (($row[$e['to']] ?? 0) > ($row[$e['from']] ?? 0)) {
                $forward[] = $e;
            } else {
                $back[] = $e;
            }
        }

        return ['positions' => $positions, 'edges' => [...$forward, ...$back]];
    }
}
