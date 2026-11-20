<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

use App\Support\Dcs\Pdf\Php\Draw\EmbeddedImage;
use App\Support\Dcs\Pdf\Php\Draw\PageCanvas;
use App\Support\Dcs\Pdf\Php\Fonts\StandardFont;

/**
 * The letterheads: the configurable kop, the fixed ISO box, the memo box, and the
 * "Page X Of Y" footer (port of dcs/pdf-header.ts and the header functions of
 * dcs/pdf-compose.ts). Header and footer are drawn after every page exists, so the
 * page total is known.
 */
final class HeaderPainter
{
    private const BORDER = [0.25, 0.27, 0.32];

    private const TEXT = [0.2, 0.22, 0.27];

    public function __construct(private Page $doc) {}

    /**
     * @param  array<string, StandardFont>  $fonts  regular, bold, italic, boldItalic
     * @param  array{layout: array<string, mixed>, values: array<string, string>}  $header
     * @return array<string, array{x: float, y: float, size: float}>
     */
    public function configured(PageCanvas $page, array $fonts, array $header, float $boxLeft, float $boxTop, float $boxW, int $pageNo, int $pageTotal, ?EmbeddedImage $logo): array
    {
        $layout = $header['layout'];
        $border = $this->rgb(HeaderGeometry::parseColor($layout['border']) ?? ['r' => 64, 'g' => 69, 'b' => 82]);
        $geo = HeaderGeometry::compute($layout, $boxW);
        $reactive = [];

        foreach ($geo['cells'] as $box) {
            $cell = $box['cell'];
            [$w, $h] = [$box['w'], $box['h']];
            [$x, $top] = [$boxLeft + $box['x'], $boxTop - $box['y']];
            $bg = HeaderGeometry::parseColor($cell['background'] ?? null);
            $page->rect($x, $top - $h, $w, $h, $bg === null ? null : $this->rgb($bg), $border, 0.5, $bg['a'] ?? null);

            $font = ! empty($cell['bold']) ? (! empty($cell['italic']) ? $fonts['boldItalic'] : $fonts['bold']) : (! empty($cell['italic']) ? $fonts['italic'] : $fonts['regular']);
            $size = HeaderGeometry::fontSize($cell, $h);
            $fixed = $cell['color'] === 'auto' ? null : HeaderGeometry::parseColor($cell['color']);
            $color = $this->rgb($fixed ?? ($cell['color'] === 'auto' ? (HeaderGeometry::autoTone($cell['background'] ?? null) === 'light' ? ['r' => 255, 'g' => 255, 'b' => 255] : ['r' => 31, 'g' => 33, 'b' => 43]) : ['r' => 31, 'g' => 33, 'b' => 43]));
            $resolved = HeaderGeometry::resolveText($cell, $header['values'], $pageNo, $pageTotal);
            $baseline = $top - $h / 2 - $size * 0.35;
            $text = $this->fit($this->safe($resolved['text']), $font, $size, $w - 10);
            $tw = $font->widthOfTextAtSize($text, $size);

            if ($resolved['reactive'] !== null) {
                $page->text($text, $font, $size, $x + 5, $baseline, $color);
                $key = $resolved['reactive'] === 'status' ? 'status' : 'issued';
                $reactive[$key] ??= ['x' => $x + 5 + $tw, 'y' => $baseline, 'size' => $size];

                continue;
            }

            $tx = ($cell['align'] ?? 'left') === 'center' ? $x + ($w - $tw) / 2 : (($cell['align'] ?? 'left') === 'right' ? $x + $w - 5 - $tw : $x + 5);
            $page->text($text, $font, $size, $tx, $baseline, $color);
        }

        if ($geo['logo'] !== null) {
            $lb = $geo['logo'];
            $page->rect($boxLeft + $lb['x'], $boxTop - $lb['y'] - $lb['h'], $lb['w'], $lb['h'], null, $border, 0.5);

            if ($logo !== null) {
                $fitted = HeaderGeometry::fitLogo($lb, $layout['logo']['padding'], $logo->width, $logo->height, $layout['logo']['valign']);
                $page->drawImage($logo, $boxLeft + $fitted['x'], $boxTop - $fitted['y'] - $fitted['h'], $fitted['w'], $fitted['h']);
            }
        }

        $page->rect($boxLeft, $boxTop - $geo['height'], $boxW, $geo['height'], null, $border, 1);

        return $reactive;
    }

