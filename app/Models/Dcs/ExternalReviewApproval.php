<?php

namespace App\Models\Dcs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An outside party's "OK" on a version. A guest sign-off note, not a step of
 * the internal signing flow.
 *
 * @property int $id
 * @property int $external_review_id
 * @property string $name
 * @property string|null $note
 * @property Carbon $approved_at
 */
class ExternalReviewApproval extends Model
{
    public $timestamps = false;

    protected $table = 'dcs_external_review_approvals';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }
}
