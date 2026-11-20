<?php

namespace App\Models\MasterData;

use App\Models\Concerns\IsTenantLookup;
use Database\Factories\MasterData\EmploymentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An employment relationship offered on employee contracts (PKWT, PKWTT, ...).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property string|null $legacy_key
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'sort_order'])]
class EmploymentType extends Model
{
    /** @use HasFactory<EmploymentTypeFactory> */
    use HasFactory, IsTenantLookup;

    protected $table = 'employment_types';

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean', 'is_system' => 'boolean'];
    }
}