    /**
     * The fixed ISO box (used when a type has no configured kop): the status and issue date are left blank for the viewer stamp.
     *
     * @param  array<string, string>  $meta
     * @return array{status: array{x: float, y: float, size: float}, issued: array{x: float, y: float, size: float}}
     */
    public function isoBox(PageCanvas $page, array $meta, int $pageNo, int $pageTotal, ?EmbeddedImage $logo): array
    {
        $l = $this->doc->layout;
        [$bold, $regular] = [$this->doc->bold, $this->doc->regular];
        $boxTop = Layout::PAGE_H - $l->mTop;
        $boxLeft = $l->mLeft;
        $boxRight = Layout::PAGE_W - $l->mRight;
        $boxW = $boxRight - $boxLeft;
        $line = fn (float $x1, float $y1, float $x2, float $y2, float $th = 0.75) => $page->line($x1, $y1, $x2, $y2, $th, self::BORDER);

        $page->rect($boxLeft, $boxTop - Layout::HEADER_BOX_H, $boxW, Layout::HEADER_BOX_H, null, self::BORDER, 1);
        [$logoColW, $titleBandH] = [78, 34];
        $titleX = $boxLeft + $logoColW;
        $line($titleX, $boxTop, $titleX, $boxTop - $titleBandH);

        if ($logo !== null) {
            $scale = min(($logoColW - 16) / $logo->width, ($titleBandH - 8) / $logo->height, 1);
            [$w, $h] = [$logo->width * $scale, $logo->height * $scale];
            $page->drawImage($logo, $boxLeft + ($logoColW - $w) / 2, $boxTop - $titleBandH / 2 - $h / 2, $w, $h);
        }

        $bandCx = $titleX + ($boxRight - $titleX) / 2;
        $tn = mb_strtoupper($meta['docTypeName']);
        $page->text($tn, $bold, 9, $bandCx - $bold->widthOfTextAtSize($tn, 9) / 2, $boxTop - 13, [0.09, 0.1, 0.14]);
        $title = TextFlow::clip($meta['title'], 64);
        $page->text($title, $bold, 8, $bandCx - $bold->widthOfTextAtSize($title, 8) / 2, $boxTop - 25, self::TEXT);
        $line($boxLeft, $boxTop - $titleBandH, $boxRight, $boxTop - $titleBandH);

        $gridTop = $boxTop - $titleBandH;
        $rowH = (Layout::HEADER_BOX_H - $titleBandH) / 4;
        $midX = $boxLeft + $boxW / 2;
        $line($midX, $gridTop, $midX, $boxTop - Layout::HEADER_BOX_H);
        $cell = function (int $col, int $row, string $label, string $value) use ($page, $regular, $boxLeft, $boxRight, $midX, $gridTop, $rowH, $line): void {
            $yTop = $gridTop - $row * $rowH;

            if ($row > 0) {
                $line($boxLeft, $yTop, $boxRight, $yTop, 0.5);
            }

            $page->text(TextFlow::clip("{$label}: {$value}", 46), $regular, 7, ($col === 0 ? $boxLeft : $midX) + 5, $yTop - $rowH / 2 - 3, self::TEXT);
        };
        $reactive = function (int $col, int $row, string $label) use ($page, $regular, $boxLeft, $boxRight, $midX, $gridTop, $rowH, $line): array {
            $cx0 = $col === 0 ? $boxLeft : $midX;
            $yTop = $gridTop - $row * $rowH;

            if ($row > 0) {
                $line($boxLeft, $yTop, $boxRight, $yTop, 0.5);
            }

            $y = $yTop - $rowH / 2 - 3;
            $page->text("{$label}: ", $regular, 7, $cx0 + 5, $y, self::TEXT);

            return ['x' => $cx0 + 5 + $regular->widthOfTextAtSize("{$label}: ", 7), 'y' => $y, 'size' => 7];
        };

        $cell(0, 0, 'Nomor Dokumen', $meta['documentNumber']);
        $issued = $reactive(1, 0, 'Tanggal Diterbitkan');
        $cell(0, 1, 'Nomor Revisi/Tahun', $meta['revisionLabel']);
        $cell(1, 1, 'Nomor Halaman', "Page {$pageNo} Of {$pageTotal}");
        $cell(0, 2, 'Bahasa', $meta['language']);
        $cell(1, 2, 'Tingkat Dokumen', $meta['documentLevel']);
        $cell(0, 3, 'Pemilik Proses', $meta['processOwner']);
        $status = $reactive(1, 3, 'Status Dokumen');

        return ['status' => $status, 'issued' => $issued];
    }

