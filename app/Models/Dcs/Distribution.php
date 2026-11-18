<?php

namespace App\Models\Dcs;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One act of sending a version to a set of users; each recipient gets a read receipt.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int $version_id
 * @property int $recipient_count
 * @property bool $is_mandatory
 * @property int $distributed_by_id
 * @property Carbon $distributed_at
 */
class Distribution extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $table = 'dcs_distributions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean', 'distributed_at' => 'datetime'];
    }
}
