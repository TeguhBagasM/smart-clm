<?php

namespace App\Models\Dcs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The counter of one numbering scope (rule + scope hash, e.g. "SOP|HRD|2026").
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $rule_id
 * @property string $scope_hash
 * @property array<string, string|int> $scope_values
 * @property int $current_seq
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NumberSequence extends Model
{
    protected $table = 'dcs_number_sequences';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scope_values' => 'array', 'current_seq' => 'integer'];
    }
}
