<?php

namespace Tests\Support;

/**
 * Builds a small real PDF whose pages carry a text layer (a page given as "" has none, like a scan).
 */
class TextPdf
{
    /**
     * @param  list<string>  $pages  lines of each page separated by "\n"
     */
    public static function make(array $pages): string
    {
        $objects = [1 => '<</Type/Catalog/Pages 2 0 R>>', 3 => '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>'];
        $kids = [];
        $next = 4;

        foreach ($pages as $text) {
            $page = $next++;
            $content = $next++;
            $kids[] = "{$page} 0 R";

            $stream = '';
            foreach (array_filter(explode("\n", $text), fn ($line) => $line !== '') as $i => $line) {
                $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
                $stream .= 'BT /F1 11 Tf 40 '.(780 - $i * 16)." Td ({$escaped}) Tj ET\n";
            }

            $objects[$page] = "<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Resources<</Font<</F1 3 0 R>>>>/Contents {$content} 0 R>>";
            $objects[$content] = '<</Length '.strlen($stream).">>\nstream\n{$stream}endstream";
        }

        $objects[2] = '<</Type/Pages/Kids['.implode(' ', $kids).']/Count '.count($pages).'>>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref\n0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        $pdf = str_replace('xref\n', "xref\n", $pdf);

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer<</Size '.(count($objects) + 1)."/Root 1 0 R>>\nstartxref\n{$xref}\n%%EOF";
    }
}
