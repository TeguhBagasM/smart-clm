<?php

namespace App\Http\Resources;

use App\Models\Dcs\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user as shared with every Inertia page. Only whitelisted
 * fields are exposed so new columns never leak to the browser by accident.
 * `can` holds the abilities that decide which menus and buttons appear.
 *
 * @mixin User
 */
class AuthUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, name: string, email: string, role: array<string, mixed>, tenant: array{id: int, name: string}|null, two_factor_enabled: bool, can: array{create_documents: bool, configure_documents: bool, view_task_queue: bool}}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => RoleSummaryResource::make($this->role)->resolve($request),
            'tenant' => $this->tenant === null ? null : [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
            ],
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'can' => [
                'create_documents' => $this->resource->can('create', Document::class),
                'configure_documents' => $this->resource->can('configure', Document::class),
                'view_task_queue' => $this->resource->can('viewQueue', Document::class),
            ],
        ];
    }
}
