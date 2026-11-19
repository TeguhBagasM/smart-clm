<?php

namespace App\Http\Resources\AiTools;

use App\Models\AiTools\DocumentComparison;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DocumentComparison
 */
class ComparisonListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'label_a' => $this->label_a,
            'label_b' => $this->label_b,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'progress' => $this->progress,
            'created_at' => $this->created_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
        ];
    }
}
