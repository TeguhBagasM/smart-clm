<?php

namespace App\Http\Resources\Dcs;

use App\Support\Dcs\DraftCompleteness;
use Illuminate\Http\Request;

/**
 * A draft for the wizard: the compose shape plus the revision to send back on
 * the next save and which wizard steps are filled in.
 */
class DraftResource extends ComposeResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'revision' => $this->content->draft_revision ?? 0,
            'status' => $this->status->value,
            'steps' => DraftCompleteness::of($this->resource),
        ];
    }
}
