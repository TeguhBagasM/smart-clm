<?php

namespace App\Enums\MasterData;

/**
 * The contract module a document type is offered in (Node docTypes[].appliesTo).
 * Stored as a varchar guarded by a CHECK on PostgreSQL; adding a case needs a
 * migration that widens it.
 */
enum DocumentModule: string
{
    case External = 'external';
    case Employee = 'employee';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::External => 'Dokumen Eksternal',
            self::Employee => 'Dokumen Karyawan',
            self::Both => 'Keduanya',
        };
    }

    /**
     * Whether a type of this module is offered in the given module.
     */
    public function offeredIn(self $module): bool
    {
        return $this === self::Both || $this === $module;
    }
}
