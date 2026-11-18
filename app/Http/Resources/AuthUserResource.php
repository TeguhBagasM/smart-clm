<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user as shared with every Inertia page. Only whitelisted
 * fields are exposed so new columns never leak to the browser by accident.
 *
 * @mixin User
 */
class AuthUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, name: string, email: string, role: array{value: string, label: string}, tenant: array{id: int, name: string}|null, two_factor_enabled: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => [
                'value' => $this->role->value,
                'label' => $this->role->label(),
            ],
            'tenant' => $this->tenant === null ? null : [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
            ],
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
        ];
    }
}
