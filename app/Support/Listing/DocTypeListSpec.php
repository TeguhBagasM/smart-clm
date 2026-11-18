<?php

namespace App\Support\Listing;

use Illuminate\Database\Eloquent\Builder;

/**
 * The document types of a company, active or not.
 */
class DocTypeListSpec extends ListSpec
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
        return [
            Sort::column('code', 'Kode', 'dcs_document_types.code'),
            Sort::column('name', 'Nama', 'dcs_document_types.name'),
            Sort::column('updated_at', 'Terakhir diubah', 'dcs_document_types.updated_at'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return [
            Filter::select('status', 'Status', [['value' => 'active', 'label' => 'Aktif'], ['value' => 'inactive', 'label' => 'Nonaktif']],
                fn (Builder $query, string $status) => $query->where('dcs_document_types.is_active', $status === 'active'), primary: true),
            Filter::select('layout', 'Tata letak', [['value' => 'standard', 'label' => 'Standar'], ['value' => 'memo', 'label' => 'Memo'], ['value' => 'kebijakan', 'label' => 'Kebijakan']],
                fn (Builder $query, string $layout) => $query->where('dcs_document_types.layout_style', $layout), primary: true),
            Filter::select('header', 'Kop dokumen', [['value' => 'iso', 'label' => 'Kop ISO (kotak)'], ['value' => 'simple', 'label' => 'Kop sederhana']],
                fn (Builder $query, string $header) => $query->where('dcs_document_types.header_style', $header)),
            Filter::boolean('letterhead', 'Memakai kop surat perusahaan', fn (Builder $query, bool $yes) => $query->where('dcs_document_types.letterhead', $yes)),
            Filter::boolean('custom_numbering', 'Punya format nomor khusus', fn (Builder $query, bool $yes) => $yes
                ? $query->whereHas('numberingRules', fn (Builder $rule) => $rule->where('is_active', true))
                : $query->whereDoesntHave('numberingRules', fn (Builder $rule) => $rule->where('is_active', true))),
        ];
    }
}
