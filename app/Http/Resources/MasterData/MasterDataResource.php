<?php

namespace App\Http\Resources\MasterData;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * Fields every master data row shows, plus what the user may do with it now
 * (`can`, from the policy) and how many records use it (`usage_count`, loaded
 * by Usage::withCount on the list query). Subclasses add their own fields.
 */
class MasterDataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();
        $user = $request->user();

        return [
            'id' => $this->resource->getKey(),
            'name' => $this->resource->getAttribute('name'),
            ...(array_key_exists('code', $attributes) ? ['code' => $this->resource->getAttribute('code')] : []),
            'sort_order' => $this->resource->getAttribute('sort_order'),
            'is_active' => $this->resource->getAttribute('is_active'),
            ...(array_key_exists('is_system', $attributes) ? ['is_system' => $this->resource->getAttribute('is_system')] : []),
            'usage_count' => (int) ($attributes['usage_count'] ?? 0),
            'can' => [
                'update' => $user !== null && Gate::forUser($user)->allows('update', $this->resource),
                'deactivate' => $user !== null && Gate::forUser($user)->allows('deactivate', $this->resource),
            ],
            ...$this->extra(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extra(): array
    {
        return [];
    }
}
