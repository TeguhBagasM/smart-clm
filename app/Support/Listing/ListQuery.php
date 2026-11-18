<?php

namespace App\Support\Listing;

/**
 * What a request asked of a list, after checking it against the list's own
 * definition: nothing here can name a column, filter or page size the list did
 * not declare.
 */
final class ListQuery
{
    /**
     * @param  array<string, mixed>  $filters  filter key => cleaned value (only filters that are set)
     */
    public function __construct(
        public readonly string $search,
        public readonly array $filters,
        public readonly string $sort,
        public readonly string $direction,
        public readonly int $perPage,
    ) {}
}
