<?php

namespace App\Support\Dcs\Pdf\Php\Compose;

use App\Support\Dcs\Pdf\Php\Fonts\StandardFont;

/** Word wrapping and the small text conventions of the body (port of the text helpers in dcs/pdf-compose.ts). */
final class TextFlow
{
    /**
     * @return list<string>
     */
    public static function wrap(string $text, StandardFont $font, float $size, float $maxWidth): array
    {
        $lines = [];

        foreach (explode("\n", $text) as $paragraph) {
            if (self::trim($paragraph) === '') {
                $lines[] = '';

                continue;
            }

            $line = '';

            foreach (preg_split('/\s+/u', $paragraph) as $word) {
                $trial = $line !== '' ? "{$line} {$word}" : $word;

                if ($font->widthOfTextAtSize($trial, $size) > $maxWidth && $line !== '') {
                    $lines[] = $line;
                    $line = $word;
                } else {
                    $line = $trial;
                }
            }

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /** JavaScript's String.trim: the Unicode white space and the byte-order mark at both ends. */
    public static function trim(string $text): string
    {
        return preg_replace('/^[\s\x{FEFF}]+|[\s\x{FEFF}]+$/u', '', $text);
    }

    /** A numbered line ("1. ") sits at level 0, a lettered one ("a. ") at 14, a dash or bullet at 28; typed indentation adds 3 per character. */
    public static function listIndent(string $rawLine): float
    {
        $stripped = ltrim($rawLine, " \t");
        $leading = (strlen($rawLine) - strlen($stripped)) * 3;

        return match (true) {
            preg_match('/^[0-9]+\.\s/u', $stripped) === 1 => $leading,
            preg_match('/^[a-zA-Z]\.\s/u', $stripped) === 1 => $leading + 14,
            preg_match('/^[-•]\s/u', $stripped) === 1 => $leading + 28,
            default => $leading,
        };
    }

    /**
     * One level of **bold**.
     *
     * @return list<array{text: string, bold: bool}>
     */
    public static function boldRuns(string $text): array
    {
        $runs = [];
        $last = 0;

        if (preg_match_all('/\*\*(.+?)\*\*/u', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) !== false) {
            foreach ($matches as $m) {
                if ($m[0][1] > $last) {
                    $runs[] = ['text' => substr($text, $last, $m[0][1] - $last), 'bold' => false];
                }

                $runs[] = ['text' => $m[1][0], 'bold' => true];
                $last = $m[0][1] + strlen($m[0][0]);
            }
        }

        if ($last < strlen($text)) {
            $runs[] = ['text' => substr($text, $last), 'bold' => false];
        }

        return $runs === [] ? [['text' => $text, 'bold' => false]] : $runs;
    }

    /** Clips text to a number of characters, with an ellipsis, as the Node engine did (UTF-16 length). */
    public static function clip(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }
}
