<?php

namespace App\Support\Templates;

use RuntimeException;

/**
 * Something tried to change the wording, clauses, parties or fields of a
 * template version that is no longer a draft, or to delete such a version.
 * The same rule is enforced by a trigger on PostgreSQL.
 */
class FrozenTemplateVersion extends RuntimeException
{
    public static function version(int|string|null $id): self
    {
        return new self("Template version {$id} is no longer a draft and cannot change.");
    }
}
