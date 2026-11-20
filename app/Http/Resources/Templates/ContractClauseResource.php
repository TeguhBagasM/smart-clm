<?php

namespace App\Http\Resources\Templates;

use App\Models\Templates\ContractClause;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A clause of the library, with the rights of the person looking at it.
 * `usage_count` comes from withCount('copies as usage_count') when a list loads it.
 *
 * @property ContractClause $resource
 */
class ContractClauseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $clause = $this->resource;
        $user = $request->user();
        $can = fn (string $ability): bool => $user !== null && Gate::forUser($user)->allows($ability, $clause);

        return [
            'id' => $clause->id,
            'title' => $clause->title,
            'body' => $clause->body,
            'category' => $clause->relationLoaded('category') && $clause->category !== null ? ['id' => $clause->category->id, 'name' => $clause->category->name] : null,
            'is_mandatory' => $clause->is_mandatory,
            'is_protected' => $clause->is_protected,
            'revision' => $clause->revision,
            'is_active' => $clause->is_active,
            'usage_count' => (int) ($clause->getAttribute('usage_count') ?? 0),
            'can' => ['update' => $can('update'), 'deactivate' => $can('deactivate')],
        ];
    }
}
