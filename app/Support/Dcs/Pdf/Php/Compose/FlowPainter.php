<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

use App\Support\Dcs\Pdf\Php\Fonts\StandardFont;

/**
 * The flow diagram page(s) of a document (port of drawFlowDiagram and its variants in
 * dcs/pdf-compose.ts): the swimlane graph with routed lines, the legacy single column
 * and swimlane charts, and the table style. Diagrams print black on white, in a blue
 * frame titled with the document.
 */
final class FlowPainter
{
    private const BLUE = [0.43, 0.55, 0.8];

    private const BLUE_FILL = [0.86, 0.9, 0.97];

    private const LABEL_BLUE = [0.16, 0.32, 0.75];

    public function __construct(private Page $doc, private string $title, private string $number) {}

    /**
     * @param  list<array<string, mixed>>  $steps
     * @param  list<string>|null  $lanes
     */
    public function draw(array $steps, StandardFont $font, StandardFont $bold, ?array $lanes, ?string $displayStyle): void
    {
        $ordered = $steps;
        usort($ordered, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        if ($displayStyle === 'table') {
            $this->table($ordered, $bold, $font);

            return;
        }

        $hasEdges = false;

        foreach ($steps as $s) {
            $hasEdges = $hasEdges || ! empty($s['nextId']) || ! empty($s['yesId']) || ! empty($s['noId']);
        }

        $source = $hasEdges ? $steps : $ordered;
        $configured = array_values(array_filter($lanes ?? [], fn (string $l): bool => count(array_filter($source, fn (array $s): bool => ($s['actor'] ?? null) === $l)) > 0));
        $derived = array_values(array_unique(array_filter(array_map(fn (array $s) => $s['actor'] ?? null, $source), fn ($a): bool => ! empty($a))));
        $effective = count($configured) > 1 ? $configured : (count($derived) > 1 ? $derived : []);

        if ($hasEdges) {
            $this->graph($steps, $font, $effective, $lanes ?? []);
        } elseif (count($effective) > 1) {
            $this->swimlane($ordered, $font, $effective);
        } else {
            $this->singleColumn($ordered, $font);
        }
    }

    /** @param  list<array<string, mixed>>  $ordered */
    private function singleColumn(array $ordered, StandardFont $font): void
    {
        $l = $this->doc->layout;
        $page = $this->doc->addPage();
        $page->text('Diagram Alir', $font, 14, $l->mLeft, $l->contentTopY, self::LABEL_BLUE);
        [$nodeW, $nodeH, $gap] = [280, 46, 34];
        $y = $l->contentTopY - 40;
        $cx = Layout::PAGE_W / 2;
        $grey = [0.4, 0.42, 0.48];

        foreach ($ordered as $i => $step) {
            if ($y - $nodeH < $l->mBottom + 30) {
                break;
            }

            FlowShapes::step($page, $font, $step, $cx, $y, $nodeW, $nodeH);
            $page->text((string) $step['order'], $font, 9, $cx - $nodeW / 2 - 16, $y - $nodeH / 2 - 3, [0.5, 0.52, 0.58]);

            if ($i !== count($ordered) - 1) {
                $page->line($cx, $y - $nodeH, $cx, $y - $nodeH - $gap + 6, 1.2, $grey);

                if ($step['type'] === 'decision') {
                    $page->text('Ya', $font, 8, $cx + 6, $y - $nodeH - $gap / 2, [0.06, 0.6, 0.4]);
                }
            }

            if ($step['type'] === 'decision' && ! empty($step['noTargetOrder'])) {
                $page->text("Tidak -> Langkah {$step['noTargetOrder']}", $font, 8, $cx + $nodeW / 2 + 10, $y - $nodeH / 2, [0.87, 0.2, 0.3]);
            }

            $y -= $nodeH + $gap;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $ordered
     * @param  list<string>  $lanes
     */
    private function swimlane(array $ordered, StandardFont $font, array $lanes): void
    {
        $l = $this->doc->layout;
        $page = $this->doc->addPage();
        $page->text('Diagram Alir', $font, 14, $l->mLeft, $l->contentTopY, self::LABEL_BLUE);
        $laneW = $l->contentW / count($lanes);
        $headerTopY = $l->contentTopY - 34;

        foreach ($lanes as $li => $lane) {
            $lx = $l->mLeft + $laneW * $li;
            $page->rect($lx, $headerTopY, $laneW, 22, [0.94, 0.95, 0.98], [0.7, 0.72, 0.78], 0.75);
            $label = TextFlow::clip($lane, 18);
            $page->text($label, $font, 8.5, $lx + $laneW / 2 - $font->widthOfTextAtSize($label, 8.5) / 2, $headerTopY + 7, [0.16, 0.16, 0.2]);
        }

        $bodyBottom = $l->mBottom + 20;

        for ($li = 1; $li < count($lanes); $li++) {
            $lx = $l->mLeft + $laneW * $li;
            $page->line($lx, $headerTopY, $lx, $bodyBottom, 0.5, [0.85, 0.86, 0.9]);
        }

        $nodeW = max(min($laneW - 20, 200), 80);
        [$nodeH, $gap] = [40, 28];
        $y = $headerTopY - 30;
        $centers = [];

        foreach ($ordered as $step) {
            if ($y - $nodeH < $bodyBottom) {
                break;
            }

            $idx = array_search($step['actor'] ?? '', $lanes, true);
            $cx = $l->mLeft + $laneW * max(0, $idx === false ? -1 : $idx) + $laneW / 2;
            FlowShapes::step($page, $font, $step, $cx, $y, $nodeW, $nodeH);
            $centers[$step['order']] = ['x' => $cx, 'y' => $y];
            $y -= $nodeH + $gap;
        }

        $grey = [0.4, 0.42, 0.48];

        for ($i = 0; $i < count($ordered) - 1; $i++) {
            $a = $centers[$ordered[$i]['order']] ?? null;
            $b = $centers[$ordered[$i + 1]['order']] ?? null;

            if ($a === null || $b === null) {
                continue;
            }

            $aBottom = $a['y'] - $nodeH;
            $bTop = $b['y'];

            if (abs($a['x'] - $b['x']) < 1) {
                $page->line($a['x'], $aBottom, $b['x'], $bTop, 1.2, $grey);
            } else {
                $mid = ($aBottom + $bTop) / 2;
                $page->line($a['x'], $aBottom, $a['x'], $mid, 1.2, $grey);
                $page->line($a['x'], $mid, $b['x'], $mid, 1.2, $grey);
                $page->line($b['x'], $mid, $b['x'], $bTop, 1.2, $grey);
            }

            if ($ordered[$i]['type'] === 'decision') {
                $page->text('Ya', $font, 7, $a['x'] + 5, $aBottom - 9, [0.06, 0.6, 0.4]);
            }
        }

        foreach ($ordered as $step) {
            if ($step['type'] === 'decision' && ! empty($step['noTargetOrder']) && isset($centers[$step['order']])) {
                $c = $centers[$step['order']];
                $x = min($c['x'] + $nodeW / 2 + 6, Layout::PAGE_W - $l->mRight - 90);
                $page->text("Tidak -> Langkah {$step['noTargetOrder']}", $font, 7, $x, $c['y'] - $nodeH / 2, [0.87, 0.2, 0.3]);
            }
        }
    }

    /**
     * Steps with explicit edges: rows from the graph, lanes by actor, lines routed around the boxes.
     *
     * @param  list<array<string, mixed>>  $steps
     * @param  list<string>  $lanes
     * @param  list<string>  $allLanes
     */
    private function graph(array $steps, StandardFont $font, array $lanes, array $allLanes): void
    {
        $l = $this->doc->layout;
        $bold = $this->doc->bodyBold;
        $page = $this->doc->addPage();
        $page->rect($l->mLeft, $l->contentTopY - 40, $l->contentW, 40, self::BLUE_FILL, self::BLUE, 1);
        $head = TextFlow::wrap(mb_strtoupper($this->title).' - DIAGRAM ALIR', $bold, 11, $l->contentW - 12)[0] ?? '';
        $page->text($head, $bold, 11, $l->mLeft + 6, $l->contentTopY - 16, [0, 0, 0]);
        $page->text($this->number, $bold, 11, $l->mLeft + 6, $l->contentTopY - 33, [0, 0, 0]);

        $hasLaneHeaders = count($lanes) > 1;
        $effectiveLanes = $hasLaneHeaders ? $lanes : ['_'];
        $graph = FlowGraph::layout($steps, $hasLaneHeaders ? $lanes : []);
        $laneW = $l->contentW / count($effectiveLanes);
        $headerTopY = $l->contentTopY - ($hasLaneHeaders ? 64 : 44);
        $bodyBottom = $l->mBottom + 20;
        $lineColor = [0.0, 0.0, 0.0];
        $nodeW = max(min($laneW - 34, 128), 70);
        [$nodeH, $gap] = [34, 34];
        $rowTopY = $headerTopY - ($hasLaneHeaders ? 28 : 40);
        $byId = [];

        foreach ($steps as $s) {
            if (! empty($s['id'])) {
                $byId[$s['id']] = $s;
            }
        }

        $clamp = fn (mixed $v, float $min, float $max): float => min($max, max($min, is_numeric($v) ? (float) $v : 1.0));
        $sizeOf = fn (array $step): array => [
            'w' => min($nodeW * $clamp($step['size']['w'] ?? null, 0.5, 1.3), $laneW - 20),
            'h' => $nodeH * $clamp($step['size']['h'] ?? null, 0.6, 3),
        ];
        $rowH = [];

        foreach ($graph['positions'] as $id => $pos) {
            if (isset($byId[$id])) {
                $rowH[$pos['row']] = max($rowH[$pos['row']] ?? $nodeH, $sizeOf($byId[$id])['h']);
            }
        }

        $rowTop = function (int $row) use ($rowTopY, $rowH, $nodeH, $gap): float {
            $y = $rowTopY;

            for ($r = 0; $r < $row; $r++) {
                $y -= ($rowH[$r] ?? $nodeH) + $gap;
            }

            return $y;
        };
        $boxes = [];
        $placed = [];

        foreach ($graph['positions'] as $id => $pos) {
            if (! isset($byId[$id])) {
                continue;
            }

            $step = $byId[$id];
            ['w' => $w, 'h' => $h] = $sizeOf($step);
            $y = $rowTop($pos['row']) - (($rowH[$pos['row']] ?? $nodeH) - $h) / 2 - ($step['manualOffset']['dyRow'] ?? 0) * ($nodeH + $gap);

            if ($y - $h < $bodyBottom) {
                continue;
            }

            $cx = $l->mLeft + $laneW * $pos['laneIndex'] + $laneW / 2 + ($step['manualOffset']['dxLane'] ?? 0) * $laneW;
            $placed[$id] = true;
            $boxes[] = ['id' => $id, 'cx' => $cx, 'top' => $y, 'w' => $w, 'h' => $h, 'lane' => $pos['laneIndex'], 'row' => $pos['row']];
        }

        $lowest = $boxes === [] ? $rowTopY : min(array_map(fn (array $b): float => $b['top'] - $b['h'], $boxes));
        $frameBottom = max($bodyBottom, $lowest - $gap);

        if ($hasLaneHeaders) {
            foreach ($effectiveLanes as $li => $lane) {
                $lx = $l->mLeft + $laneW * $li;
                $page->rect($lx, $headerTopY, $laneW, 24, self::BLUE_FILL, self::BLUE, 1);
                $label = TextFlow::clip(mb_strtoupper($lane), 18);
                $page->text($label, $bold, 10, $lx + $laneW / 2 - $bold->widthOfTextAtSize($label, 10) / 2, $headerTopY + 8, [0.16, 0.16, 0.2]);
            }
        }

        for ($li = 1; $li < count($effectiveLanes); $li++) {
            $lx = $l->mLeft + $laneW * $li;
            $page->line($lx, $headerTopY, $lx, $frameBottom, 1, self::BLUE);
        }

        $page->rect($l->mLeft, $frameBottom, $l->contentW, ($hasLaneHeaders ? $headerTopY : $l->contentTopY - 40) - $frameBottom, null, self::BLUE, 1);

        foreach ($boxes as $b) {
            FlowShapes::step($page, $font, $byId[$b['id']], $b['cx'], $b['top'], $b['w'], $b['h'], ! $hasLaneHeaders);
        }

        $edges = [];

        foreach ($graph['edges'] as $e) {
            $kind = $e['label'] === 'Tidak' ? 'no' : ($e['label'] === 'Ya' ? 'yes' : 'next');
            $ports = $byId[$e['from']]['ports'][$kind] ?? [];
            $edges[] = ['from' => $e['from'], 'to' => $e['to'], 'kind' => $kind, 'label' => $e['label'], 'exit' => $ports['exit'] ?? null, 'entry' => $ports['entry'] ?? null, 'points' => $ports['points'] ?? null];
        }

        $edges = array_values(array_filter(array_map(fn (array $e): array => array_filter($e, fn ($v): bool => $v !== null), $edges), fn (array $e): bool => isset($placed[$e['from']], $placed[$e['to']])));
        $shown = $hasLaneHeaders ? $effectiveLanes : array_values(array_filter($allLanes, fn (string $lane): bool => count(array_filter($steps, fn (array $st): bool => ($st['actor'] ?? null) === $lane)) > 0));
        $place = function (array $p) use ($rowTop, $rowH, $nodeH, $gap, $shown, $allLanes, $l, $laneW): array {
            $row = max(0, (int) floor($p['y']));
            $bandTop = $rowTop($row) + $gap / 2;
            $bandH = ($rowH[$row] ?? $nodeH) + $gap;
            $at = max(0, (int) floor($p['x']));
            $name = $allLanes[$at] ?? '';
            $before = count(array_filter(array_slice($allLanes, 0, $at), fn (string $x): bool => in_array($x, $shown, true)));
            $lane = $allLanes === [] || $shown === [] ? $p['x'] : min(count($shown), in_array($name, $shown, true) ? $before + ($p['x'] - $at) : $before);

            return ['x' => $l->mLeft + $laneW * $lane, 'y' => $bandTop - ($p['y'] - $row) * $bandH];
        };

        foreach ((new FlowRouter)->route($boxes, $edges, ['laneLeft' => fn (int $lane): float => $l->mLeft + $laneW * $lane, 'laneW' => $laneW, 'gap' => $gap, 'place' => $place]) as $routed) {
            $pts = $routed['points'];

            foreach ($pts as $i => $p) {
                if ($i > 0) {
                    $page->line($pts[$i - 1]['x'], $pts[$i - 1]['y'], $p['x'], $p['y'], 1.1, $lineColor);
                }
            }

            $n = count($pts);
            FlowShapes::arrow($page, $pts[$n - 2], $pts[$n - 1], $lineColor);

            if ($routed['label'] !== null) {
                $page->text($routed['label']['text'], $font, 7, $routed['label']['x'], $routed['label']['y'], $lineColor);
            }
        }
    }

    /**
     * The "working instruction" style: a three column table, one row per step in order.
     *
     * @param  list<array<string, mixed>>  $ordered
     */
    private function table(array $ordered, StandardFont $bold, StandardFont $regular): void
    {
        $l = $this->doc->layout;
        $page = $this->doc->addPage();
        $page->text('Diagram Alir', $bold, 14, $l->mLeft, $l->contentTopY, self::LABEL_BLUE);
        [$shapeColW, $picColW] = [90, 90];
        $instrColW = $l->contentW - $shapeColW - $picColW;
        $headers = ['Diagram Alir', 'Instruksi Kerja', 'PIC'];
        $widths = [$shapeColW, $instrColW, $picColW];
        $y = $l->contentTopY - 30;
        $headerRow = function () use (&$page, &$y, $headers, $widths, $bold, $l): void {
            $x = $l->mLeft;

            foreach ($headers as $i => $header) {
                $page->rect($x, $y - 20, $widths[$i], 20, [0.94, 0.95, 0.98], [0.7, 0.72, 0.78], 0.75);
                $page->text($header, $bold, 9, $x + $widths[$i] / 2 - $bold->widthOfTextAtSize($header, 9) / 2, $y - 14, [0.16, 0.16, 0.2]);
                $x += $widths[$i];
            }

            $y -= 20;
        };
        $headerRow();
        $shapeH = 32;
        $border = [0.85, 0.86, 0.9];
        $ink = [0.2, 0.22, 0.27];

        foreach ($ordered as $step) {
            $lines = TextFlow::wrap($step['text'], $regular, 8.5, $instrColW - 10);
            $rowH = max($shapeH + 14, count($lines) * 12 + 10, 34);

            if ($y - $rowH < $l->mBottom) {
                $page = $this->doc->addPage();
                $y = $l->contentTopY;
                $headerRow();
            }

            $x = $l->mLeft;
            $page->rect($x, $y - $rowH, $widths[0], $rowH, null, $border, 0.5);
            FlowShapes::outline($page, $step, $x + $widths[0] / 2, $y - ($rowH - $shapeH) / 2, 46, $shapeH, 1.1);
            $x += $widths[0];
            $page->rect($x, $y - $rowH, $widths[1], $rowH, null, $border, 0.5);
            $ty = $y - 14;

            foreach ($lines as $line) {
                $page->text($line, $regular, 8.5, $x + 5, $ty, $ink);
                $ty -= 12;
            }

            $x += $widths[1];
            $page->rect($x, $y - $rowH, $widths[2], $rowH, null, $border, 0.5);
            $pic = TextFlow::wrap(! empty($step['pic']) ? $step['pic'] : (! empty($step['actor']) ? $step['actor'] : '-'), $regular, 8, $widths[2] - 10);
            $py = $y - $rowH / 2 + ((count($pic) - 1) * 12) / 2 + 3;

            foreach ($pic as $line) {
                $page->text($line, $regular, 8, $x + $widths[2] / 2 - $regular->widthOfTextAtSize($line, 8) / 2, $py, $ink);
                $py -= 12;
            }

            $y -= $rowH;
        }
    }
}
