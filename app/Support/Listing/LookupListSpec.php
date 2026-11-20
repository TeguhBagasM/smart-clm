<?php

namespace App\Support\Listing;

use App\Enums\MasterData\MasterList;
use Illuminate\Database\Eloquent\Builder;

/**
 * The list of one master data table of a company: search by name (and code),
 * filter by active and, for lists with defaults, by origin, order by the
 * company's own order, name or date. The query itself is the company's
 * relation (`$tenant->platforms()`), so other companies never appear.
 */
class LookupListSpec extends ListSpec
{
    public function __construct(protected MasterList $list) {}

    public function defaultDirection(): string
    {
        return 'asc';
    }

    protected function defaultSort(): string
    {
        return 'order';
    }

    protected function table(): string
    {
        return (new ($this->list->model()))->getTable();
    }

    protected function search(Builder $query, string $term): void
    {
        $pattern = $this->like(mb_strtolower($term));
        $table = $this->table();

        $query->where(function (Builder $inner) use ($pattern, $table): void {
            $inner->whereRaw("lower({$table}.name) like ? escape '\\'", [$pattern]);

            if ($this->list->hasCode()) {
                $inner->orWhereRaw("lower({$table}.code) like ? escape '\\'", [$pattern]);
            }
        });
    }

    /**
     * @return array<int, Sort>
     */
    protected function sorts(): array
    {
        $table = $this->table();

        return [
            Sort::column('order', 'Urutan pilihan', "{$table}.sort_order"),
            Sort::column('name', 'Nama', "{$table}.name"),
            ...($this->list->hasCode() ? [Sort::column('code', 'Kode', "{$table}.code")] : []),
            Sort::column('created_at', 'Tanggal dibuat', "{$table}.created_at"),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        $table = $this->table();

        return [
            Filter::select('status', 'Status', [['value' => 'active', 'label' => 'Tampil di pilihan'], ['value' => 'inactive', 'label' => 'Disembunyikan']],
                fn (Builder $query, string $status) => $query->where("{$table}.is_active", $status === 'active'), primary: true),
            ...($this->list->hasCode() ? [Filter::select('origin', 'Asal', [['value' => 'system', 'label' => 'Bawaan'], ['value' => 'custom', 'label' => 'Dibuat sendiri']],
                fn (Builder $query, string $origin) => $query->where("{$table}.is_system", $origin === 'system'))] : []),
        ];
    }
}
