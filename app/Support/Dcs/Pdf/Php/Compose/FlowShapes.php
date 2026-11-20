<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

use App\Support\Dcs\Pdf\Php\Draw\PageCanvas;
use App\Support\Dcs\Pdf\Php\Fonts\StandardFont;

/** The symbols of a flow diagram and the text inside them (port of drawShapeOutline, drawFlowStep, drawArrow). Diagrams print black on white. */
final class FlowShapes
{
    private const BLACK = [0.0, 0.0, 0.0];

    /**
     * Draws a step's outline; returns the line colour and the text colour.
     *
     * @param  array<string, mixed>  $step
     * @return array{0: array<int, float>, 1: array<int, float>}
     */
    public static function outline(PageCanvas $page, array $step, float $cx, float $y, float $w, float $h, float $borderWidth = 1.4): array
    {
        $color = self::hex($step['color'] ?? null, self::BLACK);
        $textColor = self::hex($step['textColor'] ?? null, self::BLACK);
        $fill = ! empty($step['color']) ? array_map(fn (float $c): float => $c + (1 - $c) * 0.85, $color) : [1.0, 1.0, 1.0];

        switch ($step['type']) {
            case 'start':
            case 'end':
                $left = $cx - $w / 2 + $h / 2;
                $right = $cx + $w / 2 - $h / 2;
                $page->ellipse($left, $y - $h / 2, $h / 2, $h / 2, $fill, $color, $borderWidth);
                $page->ellipse($right, $y - $h / 2, $h / 2, $h / 2, $fill, $color, $borderWidth);
                $page->rect($left, $y - $h, $right - $left, $h, $fill, $color, 0);
                $page->line($left, $y, $right, $y, $borderWidth, $color);
                $page->line($left, $y - $h, $right, $y - $h, $borderWidth, $color);
                break;
            case 'decision':
                $page->path([['M', $cx, $y], ['L', $cx + $w / 2, $y - $h / 2], ['L', $cx, $y - $h], ['L', $cx - $w / 2, $y - $h / 2], ['Z']], $fill, $color, $borderWidth);
                break;
            case 'document':
                [$l, $r, $t, $b, $a] = [$cx - $w / 2, $cx + $w / 2, $y, $y - $h, $h * 0.12];
                $page->path([['M', $l, $t], ['L', $r, $t], ['L', $r, $b + $a], ['C', $cx + $w * 0.2, $b - $a * 0.8, $cx - $w * 0.2, $b + $a * 2.6, $l, $b + $a], ['Z']], $fill, $color, $borderWidth);
                break;
            case 'data':
                $k = $h * 0.35;
                [$l, $r] = [$cx - $w / 2, $cx + $w / 2];
                $page->path([['M', $l + $k, $y], ['L', $r, $y], ['L', $r - $k, $y - $h], ['L', $l, $y - $h], ['Z']], $fill, $color, $borderWidth);
                break;
            case 'predefined':
                $page->rect($cx - $w / 2, $y - $h, $w, $h, $fill, $color, $borderWidth);

                foreach ([-$w / 2 + 8, $w / 2 - 8] as $dx) {
                    $page->line($cx + $dx, $y, $cx + $dx, $y - $h, $borderWidth, $color);
                }
                break;
            case 'connector':
                $radius = min($h, $w) / 2;
                $page->ellipse($cx, $y - $h / 2, $radius, $radius, $fill, $color, $borderWidth);
                break;
            default:
                $page->rect($cx - $w / 2, $y - $h, $w, $h, $fill, $color, $borderWidth);
        }

        return [$color, $textColor];
    }

    /**
     * The symbol with its text wrapped inside (at most as many lines as fit) and, optionally, the actor under it.
     *
     * @param  array<string, mixed>  $step
     */
    public static function step(PageCanvas $page, StandardFont $font, array $step, float $cx, float $y, float $w, float $h, bool $withActor = true): void
    {
        [, $textColor] = self::outline($page, $step, $cx, $y, $w, $h);
        $size = 8.5;
        $inner = $step['type'] === 'decision' ? $w * 0.62 : ($step['type'] === 'data' ? $w - $h * 0.7 : $w - 16);
        $lh = $size + 1.5;
        $maxLines = max(4, (int) floor(($h - 6) / $lh));
        $lines = TextFlow::wrap($step['text'], $font, $size, $inner);

        if (count($lines) > $maxLines) {
            $last = $lines[$maxLines - 1];
            $lines = [...array_slice($lines, 0, $maxLines - 1), mb_substr($last, 0, max(0, mb_strlen($last) - 2)).'…'];
        }

        $showActor = $withActor && ! empty($step['actor']) && $step['type'] !== 'connector';
        $ty = $y - $h / 2 + (count($lines) * $lh + ($showActor ? 9 : 0)) / 2 - $size + 1;

        foreach ($lines as $line) {
            $page->text($line, $font, $size, $cx - $font->widthOfTextAtSize($line, $size) / 2, $ty, $textColor);
            $ty -= $lh;
        }

        if ($showActor) {
            $actor = "({$step['actor']})";
            $page->text($actor, $font, 7, $cx - $font->widthOfTextAtSize($actor, 7) / 2, $ty - 1, [0.4, 0.42, 0.48]);
        }
    }

    /**
     * @param  array{x: float, y: float}  $from
     * @param  array{x: float, y: float}  $to
     * @param  array<int, float>  $color
     */
    public static function arrow(PageCanvas $page, array $from, array $to, array $color): void
    {
        $dx = $to['x'] - $from['x'];
        $dy = $to['y'] - $from['y'];
        $len = hypot($dx, $dy) ?: 1;
        [$ux, $uy, $s] = [$dx / $len, $dy / $len, 5];
        [$bx, $by] = [$to['x'] - $ux * $s, $to['y'] - $uy * $s];

        $page->path([['M', $to['x'], $to['y']], ['L', $bx - $uy * $s * 0.55, $by + $ux * $s * 0.55], ['L', $bx + $uy * $s * 0.55, $by - $ux * $s * 0.55], ['Z']], $color, null, 0);
    }

    /**
     * "#rrggbb" or "#rgb" as 0-1 floats; the fallback for anything else.
     *
     * @param  array<int, float>  $fallback
     * @return array<int, float>
     */
    public static function hex(?string $hex, array $fallback): array
    {
        if ($hex === null || $hex === '' || preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($hex), $m) !== 1) {
            return $fallback;
        }

        $h = strlen($m[1]) === 3 ? implode('', array_map(fn (string $c): string => $c.$c, str_split($m[1]))) : $m[1];

        return [hexdec(substr($h, 0, 2)) / 255, hexdec(substr($h, 2, 2)) / 255, hexdec(substr($h, 4, 2)) / 255];
    }
}
