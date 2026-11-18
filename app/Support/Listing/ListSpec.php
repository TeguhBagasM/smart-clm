<?php

namespace App\Support\Listing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The definition of one list: what can be searched, filtered and sorted, and
 * how. A list page declares this once on the server; the frontend draws the
 * search box, filters, sort menu and pagination from `meta()`, so every list
 * behaves the same way and a new filter needs no frontend change.
 *
 * Query string: `search`, `filter[key]` (`filter[key][]` for multi,
 * `filter[key][from|to]` for a date range), `sort`, `direction`, `per_page`, `page`.
 */
abstract class ListSpec
{
    protected const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    protected const DEFAULT_PER_PAGE = 25;

    /**
     * @return array<int, Filter>
     */
    abstract protected function filters(): array;

    /**
     * @return array<int, Sort>
     */
    abstract protected function sorts(): array;

    /**
     * The sort used when the request names none (or an unknown one).
     */
    abstract protected function defaultSort(): string;

    /**
     * @param  Builder<*>  $query
     */
    abstract protected function search(Builder $query, string $term): void;

    public function defaultDirection(): string
    {
        return 'desc';
    }

    public function parse(Request $request): ListQuery
    {
        $sorts = collect($this->sorts())->keyBy('key');
        $sort = $sorts->has($request->query('sort')) ? (string) $request->query('sort') : $this->defaultSort();
        $direction = in_array($request->query('direction'), ['asc', 'desc'], true) ? (string) $request->query('direction') : $this->defaultDirection();
        $perPage = (int) $request->query('per_page');
        $raw = is_array($request->query('filter')) ? $request->query('filter') : [];

        $filters = [];

        foreach ($this->filters() as $filter) {
            $value = $filter->parse($raw[$filter->key] ?? null);

            if ($value !== null) {
                $filters[$filter->key] = $value;
            }
        }

        return new ListQuery(
            trim((string) $request->query('search')),
            $filters,
            $sort,
            $direction,
            in_array($perPage, static::PER_PAGE_OPTIONS, true) ? $perPage : static::DEFAULT_PER_PAGE,
        );
    }

    /**
     * Applies search, filters and order, with the id as the last tie-break so
     * pages never overlap or skip rows.
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public function apply(Builder $query, ListQuery $list): Builder
    {
        if ($list->search !== '') {
            $this->search($query, $list->search);
        }

        foreach ($this->filters() as $filter) {
            if (array_key_exists($filter->key, $list->filters)) {
                $filter->apply($query, $list->filters[$filter->key]);
            }
        }

        collect($this->sorts())->firstWhere('key', $list->sort)?->apply($query, $list->direction);
        $query->orderBy($query->getModel()->getQualifiedKeyName(), $list->direction === 'asc' ? 'asc' : 'desc');

        return $query;
    }

    /**
     * @param  Builder<*>  $query
     * @return LengthAwarePaginator<int, mixed>
     */
    public function paginate(Builder $query, ListQuery $list): LengthAwarePaginator
    {
        return $this->apply($query, $list)->paginate($list->perPage)->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(ListQuery $list): array
    {
        return [
            'search' => $list->search,
            'sort' => $list->sort,
            'direction' => $list->direction,
            'per_page' => $list->perPage,
            'per_page_options' => static::PER_PAGE_OPTIONS,
            'sorts' => array_map(fn (Sort $sort): array => ['key' => $sort->key, 'label' => $sort->label], $this->sorts()),
            'filters' => array_map(fn (Filter $filter): array => $filter->schema($list->filters[$filter->key] ?? null), $this->filters()),
            'active_count' => count($list->filters),
        ];
    }

    /**
     * Escapes the characters a LIKE pattern treats as wildcards, so a search
     * for "50%" finds "50%" and not everything starting with "50".
     */
    protected function like(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}
