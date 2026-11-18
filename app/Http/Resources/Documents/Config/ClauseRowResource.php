<?php

namespace App\Http\Resources\Documents\Config;

use App\Models\Dcs\Clause;
use App\Support\Dcs\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A clause as a row of the library list. Loads `documentTypes`.
 *
 * @mixin Clause
 */
class ClauseRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'excerpt' => Str::limit($this->content, 140),
            'category' => $this->category,
            'is_mandatory' => $this->is_mandatory,
            'tags' => $this->tags,
            'types' => $this->documentTypes->pluck('code')->all(),
            'is_active' => $this->is_active,
            'updated_at' => ApiDate::format($this->updated_at),
        ];
    }
}