    /**
     * The framed memo header: the company on the left, a compact info block on the right. Returns the y under the box.
     *
     * @param  array<string, mixed>|null  $letterhead
     * @param  array{documentNumber: string, minor: int, major: int, issuedDate: string}  $meta
     */
    public function memoBox(PageCanvas $page, ?array $letterhead, array $meta, ?EmbeddedImage $logo): float
    {
        $l = $this->doc->layout;
        [$bold, $regular] = [$this->doc->bold, $this->doc->regular];
        $boxTop = Layout::PAGE_H - $l->mTop;
        $boxH = 92;
        [$boxLeft, $boxRight] = [$l->mLeft, Layout::PAGE_W - $l->mRight];
        $boxW = $boxRight - $boxLeft;
        $page->rect($boxLeft, $boxTop - $boxH, $boxW, $boxH, null, self::BORDER, 1);
        $infoColW = 180;
        $page->line($boxRight - $infoColW, $boxTop, $boxRight - $infoColW, $boxTop - $boxH, 0.75, self::BORDER);

        $brandX = $boxLeft + 10;
        $brandColW = $boxW - $infoColW - 20;
        $logoW = 0.0;

        if ($logo !== null) {
            $scale = min(50 / $logo->width, 50 / $logo->height, 1);
            [$w, $h] = [$logo->width * $scale, $logo->height * $scale];
            $page->drawImage($logo, $brandX, $boxTop - $boxH / 2 - $h / 2, $w, $h);
            $logoW = $w + 10;
        }

        $textX = $brandX + $logoW;

        if (! empty($letterhead['companyName'])) {
            $page->text($letterhead['companyName'], $bold, 11, $textX, $boxTop - 26, [0.09, 0.1, 0.14]);
        }

        if (! empty($letterhead['companyAddress'])) {
            $ly = $boxTop - 40;

            foreach (array_slice(TextFlow::wrap($letterhead['companyAddress'], $regular, 7.5, max($brandColW - $logoW, 60)), 0, 3) as $text) {
                $page->text($text, $regular, 7.5, $textX, $ly, [0.4, 0.42, 0.48]);
                $ly -= 10;
            }
        }

        $infoX = $boxRight - $infoColW + 8;
        $ry = $boxTop - 16;

        foreach ([['Nomor Dokumen', $meta['documentNumber']], ['Revisi ke', (string) $meta['minor']], ['Terbitan ke', (string) $meta['major']], ['Tanggal Efektif', $meta['issuedDate']]] as [$label, $value]) {
            $page->text("{$label}:", $bold, 7.5, $infoX, $ry, [0.3, 0.32, 0.38]);
            $clipped = mb_strlen($value) > 28 ? mb_substr($value, 0, 27).'…' : $value;
            $page->text($clipped, $regular, 7.5, $infoX + min($bold->widthOfTextAtSize("{$label}: ", 7.5), $infoColW - 16 - 20), $ry, [0.16, 0.16, 0.2]);
            $ry -= 15;
        }

        return $boxTop - $boxH - 20;
    }

    public function footer(PageCanvas $page, int $pageNo, int $pageTotal): void
    {
        $text = "Page {$pageNo} Of {$pageTotal}";
        $width = $this->doc->regular->widthOfTextAtSize($text, 8);
        $bottom = $this->doc->layout->mBottom;
        $page->text($text, $this->doc->regular, 8, Layout::PAGE_W / 2 - $width / 2, $bottom - 16 > 8 ? $bottom - 16 : 8, [0.45, 0.47, 0.52]);
    }

    /** @param  array{r: int, g: int, b: int}  $c */
    private function rgb(array $c): array
    {
        return [$c['r'] / 255, $c['g'] / 255, $c['b'] / 255];
    }

    /** The standard fonts know only WinAnsi: anything else prints as "?". */
    private function safe(string $text): string
    {
        return preg_replace('/[^\x{20}-\x{7E}\x{A0}-\x{FF}\x{2013}\x{2014}\x{2018}\x{2019}\x{201C}\x{201D}\x{2026}]/u', '?', $text);
    }

    private function fit(string $text, StandardFont $font, float $size, float $maxWidth): string
    {
        if ($font->widthOfTextAtSize($text, $size) <= $maxWidth) {
            return $text;
        }

        $t = $text;

        while (mb_strlen($t) > 1 && $font->widthOfTextAtSize($t.'…', $size) > $maxWidth) {
            $t = mb_substr($t, 0, -1);
        }

        return $t.'…';
    }
}
