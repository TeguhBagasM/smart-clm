<?php

namespace App\Support\Dcs\Pdf\Php\Fonts;

use App\Support\Dcs\Pdf\PdfEngineException;

/**
 * One of the PDF core fonts, measured and encoded the way pdf-lib does it: WinAnsi
 * only, glyph widths and kerning pairs from the Adobe AFM files. Matching pdf-lib
 * exactly (kerning included) is what makes text break and centre like the Node
 * engine did.
 */
final class StandardFont
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $data = null;

    /** @var array<string, self> */
    private static array $loaded = [];

    /**
     * @param  array<int, int|float>  $widths
     * @param  array<string, int|float>  $kern
     * @param  array<int, int>  $unicode
     */
    private function __construct(
        public readonly string $baseFont,
        private readonly int|float $ascender,
        private readonly int|float $descender,
        private readonly array $widths,
        private readonly array $kern,
        private readonly array $unicode,
    ) {}

    public static function named(string $baseFont): self
    {
        self::$data ??= require __DIR__.'/standard_fonts.php';

        if (! isset(self::$data[$baseFont])) {
            throw new PdfEngineException("The PHP PDF engine has no metrics for the font {$baseFont}.");
        }

        return self::$loaded[$baseFont] ??= new self(
            $baseFont,
            self::$data[$baseFont]['ascender'],
            self::$data[$baseFont]['descender'],
            self::$data[$baseFont]['widths'],
            self::$data[$baseFont]['kern'],
            self::$data[$baseFont]['unicode'],
        );
    }

    /**
     * Width of a one-line text, kerning included, as pdf-lib's widthOfTextAtSize.
     *
     * @throws PdfEngineException when a character is outside WinAnsi
     */
    public function widthOfTextAtSize(string $text, float $size): float
    {
        $total = 0;
        $previous = null;

        foreach ($this->codes($text) as $code) {
            $total += $this->widths[$code] + ($previous === null ? 0 : ($this->kern[$previous.','.$code] ?? 0));
            $previous = $code;
        }

        return $total * ($size / 1000);
    }

    /** Height above the baseline (no descender), as pdf-lib's heightAtSize. */
    public function ascentAtSize(float $size): float
    {
        return $this->ascender / 1000 * $size;
    }

    /** The text as the hex string of its WinAnsi bytes, ready for a `Tj`. */
    public function encodeHex(string $text): string
    {
        return strtoupper(bin2hex(pack('C*', ...$this->codes($text))));
    }

    /**
     * @return list<int>
     *
     * @throws PdfEngineException
     */
    private function codes(string $text): array
    {
        $codes = [];

        foreach (mb_str_split($text, 1, 'UTF-8') as $char) {
            $codePoint = mb_ord($char, 'UTF-8');

            if (! isset($this->unicode[$codePoint])) {
                throw new PdfEngineException(sprintf('WinAnsi cannot encode "%s" (0x%04x)', $char, $codePoint));
            }

            $codes[] = $this->unicode[$codePoint];
        }

        return $codes;
    }
}
