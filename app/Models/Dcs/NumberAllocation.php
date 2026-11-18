<?php

namespace App\Models\Dcs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A number handed out of a sequence. While `document_id` is set the number
 * belongs to that document; a released number (released_at set, no document)
 * waits in the pool and is given out again before the counter moves on.
 *
 * @property int $id
 * @property int $sequence_id
 * @property int $seq
 * @property int|null $document_id
 * @property Carbon|null $released_at
 */
class NumberAllocation extends Model
{
    protected $table = 'dcs_number_allocations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['seq' => 'integer', 'released_at' => 'datetime'];
    }
}
