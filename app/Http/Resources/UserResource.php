<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user as listed on the user management screens. `can` carries the
 * policy decisions so the frontend never re-implements authorization rules.
 *
 * @mixin User
 */
class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'role' => RoleSummaryResource::make($this->role)->resolve($request),
            'tenant' => $this->whenLoaded('tenant', fn (): ?array => $this->tenant === null ? null : [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
            ]),
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'can' => [
                'update' => $request->user()->can('update', $this->resource),
            ],
        ];
    }
}
