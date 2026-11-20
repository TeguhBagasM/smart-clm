<?php

namespace App\Support\Listing;

use Illuminate\Database\Eloquent\Builder;

/**
 * The fields of the library: search by code or name; filter by status and type;
 * order by name or code.
 */
class ContractVariableListSpec extends ListSpec
{
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
            ->whereRaw("lower(contract_variables.code) like ? escape '\\'", [$pattern])
            ->orWhereRaw("lower(contract_variables.name) like ? escape '\\'", [$pattern]));
    }

    /**
     * @return array<int, Sort>
     */
    protected function sorts(): array
    {
        $labels = config()->array('contract_templates.list.variables.sorts');

        return [
            new Sort('name', $labels['name'], fn (Builder $query, string $direction) => $query->orderByRaw('lower(contract_variables.name) '.($direction === 'asc' ? 'asc' : 'desc'))),
            Sort::column('code', $labels['code'], 'contract_variables.code'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        $config = config()->array('contract_templates.list.variables');

        return [
            Filter::select('status', $config['status']['label'], $this->options($config['status']['options']),
                fn (Builder $query, string $value) => $query->where('contract_variables.is_active', $value === 'active'), primary: true),
            Filter::select('type', $config['type']['label'], $this->options($config['type']['options']),
                fn (Builder $query, string $value) => $query->where('contract_variables.value_type', $value)),
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
