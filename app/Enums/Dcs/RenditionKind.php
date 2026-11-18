<?php

namespace App\Enums\Dcs;

/**
 * What a stored PDF is: composed from the structured content, uploaded as a
 * finished file, signed (a composed or uploaded file with signatures filled in),
 * or the English reference translation.
 */
enum RenditionKind: string
{
    case Composed = 'composed';
    case Uploaded = 'uploaded';
    case Signed = 'signed';
    case Translated = 'translated';

    /**
     * Kinds that can be the version's main file (everything except the translation).
     *
     * @return list<string>
     */
    public static function mainValues(): array
    {
        return [self::Composed->value, self::Uploaded->value, self::Signed->value];
    }
}
