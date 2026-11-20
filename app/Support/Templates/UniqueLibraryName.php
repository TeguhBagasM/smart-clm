<?php

namespace App\Support\Templates;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * A name that must be unique within one company, ignoring letter case (the
 * unique indexes of the library tables are the last line; this gives the message).
 */
class UniqueLibraryName
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function taken(string $model, int $tenantId, string $column, string $value, ?Model $except): bool
    {
        return $model::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw(match ($column) {
                'title' => 'lower(title) = ?',
                'name' => 'lower(name) = ?',
                default => 'lower(code) = ?',
            }, [mb_strtolower(trim($value))])
            ->when($except !== null, fn (Builder $query) => $query->whereKeyNot($except?->getKey()))
            ->exists();
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function ensure(string $model, int $tenantId, string $column, string $value, ?Model $except, string $field, string $message): void
    {
        if (self::taken($model, $tenantId, $column, $value, $except)) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
