<?php

namespace App\Http\Resources\Documents\Config;

use App\Models\Dcs\DocumentType;
use App\Support\Dcs\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A document type as a row of the configuration list. Counts `sections`,
 * `steps` and `defaultClauses` and loads `numberingRules`.
 *
 * @mixin DocumentType
 */
class DocTypeRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'layout' => $this->layout_style->value,
            'header' => $this->header_style->value,
            'letterhead' => $this->letterhead,
            'level' => $this->document_level,
            'is_active' => $this->is_active,
            'sections_count' => (int) $this->getAttribute('sections_count'),
            'columns_count' => (int) $this->getAttribute('steps_count'),
            'clauses_count' => (int) $this->getAttribute('default_clauses_count'),
            'custom_numbering' => $this->numberingRules->contains(fn ($rule): bool => $rule->is_active),
            'updated_at' => ApiDate::format($this->updated_at),
        ];
    }
}
