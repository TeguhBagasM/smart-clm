<?php

namespace App\Support\Templates;

/**
 * Reads the placeholders of a clause or paragraph: {{Code}}, where Code is one
 * word of letters, digits and underscore, starting with a letter (audit A2.5).
 * Nothing is evaluated: no expressions, filters, or calls, so a placeholder can
 * only ever name a field. Pure: no database, no configuration.
 */
class Placeholders
{
    public const CODE = '[A-Za-z][A-Za-z0-9_]{0,59}';

    private const TOKEN = '/\{\{(.*?)\}\}/s';

    /**
     * The valid codes used in a text, once each, in order of first use.
     *
     * @return list<string>
     */
    public static function codes(string $text): array
    {
        preg_match_all(self::TOKEN, $text, $matches);
        $codes = [];

        foreach ($matches[1] as $inner) {
            if (self::isCode($inner) && ! in_array($inner, $codes, true)) {
                $codes[] = $inner;
            }
        }

        return $codes;
    }

    /**
     * Brace groups that are not a valid code, such as {{ Nama }}, {{a|upper}} or {{}}.
     *
     * @return list<string>
     */
    public static function invalidTokens(string $text): array
    {
        preg_match_all(self::TOKEN, $text, $matches);
        $invalid = [];

        foreach ($matches[1] as $i => $inner) {
            if (! self::isCode($inner)) {
                $invalid[] = $matches[0][$i];
            }
        }

        return array_values(array_unique($invalid));
    }

    /**
     * Whether an opening or closing brace is left over once the valid and invalid groups are taken out.
     */
    public static function hasStrayBraces(string $text): bool
    {
        $rest = (string) preg_replace(self::TOKEN, '', $text);

        return str_contains($rest, '{{') || str_contains($rest, '}}');
    }

    private static function isCode(string $inner): bool
    {
        return preg_match('/\A'.self::CODE.'\z/', $inner) === 1;
    }
}
