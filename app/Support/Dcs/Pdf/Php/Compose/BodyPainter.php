<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

use App\Support\Dcs\Pdf\Php\Fonts\StandardFont;

/** Headings, paragraphs and tables of the body (port of drawHeading, drawFormalHeading, drawParagraph and drawTable). */
final class BodyPainter
{
    private const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII', 'XIII', 'XIV', 'XV', 'XVI', 'XVII', 'XVIII', 'XIX', 'XX'];

    public const FORMAL_OFFSET = 44;

    public function __construct(private Page $doc) {}

    public function heading(Cursor $cur, string $text, StandardFont $font, float $size = 12): void
    {
        $l = $this->doc->layout;
        $this->doc->ensureSpace($cur, $size * 2.2);
        $cur->y -= $size * 0.6;
        $cur->page->text($text, $font, $size, $l->mLeft, $cur->y - $size, [0.16, 0.32, 0.75]);
        $cur->page->line($l->mLeft, $cur->y - $size - 4, Layout::PAGE_W - $l->mRight, $cur->y - $size - 4, 0.75, [0.7, 0.72, 0.78]);
        $cur->y -= $size + 12;
    }

    public function formalHeading(Cursor $cur, int $index, string $text, StandardFont $font): void
    {
        $size = 11;
        $this->doc->ensureSpace($cur, $size * 3);
        $cur->y -= 4;
        $y = $cur->y - $size;
        $cur->page->text((self::ROMAN[$index] ?? $index + 1).'.', $font, $size, $this->doc->layout->mLeft + 10, $y, [0, 0, 0]);
        $cur->page->text(mb_strtoupper($text), $font, $size, $this->doc->layout->mLeft + self::FORMAL_OFFSET, $y, [0, 0, 0]);
        $cur->y = $y - 8;
    }

    /**
     * @param  array{lineFactor?: float, justify?: bool, offset?: float}  $opts
     * @param  array<int, float>  $color
     */
    public function paragraph(Cursor $cur, string $text, StandardFont $regular, StandardFont $bold, float $size, array $color, array $opts = []): void
    {
        $lineHeight = $size * ($opts['lineFactor'] ?? 1.45);
        $offset = $opts['offset'] ?? 0;
        $justify = $opts['justify'] ?? false;

        foreach (explode("\n", $text) as $rawLine) {
            if (TextFlow::trim($rawLine) === '') {
                $this->doc->ensureSpace($cur, $lineHeight);
                $cur->y -= $lineHeight;

                continue;
            }

            $indent = TextFlow::listIndent($rawLine);
            $tokens = [];

            foreach (TextFlow::boldRuns(ltrim($rawLine, " \t")) as $run) {
                foreach (preg_split('/\s+/u', $run['text'], -1, PREG_SPLIT_NO_EMPTY) as $word) {
                    $tokens[] = ['word' => $word, 'bold' => $run['bold']];
                }
            }

            $maxWidth = max($this->doc->layout->contentW - $offset - $indent, 40);
            $line = [];
            $lineWidth = 0.0;
            $flush = function (bool $last) use (&$line, &$lineWidth, $cur, $lineHeight, $regular, $bold, $size, $color, $offset, $indent, $maxWidth, $justify): void {
                if ($line === []) {
                    return;
                }

                $this->doc->ensureSpace($cur, $lineHeight);
                $x = $this->doc->layout->mLeft + $offset + $indent;
                $natural = array_sum(array_map(fn (array $t): float => ($t['bold'] ? $bold : $regular)->widthOfTextAtSize($t['word'], $size), $line));
                $gaps = count($line) - 1;
                $gap = $justify && ! $last && $gaps > 0 ? ($maxWidth - $natural) / $gaps : $regular->widthOfTextAtSize(' ', $size);

                foreach ($line as $t) {
                    $font = $t['bold'] ? $bold : $regular;
                    $cur->page->text($t['word'], $font, $size, $x, $cur->y - $lineHeight, $color);
                    $x += $font->widthOfTextAtSize($t['word'], $size) + $gap;
                }

                $cur->y -= $lineHeight;
                $line = [];
                $lineWidth = 0.0;
            };

            foreach ($tokens as $t) {
                $w = ($t['bold'] ? $bold : $regular)->widthOfTextAtSize($t['word'].' ', $size);

                if ($lineWidth + $w > $maxWidth && $line !== []) {
                    $flush(false);
                }

                $line[] = $t;
                $lineWidth += $w;
            }

            $flush(true);
        }
    }

    /**
     * @param  list<string>  $columns
     * @param  list<list<string>>  $rows
     */
    public function table(Cursor $cur, array $columns, array $rows, StandardFont $bold, StandardFont $regular): void
    {
        if ($columns === []) {
            return;
        }

        $l = $this->doc->layout;
        $colW = $l->contentW / count($columns);
        $pad = 4;
        $textW = max($colW - $pad * 2, 8);
        [$headSize, $cellSize, $lineH, $vpad, $minRow, $maxLines] = [8.5, 8, 11, 6, 20, 8];

        $fit = function (string $text, StandardFont $font, float $size) use ($textW, $maxLines): array {
            $lines = TextFlow::wrap($text, $font, $size, $textW);

            return $lines === [] ? [''] : (count($lines) > $maxLines ? [...array_slice($lines, 0, $maxLines - 1), '…'] : $lines);
        };
        $height = fn (array $per): float => max($minRow, max(array_map('count', $per)) * $lineH + $vpad * 2);
        $headerRow = function () use ($cur, $columns, $fit, $height, $bold, $headSize, $colW, $pad, $vpad, $lineH, $l): void {
            $per = array_map(fn (string $c): array => $fit($c, $bold, $headSize), $columns);
            $h = $height($per);
            $x = $l->mLeft;

            foreach ($columns as $i => $_) {
                $cur->page->rect($x, $cur->y - $h, $colW, $h, [0.94, 0.95, 0.98], [0.7, 0.72, 0.78], 0.75);

                foreach ($per[$i] as $li => $line) {
                    $cur->page->text($line, $bold, $headSize, $x + $pad, $cur->y - $vpad - $headSize - $li * $lineH, [0.16, 0.16, 0.2]);
                }

                $x += $colW;
            }

            $cur->y -= $h;
        };

        if ($cur->y - $minRow * 2 < $l->mBottom) {
            $cur->page = $this->doc->addPage();
            $cur->y = $l->contentTopY;
        }

        $headerRow();

        foreach ($rows as $cells) {
            $per = array_map(fn (string $c, int $i): array => $fit($cells[$i] ?? '', $regular, $cellSize), $columns, array_keys($columns));
            $h = $height($per);

            if ($cur->y - $h < $l->mBottom) {
                $cur->page = $this->doc->addPage();
                $cur->y = $l->contentTopY;
                $headerRow();
            }

            $x = $l->mLeft;

            foreach ($columns as $i => $_) {
                $cur->page->rect($x, $cur->y - $h, $colW, $h, null, [0.85, 0.86, 0.9], 0.5);

                foreach ($per[$i] as $li => $line) {
                    $cur->page->text($line, $regular, $cellSize, $x + $pad, $cur->y - $vpad - $cellSize - $li * $lineH, [0.2, 0.22, 0.27]);
                }

                $x += $colW;
            }

            $cur->y -= $h;
        }

        $cur->y -= 10;
    }
}
