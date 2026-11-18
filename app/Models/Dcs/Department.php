<?php

namespace App\Models\Dcs;

use App\Models\Concerns\HasUuid;
use App\Models\Tenant;
use Database\Factories\Dcs\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory, HasUuid;

    /** Letters, digits, space and _ . & - : the code is printed in document numbers, so no "/". */
    public const CODE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9 _.&-]*$/';

    protected $table = 'dcs_departments';

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
