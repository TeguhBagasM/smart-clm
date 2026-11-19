<?php

namespace App\Enums\Dcs;

/**
 * How a signature column of a document type gets its person.
 *
 * - Person: the administrator names one user in the template.
 * - AtCreation: whoever drafts the document picks one user from the column's candidates.
 * - Legacy: a column set up before people were named (a role only). It keeps the old
 *   rule, a named person else any holder of the role, until an administrator sets it.
 */
enum SignerFillMode: string
{
    case Legacy = 'legacy';
    case Person = 'person';
    case AtCreation = 'at_creation';

    public function label(): string
    {
        return match ($this) {
            self::Legacy => 'Belum ditetapkan (lama)',
            self::Person => 'Orang tertentu',
            self::AtCreation => 'Dipilih saat dokumen dibuat',
        };
    }
}
