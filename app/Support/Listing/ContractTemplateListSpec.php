<?php

namespace App\Support\Listing;

use Illuminate\Database\Eloquent\Builder;

/**
 * Contract templates: search by name or description; filter by status, kind
 * (external or employee), category and whether a version is published; order by
 * name or last change.
 */
class ContractTemplateListSpec extends ListSpec
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
        return 'name';
    }

    protected function search(Builder $query, string $term): void
    {
        $pattern = $this->like(mb_strtolower($term));

        $query->where(fn (Builder $inner) => $inner
            ->whereRaw("lower(contract_templates.name) like ? escape '\\'", [$pattern])
            ->orWhereRaw("lower(contract_templates.description) like ? escape '\\'", [$pattern]));
    }

    /**
     * @return array<int, Sort>
     */
    protected function sorts(): array
    {
        $labels = config()->array('contract_templates.list.templates.sorts');

        return [
            new Sort('name', $labels['name'], fn (Builder $query, string $direction) => $query->orderByRaw('lower(contract_templates.name) '.($direction === 'asc' ? 'asc' : 'desc'))),
            Sort::column('updated_at', $labels['updated_at'], 'contract_templates.updated_at'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        $config = config()->array('contract_templates.list.templates');

        return [
            Filter::select('status', $config['status']['label'], $this->options($config['status']['options']),
                fn (Builder $query, string $value) => $query->where('contract_templates.is_active', $value === 'active'), primary: true),
            Filter::select('module', $config['module']['label'], $this->options($config['module']['options']),
                fn (Builder $query, string $value) => $query->where('contract_templates.module', $value)),
            Filter::select('category', $config['category']['label'], $this->categoryOptions,
                fn (Builder $query, string $value) => $query->where('contract_templates.contract_category_id', (int) $value)),
            Filter::select('version', $config['version']['label'], $this->options($config['version']['options']),
                fn (Builder $query, string $value) => $value === 'published' ? $query->whereHas('currentVersion') : $query->whereDoesntHave('currentVersion')),
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
