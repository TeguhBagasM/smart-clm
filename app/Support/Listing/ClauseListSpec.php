<?php

namespace App\Support\Listing;

use App\Models\Dcs\Clause;
use App\Models\Dcs\DocumentType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * The clause library of a company, active or not.
 */
class ClauseListSpec extends ListSpec
{
    public function __construct(private Tenant $tenant) {}

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
            ->whereRaw("lower(dcs_clauses.title) like ? escape '\\'", [$pattern])
            ->orWhereRaw("lower(dcs_clauses.content) like ? escape '\\'", [$pattern])
            ->orWhereRaw("lower(dcs_clauses.category) like ? escape '\\'", [$pattern]));
    }

    /**
     * @return array<int, Sort>
     */
    protected function sorts(): array
    {
        return [
            Sort::column('title', 'Judul', 'dcs_clauses.title'),
            Sort::column('category', 'Kategori', 'dcs_clauses.category'),
            Sort::column('updated_at', 'Terakhir diubah', 'dcs_clauses.updated_at'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        $categories = Clause::query()->where('tenant_id', $this->tenant->id)->distinct()->orderBy('category')->pluck('category')->map(fn (string $category): array => ['value' => $category, 'label' => $category])->all();
        $types = DocumentType::query()->where('tenant_id', $this->tenant->id)->orderBy('code')->get(['code', 'name'])->map(fn (DocumentType $type): array => ['value' => $type->code, 'label' => "{$type->code} — {$type->name}"])->all();

        return [
            Filter::select('status', 'Status', [['value' => 'active', 'label' => 'Aktif'], ['value' => 'inactive', 'label' => 'Nonaktif']],
                fn (Builder $query, string $status) => $query->where('dcs_clauses.is_active', $status === 'active'), primary: true),
            Filter::multi('category', 'Kategori', $categories, fn (Builder $query, array $values) => $query->whereIn('dcs_clauses.category', $values), primary: true),
            Filter::select('type', 'Berlaku untuk jenis', $types, fn (Builder $query, string $code) => $query->where(fn (Builder $inner) => $inner
                ->whereHas('documentTypes', fn (Builder $type) => $type->where('code', $code))->orWhereDoesntHave('documentTypes')), primary: true),
            Filter::boolean('mandatory', 'Klausul wajib', fn (Builder $query, bool $yes) => $query->where('dcs_clauses.is_mandatory', $yes)),
        ];
    }
}
