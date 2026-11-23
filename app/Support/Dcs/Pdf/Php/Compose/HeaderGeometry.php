<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

/**
 * Where each cell and the logo of the configurable letterhead sit (port of
 * dcs/pdf-header-geometry.ts, the same geometry the editor preview uses). Units are PDF
 * points, y measured down from the top edge of the header.
 */
final class HeaderGeometry
{
    /** @return array{r: int, g: int, b: int, a: float}|null */
    public static function parseColor(?string $value): ?array
    {
        if (preg_match('/^#([0-9a-f]{6})([0-9a-f]{2})?$/i', $value ?? '', $m) !== 1) {
            return null;
        }

        $n = hexdec($m[1]);

        return ['r' => ($n >> 16) & 255, 'g' => ($n >> 8) & 255, 'b' => $n & 255, 'a' => isset($m[2]) && $m[2] !== '' ? hexdec($m[2]) / 255 : 1];
    }

    /** "light" text on a dark background, "dark" on a light or empty one. */
    public static function autoTone(?string $background): string
    {
        $bg = self::parseColor($background);

        if ($bg === null) {
            return 'dark';
        }

        $mix = fn (int $v): float => round($v * $bg['a'] + 255 * (1 - $bg['a']));
        $lin = function (float $v): float {
            $s = $v / 255;

            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        };
        $luminance = 0.2126 * $lin($mix($bg['r'])) + 0.7152 * $lin($mix($bg['g'])) + 0.0722 * $lin($mix($bg['b']));

        return $luminance < 0.18 ? 'light' : 'dark';
    }

    /** @param  array<string, mixed>  $cell */
    public static function fontSize(array $cell, float $rowHeight): float
    {
        return $cell['fontSize'] ?? min(9, max(6.5, $rowHeight * 0.42));
    }

    /** @param  array<string, mixed>  $layout */
    public static function height(array $layout): float
    {
        return array_sum(array_map(fn (array $row): float => $row['height'], $layout['rows']));
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array{height: float, cells: list<array<string, mixed>>, logo: array{x: float, y: float, w: float, h: float}|null}
     */
    public static function compute(array $layout, float $boxW): array
    {
        $logoRows = $layout['logo']['enabled'] ? min($layout['logo']['rows'], count($layout['rows'])) : 0;
        $logoW = $logoRows > 0 ? $boxW * $layout['logo']['width'] / 100 : 0;
        $logoLeft = $layout['logo']['side'] === 'left';
        $cells = [];
        $y = 0.0;
        $logoH = 0.0;

        foreach ($layout['rows'] as $rowIndex => $row) {
            $beside = $rowIndex < $logoRows;

            if ($beside) {
                $logoH += $row['height'];
            }

            $rowW = $boxW - ($beside ? $logoW : 0);
            $x = $beside && $logoLeft ? $logoW : 0.0;
            $sum = array_sum(array_map(fn (array $c): float => $c['width'], $row['cells'])) ?: 1;

            foreach ($row['cells'] as $cellIndex => $cell) {
                $w = $rowW * $cell['width'] / $sum;
                $cells[] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $row['height'], 'cell' => $cell];
                $x += $w;
            }

            $y += $row['height'];
        }

        return ['height' => $y, 'cells' => $cells, 'logo' => $logoRows > 0 ? ['x' => $logoLeft ? 0 : $boxW - $logoW, 'y' => 0, 'w' => $logoW, 'h' => $logoH] : null];
    }

    /**
     * The logo "contained" in its box: never cropped, distorted or enlarged.
     *
     * @param  array{x: float, y: float, w: float, h: float}  $box
     * @return array{x: float, y: float, w: float, h: float}
     */
    public static function fitLogo(array $box, float $padding, float $imgW, float $imgH, string $valign): array
    {
        $availW = max($box['w'] - 2 * $padding, 1);
        $availH = max($box['h'] - 2 * $padding, 1);
        $scale = min($availW / $imgW, $availH / $imgH, 1);
        [$w, $h] = [$imgW * $scale, $imgH * $scale];
        $y = $valign === 'top' ? $box['y'] + $padding : ($valign === 'bottom' ? $box['y'] + $box['h'] - $padding - $h : $box['y'] + ($box['h'] - $h) / 2);

        return ['x' => $box['x'] + ($box['w'] - $w) / 2, 'y' => $y, 'w' => $w, 'h' => $h];
    }

    /**
     * The text of a cell with its placeholders filled. Status and issue date are left empty (they are stamped when
     * the document is viewed); the text before that placeholder is what is printed.
     *
     * @param  array<string, mixed>  $cell
     * @param  array<string, string>  $values
     * @return array{text: string, reactive: string|null}
     */
    public static function resolveText(array $cell, array $values, int $pageNo, int $pageTotal): array
    {
        $reactive = null;
        $text = preg_replace_callback('/\{([A-Za-z]+)\}/', function (array $m) use (&$reactive, $values, $pageNo, $pageTotal): string {
            if ($m[1] === 'status' || $m[1] === 'issuedDate') {
                $reactive = $m[1];

                return '';
            }

            return match ($m[1]) {
                'page' => "Page {$pageNo} Of {$pageTotal}",
                'pageOf' => "{$pageNo} dari {$pageTotal}",
                default => $values[$m[1]] ?? '',
            };
        }, $cell['text']);

        return ['text' => ! empty($cell['uppercase']) ? mb_strtoupper($text) : $text, 'reactive' => $reactive];
    }
}
