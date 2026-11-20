<?php

namespace App\Support\Listing;

use Illuminate\Database\Eloquent\Builder;

/**
 * The vendors of a company: search by name, NPWP (digits, with or without dots),
 * contact name or email; filter by status and by missing NPWP or bank account;
 * order by name (read from the (tenant_id, lower(name)) index), last change or
 * creation. Labels come from config/vendors.php. The query is the company's
 * relation, so other companies never appear.
 */
class VendorListSpec extends ListSpec
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
        $digits = preg_replace('/\D/', '', $term);

        $query->where(function (Builder $inner) use ($pattern, $digits): void {
            $inner->whereRaw("lower(vendors.name) like ? escape '\\'", [$pattern])
                ->orWhereRaw("lower(vendors.pic_name) like ? escape '\\'", [$pattern])
                ->orWhereRaw("lower(vendors.email) like ? escape '\\'", [$pattern]);

            if (strlen((string) $digits) >= 3) {
                $inner->orWhere('vendors.npwp', 'like', '%'.$digits.'%');
            }
        });
    }

    /**
     * @return array<int, Sort>
     */
    protected function sorts(): array
    {
        $labels = config()->array('vendors.list.sorts');

        return [
            new Sort('name', $labels['name'], fn (Builder $query, string $direction) => $query->orderByRaw('lower(vendors.name) '.($direction === 'asc' ? 'asc' : 'desc'))),
            Sort::column('updated_at', $labels['updated_at'], 'vendors.updated_at'),
            Sort::column('created_at', $labels['created_at'], 'vendors.created_at'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        $status = config()->array('vendors.list.filters.status');
        $completeness = config()->array('vendors.list.filters.completeness');

        return [
            Filter::select('status', $status['label'], $this->options($status['options']),
                fn (Builder $query, string $value) => $query->where('vendors.is_active', $value === 'active'), primary: true),
            Filter::select('completeness', $completeness['label'], $this->options($completeness['options']),
                fn (Builder $query, string $value) => $query->whereNull($value === 'no_npwp' ? 'vendors.npwp' : 'vendors.bank_account_number')),
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
