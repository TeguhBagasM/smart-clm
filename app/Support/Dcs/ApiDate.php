<?php

namespace App\Support\Dcs;

use Carbon\CarbonInterface;

/**
 * Dates as the Node API wrote them: ISO 8601 in UTC with milliseconds and a "Z".
 */
class ApiDate
{
    public static function format(?CarbonInterface $date): ?string
    {
        return $date?->copy()->utc()->format('Y-m-d\TH:i:s.v\Z');
    }
}
