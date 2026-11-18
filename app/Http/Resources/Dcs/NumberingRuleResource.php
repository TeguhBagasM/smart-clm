<?php

namespace App\Http\Resources\Dcs;

use App\Models\Dcs\NumberingRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A numbering rule in the shape of the Node API. `isOverride` tells whether the
 * rule belongs to the requested document type or is the inherited default.
 *
 * @mixin NumberingRule
 */
class NumberingRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'tenantId' => $this->tenant->apiId(),
            'mask' => $this->mask,
            'scopeTokens' => $this->scope_tokens,
            'isOverride' => $this->doc_type_id !== null,
        ];
    }
}
