<?php

namespace App\Support\Dcs\Legacy;

use Carbon\CarbonImmutable;

/**
 * Reads values out of rows of the Node database the same way whether it is
 * PostgreSQL (jsonb and arrays come back as text) or a test fixture.
 */
class LegacyRows
{
    /**
     * @return array<mixed>
     */
    public static function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * A PostgreSQL array ("{1,2}", "{a,b}") or a JSON array, as a PHP list.
     *
     * @return list<string>
     */
    public static function list(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        if (! is_string($value) || $value === '' || $value === '{}') {
            return [];
        }

        if (str_starts_with($value, '[')) {
            return array_values(array_map('strval', self::json($value)));
        }

        return array_map(fn (string $item): string => trim($item, '"'), explode(',', trim($value, '{}')));
    }

    public static function time(mixed $value): ?CarbonImmutable
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse((string) $value, 'UTC');
    }

    public static function text(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
