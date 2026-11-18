<?php

namespace App\Support\Listing;

use Illuminate\Database\Eloquent\Builder;

/**
 * The departments of a company, active or not.
 */
class DepartmentListSpec extends ListSpec
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
            ->whereRaw("lower(dcs_departments.code) like ? escape '\\'", [$pattern])
            ->orWhereRaw("lower(dcs_departments.name) like ? escape '\\'", [$pattern]));
    }

    /**
     * @return array<int, Sort>
     */
    protected function sorts(): array
    {
        return [
            Sort::column('code', 'Kode', 'dcs_departments.code'),
            Sort::column('name', 'Nama', 'dcs_departments.name'),
            Sort::column('created_at', 'Tanggal dibuat', 'dcs_departments.created_at'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return [
            Filter::select('status', 'Status', [['value' => 'active', 'label' => 'Aktif'], ['value' => 'inactive', 'label' => 'Nonaktif']],
                fn (Builder $query, string $status) => $query->where('dcs_departments.is_active', $status === 'active'), primary: true),
            Filter::boolean('in_use', 'Dipakai dokumen', fn (Builder $query, bool $yes) => $yes ? $query->whereHas('documents') : $query->whereDoesntHave('documents')),
        ];
    }
}
