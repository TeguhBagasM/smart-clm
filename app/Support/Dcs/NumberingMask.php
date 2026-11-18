<?php

namespace App\Support\Dcs;

use InvalidArgumentException;

/**
 * Rules for document number masks such as {DocType}/{Department}/{Year}/{Sequence:4}.
 */
class NumberingMask
{
    /** Tokens other than {Sequence} that a DCS mask may use. */
    public const TOKENS = ['DocType', 'Department', 'Year', 'Month', 'MonthRoman', 'Day'];

    /** Tokens whose values select the counter: a new combination restarts at 1. */
    public const DEFAULT_SCOPE_TOKENS = ['DocType', 'Department', 'Year'];

    public const DEFAULT_MASK = '{DocType}/{Department}/{Year}/{Sequence:4}';

    /**
     * Returns the reason the mask is unusable, or null when it is valid.
     */
    public static function validate(?string $mask): ?string
    {
        if ($mask === null || trim($mask) === '') {
            return 'Format nomor tidak boleh kosong.';
        }

        if (! preg_match('/\{Sequence(?::\d+)?\}/', $mask)) {
            return 'Format nomor wajib memuat token {Sequence} atau {Sequence:N}.';
        }

        preg_match_all('/\{(\w+)(?::\d+)?\}/', $mask, $matches);

        foreach (array_unique($matches[1]) as $token) {
            if ($token !== 'Sequence' && ! in_array($token, self::TOKENS, true)) {
                $available = implode(', ', array_map(fn (string $name): string => '{'.$name.'}', self::TOKENS));

                return 'Token "{'.$token.'}" tidak dikenal. Token yang tersedia: '.$available.', {Sequence}.';
            }
        }

        return null;
    }

    /**
     * Renders the final number. {Sequence:4} is zero padded and {MonthRoman} prints
     * the month in Roman numerals. A token without a value is an error. A token
     * that resolves to an empty string is dropped and the separators around it
     * collapse, so "001/PKS//VII" becomes "001/PKS/VII".
     *
     * @param  array<string, string|int>  $tokens
     */
    public static function render(string $mask, array $tokens, int $sequence): string
    {
        $out = preg_replace_callback(
            '/\{Sequence(?::(\d+))?\}/',
            fn (array $match): string => isset($match[1]) ? str_pad((string) $sequence, (int) $match[1], '0', STR_PAD_LEFT) : (string) $sequence,
            $mask,
            1,
        );

        $sawEmpty = false;

        $out = preg_replace_callback('/\{(\w+)(?::\d+)?\}/', function (array $match) use ($tokens, &$sawEmpty): string {
            $name = $match[1];

            if ($name === 'MonthRoman') {
                return self::romanMonth((int) ($tokens['Month'] ?? $tokens['MonthRoman'] ?? 0));
            }

            if (! array_key_exists($name, $tokens)) {
                throw new InvalidArgumentException('Mask token "{'.$name.'}" has no value in the numbering context');
            }

            $value = (string) $tokens[$name];
            $sawEmpty = $sawEmpty || $value === '';

            return $value;
        }, (string) $out);

        if ($sawEmpty) {
            $out = preg_replace(['/([\/.\-_])\1+/', '/^[\/.\-_]+|[\/.\-_]+$/'], ['$1', ''], $out);
        }

        return (string) $out;
    }

    /**
     * The value that selects the counter: the scope tokens' values joined by "|".
     * A new combination (another year, department or type) starts a new counter.
     *
     * @param  list<string>  $scopeTokens
     * @param  array<string, string|int>  $tokens
     */
    public static function scopeHash(array $scopeTokens, array $tokens): string
    {
        return implode('|', array_map(
            fn (string $name): string => (string) ($tokens[$name] ?? throw new InvalidArgumentException('Numbering scope token "'.$name.'" has no value')),
            $scopeTokens,
        ));
    }

    private static function romanMonth(int $month): string
    {
        $romans = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

        return $romans[($month ?: 1) - 1] ?? 'I';
    }
}
