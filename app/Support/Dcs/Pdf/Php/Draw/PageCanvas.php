<?php

namespace App\Support\Dcs\Pdf\Php\Draw;

use App\Support\Dcs\Pdf\PdfEngineException;
use App\Support\Dcs\Pdf\Php\Fonts\StandardFont;
use App\Support\Dcs\Pdf\Php\Model\Dict;
use App\Support\Dcs\Pdf\Php\Model\Name;
use App\Support\Dcs\Pdf\Php\Model\PdfFile;
use App\Support\Dcs\Pdf\Php\Model\PdfSerializer;
use App\Support\Dcs\Pdf\Php\Model\Ref;

/**
 * Collects what is drawn on one page of an existing PDF and appends it after the
 * page's own content. drawText follows pdf-lib's drawText: the same operators, the
 * same default line height (24, which is why a wrapped second line falls 24 points
 * below the first), the same word-wrapping when a maxWidth is given.
 */
final class PageCanvas
{
    use DrawsShapes;

    private string $operators = '';

    /** @var array<string, array<string, mixed>> */
    private array $resources = [];

    /** @var array<string, array{0: float|null, 1: float|null}> */
    private array $states = [];

    public function __construct(private PdfFile $file, private FontPool $pool, private Ref $page, public readonly float $width, public readonly float $height) {}

    /**
     * @param  array{0: float, 1: float, 2: float}  $color  red, green, blue (0-1)
     *
     * @throws PdfEngineException when the text has a character outside WinAnsi
     */
    public function text(string $text, StandardFont $font, float $size, float $x, float $y, array $color, ?float $opacity = null, float $rotateDegrees = 0, ?float $maxWidth = null, float $lineHeight = 24): void
    {
        $lines = $maxWidth === null ? self::lines(self::clean($text)) : self::wrap($text, $maxWidth, fn (string $t): float => $font->widthOfTextAtSize($t, $size));
        $fontName = $this->fontName($font);
        $radians = $rotateDegrees * M_PI / 180;
        $n = PdfSerializer::number(...);

        $ops = ["q\n"];

        if ($opacity !== null) {
            $ops[] = '/'.$this->stateName($opacity, $opacity)." gs\n";
        }

        $ops[] = "BT\n{$n($color[0])} {$n($color[1])} {$n($color[2])} rg\n/{$fontName} {$n($size)} Tf\n{$n($lineHeight)} TL\n";
        $ops[] = $n(cos($radians)).' '.$n(sin($radians)).' '.$n(-sin($radians)).' '.$n(cos($radians))." {$n($x)} {$n($y)} Tm\n";

        foreach ($lines as $line) {
            $ops[] = '<'.$font->encodeHex($line)."> Tj\nT*\n";
        }

        $this->operators .= implode('', $ops)."ET\nQ\n";
    }

    public function drawImage(EmbeddedImage $image, float $x, float $y, float $width, float $height): void
    {
        $this->resources['XObject'][$image->name] = $image->ref;
        $this->image($image->name, $x, $y, $width, $height);
    }

    public function flush(): void
    {
        if ($this->operators === '') {
            return;
        }

        foreach ($this->states as $name => [$fill, $stroke]) {
            $this->resources['ExtGState'][$name] = new Dict(['Type' => new Name('ExtGState')] + ($fill === null ? [] : ['ca' => $fill]) + ($stroke === null ? [] : ['CA' => $stroke]));
        }

        $this->file->overlay($this->page, $this->operators, $this->resources);
        $this->operators = '';
        $this->states = [];
        $this->resources = [];
    }

    private function fontName(StandardFont $font): string
    {
        $name = $this->pool->name($font);
        $this->resources['Font'][$name] = $this->pool->ref($font);

        return $name;
    }

    /** The name of an ExtGState with the given fill (ca) and stroke (CA) opacity. */
    private function stateName(?float $fill, ?float $stroke): string
    {
        foreach ($this->states as $name => $value) {
            if ($value === [$fill, $stroke]) {
                return $name;
            }
        }

        $name = $this->file->uniqueName('DcsGS');
        $this->states[$name] = [$fill, $stroke];

        return $name;
    }

    /** pdf-lib's cleanText. */
    private static function clean(string $text): string
    {
        return preg_replace('/[\x08\x0B]/u', '', preg_replace('/\t|\x{0085}|\x{2028}|\x{2029}/u', '    ', $text));
    }

    /** @return list<string> */
    private static function lines(string $text): array
    {
        return preg_split('/[\n\f\r\x{000B}]/u', $text);
    }

    /**
     * pdf-lib's breakTextIntoLines with a space as the only word break: words keep
     * their trailing space, a line breaks before the word that would not fit.
     *
     * @param  callable(string): float  $width
     * @return list<string>
     */
    private static function wrap(string $text, float $maxWidth, callable $width): array
    {
        preg_match_all('/(\n|\f|\r|\x{000B})|((.*?)( |$))/mu', self::clean($text), $matches);

        $lines = [];
        $line = '';
        $lineWidth = 0.0;
        $push = function () use (&$lines, &$line, &$lineWidth): void {
            if ($line !== '') {
                $lines[] = $line;
            }

            [$line, $lineWidth] = ['', 0.0];
        };

        foreach ($matches[0] as $word) {
            if (preg_match('/^[\n\f\r\x{000B}]$/u', $word) === 1) {
                $push();

                continue;
            }

            $w = $width($word);

            if ($lineWidth + $w > $maxWidth) {
                $push();
            }

            $line .= $word;
            $lineWidth += $w;
        }

        $push();

        return $lines;
    }
}
