<?php

namespace App\Support\Dcs\Pdf\Php\Draw;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The two Indonesian date styles the Node engine printed with toLocaleDateString("id-ID"):
 * 5/1/2026 and 05 Mei 2026, read in the zone the Node server ran in.
 */
final class IdDate
{
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public static function numeric(?string $iso, string $timezone): string
    {
        $date = self::parse($iso, $timezone);

        return $date === null ? '-' : $date->format('j/n/Y');
    }

    public static function short(string $iso, string $timezone): string
    {
        $date = self::parse($iso, $timezone);

        return $date === null ? 'Invalid Date' : $date->format('d').' '.self::MONTHS[(int) $date->format('n') - 1].' '.$date->format('Y');
    }

    private static function parse(?string $iso, string $timezone): ?DateTimeImmutable
    {
        if ($iso === null || $iso === '') {
            return null;
        }

        return (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone($timezone));
    }
}
