<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One request to a language model: which model, how long, how many tokens. Append-only.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $comparison_id
 * @property string $stage
 * @property string $provider
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $duration_ms
 * @property bool $ok
 * @property string|null $error
 * @property Carbon $created_at
 */
class AiCall extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ok' => 'boolean'];
    }
}
