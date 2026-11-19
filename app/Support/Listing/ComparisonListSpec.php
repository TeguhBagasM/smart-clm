<?php

namespace App\Support\Listing;

use App\Enums\AiTools\ComparisonStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * The history of comparisons: search by the names of the two documents, filter by status.
 */
class ComparisonListSpec extends ListSpec
{
    protected function defaultSort(): string
    {
        return 'created_at';
    }

    protected function search(Builder $query, string $term): void
    {
        $pattern = $this->like(mb_strtolower($term));

        $query->where(fn (Builder $inner) => $inner
            ->whereRaw("lower(document_comparisons.label_a) like ? escape '\\'", [$pattern])
            ->orWhereRaw("lower(document_comparisons.label_b) like ? escape '\\'", [$pattern]));
    }

    /**
     * @return array<int, Sort>
     */
    protected function sorts(): array
    {
        return [
            Sort::column('created_at', 'Tanggal dibuat', 'document_comparisons.created_at'),
            Sort::column('status', 'Status', 'document_comparisons.status'),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return [
            Filter::select('status', 'Status', array_map(fn (ComparisonStatus $status): array => ['value' => $status->value, 'label' => $status->label()], ComparisonStatus::cases()),
                fn (Builder $query, string $status) => $query->where('document_comparisons.status', $status), primary: true),
        ];
    }
}
