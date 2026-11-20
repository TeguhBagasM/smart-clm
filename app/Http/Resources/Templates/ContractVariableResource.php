<?php

namespace App\Http\Resources\Templates;

use App\Models\Templates\ContractVariable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A field of the library ({{Code}}), with the rights of the person looking at it.
 *
 * @property ContractVariable $resource
 */
class ContractVariableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variable = $this->resource;
        $user = $request->user();
        $can = fn (string $ability): bool => $user !== null && Gate::forUser($user)->allows($ability, $variable);

        return [
            'id' => $variable->id,
            'code' => $variable->code,
            'name' => $variable->name,
            'value_type' => ['value' => $variable->value_type->value, 'label' => $variable->value_type->label()],
            'description' => $variable->description,
            'default_value' => $variable->default_value,
            'source_field' => $variable->source_field,
            'is_active' => $variable->is_active,
            'usage_count' => (int) ($variable->getAttribute('usage_count') ?? 0),
            'can' => ['update' => $can('update'), 'deactivate' => $can('deactivate')],
        ];
    }
}
