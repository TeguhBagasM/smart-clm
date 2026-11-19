<?php

namespace App\Support\Dcs;

use InvalidArgumentException;

/**
 * Rules for document number masks such as {DocType}-{Dept}-{Seq:2}-{Rev:2}-{YY}.
 *
 * A mask is free text around tokens. The number a document is issued with is the
 * mask rendered once; `{Rev}` is the revision (the version's minor, 00 = none yet),
 * so each version has its own full number while the part without the revision, the
 * base number, stays the same. `{Seq:N}` is the running number padded to at least N
 * digits and never cut when it grows longer.
 */
class NumberingMask
{
    /** Tokens other than the sequence and the revision that a DCS mask may use ({Dept}, {YYYY}, {YY} and {MM} are the short forms). */
    public const TOKENS = ['DocType', 'Department', 'Dept', 'Year', 'YYYY', 'YY', 'Month', 'MM', 'MonthRoman', 'Day'];

    /** The running number: {Sequence} is the older spelling of {Seq}. */
    public const SEQUENCE_TOKENS = ['Sequence', 'Seq'];

    public const REVISION_TOKEN = 'Rev';

    /** Tokens whose values select the counter: a new combination restarts at 1. */
    public const DEFAULT_SCOPE_TOKENS = ['DocType', 'Department', 'Year'];

    /** For the new style of mask: the counter restarts each year, per document type. */
    public const SUGGESTED_SCOPE_TOKENS = ['DocType', 'Year'];

    public const DEFAULT_MASK = '{DocType}/{Department}/{Year}/{Sequence:4}';

    /** For example SOP-HED-27-00-26. */
    public const SUGGESTED_MASK = '{DocType}-{Dept}-{Seq:2}-{Rev:2}-{YY}';

    /**
     * Returns the reason the mask is unusable, or null when it is valid.
     */
    public static function validate(?string $mask): ?string
    {
        if ($mask === null || trim($mask) === '') {
            return 'Format nomor tidak boleh kosong.';
        }

        if (! preg_match('/\{(?:Sequence|Seq)(?::\d+)?\}/', $mask)) {
            return 'Format nomor wajib memuat token {Sequence} atau {Sequence:N}.';
        }

        preg_match_all('/\{(\w+)(?::(\d+))?\}/', $mask, $matches, PREG_SET_ORDER);
        $seen = [];

        foreach ($matches as $match) {
            [$token, $length] = [$match[1], (int) ($match[2] ?? 0)];

            if (! in_array($token, [...self::TOKENS, ...self::SEQUENCE_TOKENS, self::REVISION_TOKEN], true)) {
                $available = implode(', ', array_map(fn (string $name): string => '{'.$name.'}', [...self::TOKENS, 'Seq', 'Rev']));

                return 'Token "{'.$token.'}" tidak dikenal. Token yang tersedia: '.$available.'.';
            }

            if ($length > 12) {
                return 'Panjang minimal pada "{'.$token.':'.$length.'}" terlalu besar (maksimal 12).';
            }

            if (in_array($token, [...self::SEQUENCE_TOKENS, self::REVISION_TOKEN], true) && isset($seen[$token])) {
                return 'Token "{'.$token.'}" hanya boleh dipakai sekali.';
            }

            $seen[$token] = true;
        }

        if (isset($seen['Seq'], $seen['Sequence'])) {
            return 'Pakai {Seq} atau {Sequence}, jangan keduanya.';
        }

        return null;
    }

    /**
     * Whether the mask has the new style (a {Seq} or {Rev} token), whose counter restarts per document type and year.
     */
    public static function usesNewStyle(string $mask): bool
    {
        return (bool) preg_match('/\{(?:Seq|Rev|Dept|YY|YYYY|MM)(?::\d+)?\}/', $mask);
    }

    /**
     * The scope tokens a rule should start with for this mask.
     *
     * @return list<string>
     */
    public static function scopeFor(string $mask): array
    {
        return self::usesNewStyle($mask) ? self::SUGGESTED_SCOPE_TOKENS : self::DEFAULT_SCOPE_TOKENS;
    }

    /**
     * The number as written for revision `$revision`.
     *
     * @param  array<string, string|int>  $tokens  DocType, Department, Year, Month, Day
     */
    public static function render(string $mask, array $tokens, int $sequence, int $revision = 0): string
    {
        $sawEmpty = false;

        $out = preg_replace_callback('/\{(\w+)(?::(\d+))?\}/', function (array $match) use ($tokens, $sequence, $revision, &$sawEmpty): string {
            [$name, $length] = [$match[1], (int) ($match[2] ?? 0)];

            $value = match (true) {
                in_array($name, self::SEQUENCE_TOKENS, true) => self::pad($sequence, $length),
                $name === self::REVISION_TOKEN => self::pad($revision, $length),
                $name === 'MonthRoman' => self::romanMonth((int) ($tokens['Month'] ?? 0)),
                $name === 'Dept' => (string) self::need($tokens, 'Department'),
                $name === 'YYYY' => (string) self::need($tokens, 'Year'),
                $name === 'YY' => substr(str_pad((string) self::need($tokens, 'Year'), 2, '0', STR_PAD_LEFT), -2),
                $name === 'MM' => str_pad((string) self::need($tokens, 'Month'), 2, '0', STR_PAD_LEFT),
                default => (string) self::need($tokens, $name),
            };
            $sawEmpty = $sawEmpty || $value === '';

            return $value;
        }, $mask);

        if ($sawEmpty) {
            $out = preg_replace(['/([\/.\-_])\1+/', '/^[\/.\-_]+|[\/.\-_]+$/'], ['$1', ''], (string) $out);
        }

        return (string) $out;
    }

    /**
     * The number without its revision: the same number for every version of a document.
     *
     * @param  array<string, string|int>  $tokens
     */
    public static function renderBase(string $mask, array $tokens, int $sequence): string
    {
        $without = (string) preg_replace('/[\/.\-_]?\{Rev(?::\d+)?\}/', '', $mask, 1);
        $without = preg_replace('/^[\/.\-_]+/', '', $without);

        return self::render((string) $without, $tokens, $sequence);
    }

    /**
     * @param  array<string, string|int>  $tokens
     */
    public static function scopeHash(array $scopeTokens, array $tokens): string
    {
        return implode('|', array_map(
            fn (string $name): string => (string) ($tokens[$name] ?? throw new InvalidArgumentException('Numbering scope token "'.$name.'" has no value')),
            $scopeTokens,
        ));
    }

    /**
     * Padded to at least `$length` digits; a longer number is never cut.
     */
    private static function pad(int $number, int $length): string
    {
        return $length > 0 ? str_pad((string) $number, $length, '0', STR_PAD_LEFT) : (string) $number;
    }

    /**
     * @param  array<string, string|int>  $tokens
     */
    private static function need(array $tokens, string $name): string|int
    {
        return $tokens[$name] ?? throw new InvalidArgumentException('Mask token "{'.$name.'}" has no value in the numbering context');
    }

    private static function romanMonth(int $month): string
    {
        $romans = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

        return $romans[($month ?: 1) - 1] ?? 'I';
    }
}
