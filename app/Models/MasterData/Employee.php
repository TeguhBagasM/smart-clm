<?php

namespace App\Models\MasterData;

use App\Models\Dcs\Department;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\MasterData\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An employee of a company, used to prefill employee contracts. Holds no
 * salary and no national id number (audit P2, P3).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $employee_number
 * @property string $name
 * @property string|null $position
 * @property int|null $department_id
 * @property int|null $employment_type_id
 * @property Carbon|null $start_date
 * @property int|null $user_id
 * @property bool $is_active
 * @property string|null $legacy_key
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['employee_number', 'name', 'position', 'start_date'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $table = 'employees';

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The DCS department, read only from here (audit P4).
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<EmploymentType, $this>
     */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['start_date' => 'date', 'is_active' => 'boolean'];
    }
}
