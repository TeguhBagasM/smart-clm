<?php

namespace App\Support\Listing;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * One way to order a list. `$apply` receives the query and the direction.
 */
final class Sort
{
    /**
     * @param  Closure(Builder<*>, string): mixed  $apply
     */
    public function __construct(public readonly string $key, public readonly string $label, private readonly Closure $apply) {}

    /**
     * Orders by a plain column.
     */
    public static function column(string $key, string $label, string $column): self
    {
        return new self($key, $label, function (Builder $query, string $direction) use ($column): void {
            $query->orderBy($column, $direction === 'asc' ? 'asc' : 'desc');
        });
    }

    /**
     * @param  Builder<*>  $query
     */
    public function apply(Builder $query, string $direction): void
    {
        ($this->apply)($query, $direction);
    }
}
