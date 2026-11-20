<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Compares two PDFs the way a reader sees them: page count, extracted text and the
 * rendered pixels of every page (poppler's pdftoppm). Used to hold the PHP PDF engine
 * to the golden files the Node engine produced.
 */
final class PdfParity
{
    public static function available(): bool
    {
        foreach (['pdftoppm', 'pdftotext', 'pdfinfo'] as $tool) {
            if (trim((string) shell_exec('command -v '.$tool)) === '') {
                return false;
            }
        }

        return true;
    }

    public static function pages(string $pdf): int
    {
        preg_match('/Pages:\s+(\d+)/', self::run('pdfinfo', $pdf), $match);

        return (int) ($match[1] ?? 0);
    }

    public static function text(string $pdf): string
    {
        return trim(self::run('pdftotext -layout', $pdf, '-'));
    }

    /**
     * @return array{pages: int, differingPixels: int, maxChannelDifference: int, worstPage: int|null}
     */
    public static function compare(string $expected, string $actual, int $dpi = 100): array
    {
        $a = self::render($expected, $dpi);
        $b = self::render($actual, $dpi);
        $result = ['pages' => count($a), 'differingPixels' => 0, 'maxChannelDifference' => 0, 'worstPage' => null];

        if (count($a) !== count($b)) {
            return ['differingPixels' => PHP_INT_MAX, 'maxChannelDifference' => 255] + $result;
        }

        $worst = 0;

        foreach ($a as $page => $left) {
            [$count, $max] = self::difference($left, $b[$page]);
            $result['differingPixels'] += $count;
            $result['maxChannelDifference'] = max($result['maxChannelDifference'], $max);

            if ($count > $worst) {
                [$worst, $result['worstPage']] = [$count, $page + 1];
            }
        }

        return $result;
    }

    /** @return list<\GdImage> */
    private static function render(string $pdf, int $dpi): array
    {
        $dir = sys_get_temp_dir().'/pdfparity-'.bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents("$dir/in.pdf", $pdf);
        shell_exec("pdftoppm -r {$dpi} -png ".escapeshellarg("$dir/in.pdf").' '.escapeshellarg("$dir/page").' 2>/dev/null');

        $images = array_map(fn (string $file) => imagecreatefrompng($file) ?: throw new RuntimeException("Cannot read $file"), glob("$dir/page-*.png") ?: []);
        array_map('unlink', glob("$dir/*") ?: []);
        rmdir($dir);

        return $images;
    }

    /** @return array{0: int, 1: int} */
    private static function difference(\GdImage $a, \GdImage $b): array
    {
        if (imagesx($a) !== imagesx($b) || imagesy($a) !== imagesy($b)) {
            return [PHP_INT_MAX, 255];
        }

        [$count, $max] = [0, 0];

        for ($y = 0, $h = imagesy($a); $y < $h; $y++) {
            for ($x = 0, $w = imagesx($a); $x < $w; $x++) {
                if (($p = imagecolorat($a, $x, $y)) === ($q = imagecolorat($b, $x, $y))) {
                    continue;
                }

                $count++;
                $max = max($max, abs(($p >> 16 & 255) - ($q >> 16 & 255)), abs(($p >> 8 & 255) - ($q >> 8 & 255)), abs(($p & 255) - ($q & 255)));
            }
        }

        return [$count, $max];
    }

    private static function run(string $command, string $pdf, string $output = ''): string
    {
        $file = tempnam(sys_get_temp_dir(), 'pdfparity');
        file_put_contents($file, $pdf);
        $text = (string) shell_exec("{$command} ".escapeshellarg($file)." {$output} 2>/dev/null");
        unlink($file);

        return $text;
    }
}
