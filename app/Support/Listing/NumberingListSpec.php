<?php

namespace App\Support\Listing;

use Illuminate\Database\Eloquent\Builder;

/**
 * Numbering by document type: one row per active type, showing the mask it
 * uses (its own, or the company default).
 */
class NumberingListSpec extends ListSpec
{
    public function defaultDirection(): string
    {
        return 'asc';
    }

    protected function defaultSort(): string
    {
        return 'code';
    }

    protected function search(Builder $query, string $term): void
    {
        $pattern = $this->like(mb_strtolower($term));

        $query->where(fn (Builder $inner) => $inner
            ->whereRaw("lower(dcs_document_types.code) like ? escape '\\'", [$pattern])
            ->orWhereRaw("lower(dcs_document_types.name) like ? escape '\\'", [$pattern]));
    }

    /**
     * @return array<int, Sort>
     */
    protected function sorts(): array
    {
        return [Sort::column('code', 'Kode', 'dcs_document_types.code'), Sort::column('name', 'Nama', 'dcs_document_types.name')];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return [
            Filter::select('format', 'Format nomor', [['value' => 'default', 'label' => 'Memakai format default'], ['value' => 'custom', 'label' => 'Format khusus']],
                fn (Builder $query, string $format) => $format === 'custom'
                    ? $query->whereHas('numberingRules', fn (Builder $rule) => $rule->where('is_active', true))
                    : $query->whereDoesntHave('numberingRules', fn (Builder $rule) => $rule->where('is_active', true)), primary: true),
        ];
    }
}
