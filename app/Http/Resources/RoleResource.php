<?php

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A role as listed and edited on the role management screens. `can` carries
 * the policy decisions so the frontend never re-derives authorization rules.
 *
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'level' => $this->level,
            'is_system' => $this->is_system,
            'is_assignable' => $this->is_assignable,
            'is_super_admin' => $this->is_super_admin,
            'tenant' => $this->whenLoaded('tenant', fn (): ?array => $this->tenant === null ? null : [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
            ]),
            'users_count' => $this->whenCounted('users'),
            'can' => [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
            ],
        ];
    }
}
