<?php

namespace App\Support\Ai;

/**
 * Turns a model's reply into an array. JSON mode makes valid JSON likely, not certain: replies
 * arrive fenced in ```json, followed by a closing sentence, or with raw line breaks inside a
 * string. Ported from the legacy `parseAiJson`.
 */
final class JsonRepair
{
    /**
     * @return array<mixed>|null null when nothing usable can be recovered
     */
    public static function decode(string $text): ?array
    {
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text)) ?? $text);

        $slice = self::firstValue($text);

        if ($slice === null) {
            return null;
        }

        foreach ([$slice, self::escapeRawControlCharacters($slice)] as $candidate) {
            $decoded = json_decode($candidate, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private static function firstValue(string $text): ?string
    {
        if (! preg_match('/[{\[]/', $text, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = $match[0][1];
        $open = $text[$start];
        $close = $open === '{' ? '}' : ']';
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = $start, $length = strlen($text); $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === $open) {
                $depth++;
            } elseif ($char === $close && --$depth === 0) {
                return substr($text, $start, $i - $start + 1);
            }
        }

        return null;
    }

    private static function escapeRawControlCharacters(string $json): string
    {
        $out = '';
        $inString = false;
        $escaped = false;

        for ($i = 0, $length = strlen($json); $i < $length; $i++) {
            $char = $json[$i];

            if ($inString && ! $escaped && ord($char) < 0x20) {
                $out .= match ($char) {
                    "\n" => '\n',
                    "\r" => '\r',
                    "\t" => '\t',
                    default => '',
                };

                continue;
            }

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
            } elseif ($char === '"') {
                $inString = true;
            }

            $out .= $char;
        }

        return $out;
    }
}
