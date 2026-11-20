<?php

namespace App\Support\Templates\Legacy;

/**
 * Node keeps clause text as rich HTML. The library keeps plain text (audit Q-T1):
 * paragraphs and line breaks are kept, formatting (bold, colour, tables, images)
 * is dropped, and the caller is told whether anything was removed.
 */
class NodeText
{
    /**
     * @return array{0: string, 1: bool} the plain text, and whether tags were removed
     */
    public static function plain(string $value): array
    {
        if (preg_match('/<\/?[a-zA-Z][^>]*>/', $value) !== 1) {
            return [trim($value), false];
        }

        $text = (string) preg_replace('/<\s*br\s*\/?>|<\/(p|div|li|h[1-6])\s*>/i', "\n", $value);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[ \t]+\n/', "\n", $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return [trim($text), true];
    }

    public static function hasTags(string $text): bool
    {
        return preg_match('/<\/?[a-zA-Z][^>]*>/', $text) === 1;
    }
}
