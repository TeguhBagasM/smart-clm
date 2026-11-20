<?php

namespace App\Support\Templates;

/**
 * Puts values into a text. Values are data: they are never parsed again, so a
 * value that looks like a placeholder or HTML stays exactly as typed. The caller
 * decides how to show the result (the web client escapes text; nothing here
 * builds HTML). Only valid codes are replaced; anything else stays literal.
 */
class PlaceholderRenderer
{
    private const VALID = '/\{\{('.Placeholders::CODE.')\}\}/';

    /**
     * The text cut into parts: plain text, and fields with their value. A field
     * without a value is shown as [Code] and flagged missing.
     *
     * @param  array<string, string>  $values
     * @return list<array{text: string}|array{code: string, value: string, missing: bool}>
     */
    public static function segments(string $text, array $values): array
    {
        $parts = preg_split('/(\{\{'.Placeholders::CODE.'\}\})/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $segments = [];

        foreach ($parts as $part) {
            if (preg_match(self::VALID, $part, $match) === 1) {
                $value = $values[$match[1]] ?? null;
                $segments[] = ['code' => $match[1], 'value' => $value ?? '['.$match[1].']', 'missing' => $value === null];
            } else {
                $segments[] = ['text' => $part];
            }
        }

        return $segments;
    }

    /**
     * The text with its values, in one pass: a value is never scanned for placeholders.
     *
     * @param  array<string, string>  $values
     */
    public static function plain(string $text, array $values): string
    {
        return preg_replace_callback(self::VALID, fn (array $match): string => $values[$match[1]] ?? '['.$match[1].']', $text) ?? $text;
    }
}
