<?php

namespace App\Support\MasterData;

use Illuminate\Support\Str;

/**
 * Derives the code of a list entry that Node kept by name only (categories,
 * employment types, party types): ASCII, upper case, words joined by "-".
 * "Magang / Internship" becomes "MAGANG-INTERNSHIP".
 */
class LookupCode
{
    public static function fromName(string $name, int $maxLength = 100): string
    {
        $code = Str::upper(Str::slug($name, '-'));

        return rtrim(mb_substr($code, 0, $maxLength), '-');
    }
}
