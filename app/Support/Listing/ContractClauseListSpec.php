<?php

namespace App\Support\Listing;

use Illuminate\Database\Eloquent\Builder;

/**
 * The clause library: search by title or text; filter by status, category and
 * kind (mandatory or protected); order by title or last change. Labels come from
 * config/contract_templates.php. The query is the company's relation.
 */
class ContractClauseListSpec extends ListSpec
{
    /**
     * @param  array<int, array{value: string, label: string}>  $categoryOptions
     */
    public function __construct(private array $categoryOptions) {}

    public function defaultDirection(): string
    {
        return 'asc';
    }

    protected function defaultSort(): string
    {
        return 'title';
    }

    protected function search(Builder $query, string $term): void
    {
        $pattern = $this->like(mb_strtolower($term));

        $query->where(fn (Builder $inner) => $inner
            ->whereRaw("lower(contract_clauses.title) like ? escape '\\'", [$pattern])
            ->orWhereRaw("lower(contract_clauses.body) like ? escape '\\'", [$pattern]));
    }

    /**
     * @return array<int, Sort>
     */
    protected function sorts(): array
    {
        $labels = config()->array('contract_templates.list.clauses.sorts');

        return [
            new Sort('title', $labels['title'], fn (Builder $query, string $direction) => $query->orderByRaw('lower(contract_clauses.title) '.($direction === 'asc' ? 'asc' : 'desc'))),
            Sort::column('updated_at', $labels['updated_at'], 'contract_clauses.updated_at'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        $config = config()->array('contract_templates.list.clauses');

        return [
            Filter::select('status', $config['status']['label'], $this->options($config['status']['options']),
                fn (Builder $query, string $value) => $query->where('contract_clauses.is_active', $value === 'active'), primary: true),
            Filter::select('category', $config['category']['label'], $this->categoryOptions,
                fn (Builder $query, string $value) => $query->where('contract_clauses.contract_category_id', (int) $value)),
            Filter::select('kind', $config['kind']['label'], $this->options($config['kind']['options']),
                fn (Builder $query, string $value) => $query->where($value === 'protected' ? 'contract_clauses.is_protected' : 'contract_clauses.is_mandatory', true)),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<array{value: string, label: string}>
     */
    private function options(array $labels): array
    {
        return array_map(fn (string $value, string $label): array => ['value' => $value, 'label' => $label], array_keys($labels), array_values($labels));
    }
}
