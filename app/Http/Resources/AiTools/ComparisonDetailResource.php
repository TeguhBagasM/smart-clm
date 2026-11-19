<?php

namespace App\Http\Resources\AiTools;

use App\Enums\AiTools\ComparisonStatus;
use App\Models\AiTools\ComparisonRow;
use App\Models\AiTools\ComparisonSource;
use App\Models\AiTools\DocumentComparison;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The comparison page: progress while it runs, the matrix and the review once it is complete.
 * Loads `sources`, and `rows.articleA`/`rows.articleB` when completed. Never exposes database ids.
 *
 * @mixin DocumentComparison
 */
class ComparisonDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $done = $this->status === ComparisonStatus::Completed;

        return [
            'id' => $this->uuid,
            'label_a' => $this->label_a,
            'label_b' => $this->label_b,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'stage' => $this->stage === null ? null : ['value' => $this->stage->value, 'label' => $this->stage->label()],
            'progress' => $this->progress,
            'error' => $this->error_message,
            'warnings' => $this->warnings ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'sources' => $this->sources->sortBy('side')->map(fn (ComparisonSource $source): array => [
                'side' => $source->side,
                'kind' => $source->kind->value,
                'name' => $source->original_name,
                'pages' => $source->page_count,
                'method' => $source->extraction_method?->value,
            ])->values()->all(),
            'summary' => $done ? $this->summary : null,
            'rows' => $done ? $this->rows->map(fn (ComparisonRow $row): array => [
                'no' => $row->ordinal,
                'a' => $row->articleA === null ? null : ['label' => $row->articleA->number_label, 'heading' => $row->articleA->heading],
                'b' => $row->articleB === null ? null : ['label' => $row->articleB->number_label, 'heading' => $row->articleB->heading],
                'kind' => ['value' => $row->change_kind->value, 'label' => $row->change_kind->label()],
                'diff' => $row->diff,
                'explanation' => $row->explanation,
            ])->values()->all() : [],
        ];
    }
}
