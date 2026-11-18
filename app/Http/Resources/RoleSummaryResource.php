<?php

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The few role fields shown next to a user.
 *
 * @mixin Role
 */
class RoleSummaryResource extends JsonResource
{
    /**
     * @return array{id: int, key: string, name: string, level: int, is_system: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'level' => $this->level,
            'is_system' => $this->is_system,
        ];
    }
}
