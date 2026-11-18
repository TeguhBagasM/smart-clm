<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a public `uuid` (set on creation unless one is supplied, as the
 * legacy import does). API payloads and URLs use it; the bigint key stays
 * internal.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }
}
