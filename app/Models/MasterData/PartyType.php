<?php

namespace App\Models\MasterData;

use App\Models\Concerns\IsTenantLookup;
use Database\Factories\MasterData\PartyTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A kind of second party on a contract. The one with is_employee replaces Node's literal "Karyawan".
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property bool $is_employee
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
class PartyType extends Model
{
    /** @use HasFactory<PartyTypeFactory> */
    use HasFactory, IsTenantLookup;

    protected $table = 'party_types';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean', 'is_system' => 'boolean', 'is_employee' => 'boolean'];
    }
}
