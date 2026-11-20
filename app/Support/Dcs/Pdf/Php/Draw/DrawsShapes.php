<?php

namespace App\Support\Dcs\Pdf\Php\Draw;

use App\Support\Dcs\Pdf\Php\Model\PdfSerializer;

/**
 * The shape operators of pdf-lib (drawRectangle, drawLine, drawEllipse, drawSvgPath,
 * drawImage), emitted the way pdf-lib emits them: the same defaults, the same way a
 * fill and a border combine, so a page built here paints like the Node engine's.
 * Colours are [r, g, b] in 0-1. For the shapes "fill" is pdf-lib's `color` and
 * "stroke" its `borderColor`; with neither, pdf-lib fills black.
 */
trait DrawsShapes
{
    private const KAPPA = 0.5522847498307936;

    /**
     * @param  array<int, float>|null  $fill
     * @param  array<int, float>|null  $stroke
     */
    public function rect(float $x, float $y, float $width, float $height, ?array $fill = null, ?array $stroke = null, float $borderWidth = 0, ?float $fillOpacity = null): void
    {
        [$fill, $stroke] = $fill === null && $stroke === null ? [[0.0, 0.0, 0.0], null] : [$fill, $stroke];
        $n = PdfSerializer::number(...);
        $path = "1 0 0 1 {$n($x)} {$n($y)} cm\n0 0 m\n0 {$n($height)} l\n{$n($width)} {$n($height)} l\n{$n($width)} 0 l\nh\n";

        $this->shape($fill, $stroke, $borderWidth, $path, $fillOpacity, true);
    }

    /**
     * @param  array<int, float>|null  $color
     */
    public function line(float $x1, float $y1, float $x2, float $y2, float $thickness = 1, ?array $color = [0.0, 0.0, 0.0]): void
    {
        $n = PdfSerializer::number(...);
        $this->operators .= "q\n".($color === null ? '' : $this->color($color, true)."\n")."{$n($thickness)} w\n[] 0 d\n{$n($x1)} {$n($y1)} m\n{$n($x1)} {$n($y1)} m\n{$n($x2)} {$n($y2)} l\nS\nQ\n";
    }

    /**
     * @param  array<int, float>|null  $fill
     * @param  array<int, float>|null  $stroke
     */
    public function ellipse(float $cx, float $cy, float $rx, float $ry, ?array $fill = null, ?array $stroke = null, float $borderWidth = 0): void
    {
        [$fill, $stroke] = $fill === null && $stroke === null ? [[0.0, 0.0, 0.0], null] : [$fill, $stroke];
        $n = PdfSerializer::number(...);
        [$x, $y] = [$cx - $rx, $cy - $ry];
        [$ox, $oy] = [$rx * self::KAPPA, $ry * self::KAPPA];
        [$xe, $ye, $xm, $ym] = [$x + $rx * 2, $y + $ry * 2, $x + $rx, $y + $ry];
        $curve = fn (float ...$p): string => implode(' ', array_map($n, $p))." c\n";

        $path = "{$n($x)} {$n($ym)} m\n".$curve($x, $ym - $oy, $xm - $ox, $y, $xm, $y).$curve($xm + $ox, $y, $xe, $ym - $oy, $xe, $ym)
            .$curve($xe, $ym + $oy, $xm + $ox, $ye, $xm, $ye).$curve($xm - $ox, $ye, $x, $ym + $oy, $x, $ym);

        $this->shape($fill, $stroke, $borderWidth, $path, null, false);
    }

    /**
     * A path of absolute M, L, C and Z commands, already in page coordinates.
     *
     * @param  list<array{0: string, 1?: float, 2?: float, 3?: float, 4?: float, 5?: float, 6?: float}>  $commands
     * @param  array<int, float>|null  $fill
     * @param  array<int, float>|null  $stroke
     */
    public function path(array $commands, ?array $fill = null, ?array $stroke = null, float $borderWidth = 0): void
    {
        $n = PdfSerializer::number(...);
        $path = '';

        foreach ($commands as $command) {
            $letter = array_shift($command);
            $path .= $letter === 'Z' ? "h\n" : implode(' ', array_map($n, $command)).' '.strtolower($letter)."\n";
        }

        $this->shape($fill, $stroke, $borderWidth, $path, null, false, $borderWidth !== 0.0);
    }

    public function image(string $name, float $x, float $y, float $width, float $height): void
    {
        $n = PdfSerializer::number(...);
        $this->operators .= "q\n{$n($width)} 0 0 {$n($height)} {$n($x)} {$n($y)} cm\n/{$name} Do\nQ\n";
    }

    /**
     * @param  array<int, float>|null  $fill
     * @param  array<int, float>|null  $stroke
     */
    private function shape(?array $fill, ?array $stroke, float $borderWidth, string $path, ?float $fillOpacity, bool $alwaysWidth, bool $setWidth = true): void
    {
        $n = PdfSerializer::number(...);
        $paint = $fill !== null && $borderWidth != 0 ? 'B' : ($fill !== null ? 'f' : ($stroke !== null ? 'S' : 'h'));
        $ops = "q\n";

        if ($fillOpacity !== null) {
            $ops .= '/'.$this->stateName($fillOpacity, null)." gs\n";
        }

        $ops .= ($fill === null ? '' : $this->color($fill, false)."\n").($stroke === null ? '' : $this->color($stroke, true)."\n");
        $ops .= ($setWidth || $alwaysWidth ? "{$n($borderWidth)} w\n" : '')."[] 0 d\n";

        $this->operators .= $ops.$path.$paint."\nQ\n";
    }

    /** @param array<int, float> $color */
    private function color(array $color, bool $stroke): string
    {
        return implode(' ', array_map(PdfSerializer::number(...), $color)).($stroke ? ' RG' : ' rg');
    }
}
