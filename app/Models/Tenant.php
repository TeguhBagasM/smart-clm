<?php

namespace App\Models;

use App\Models\Concerns\HasMasterData;
use App\Models\Dcs\Clause;
use App\Models\Dcs\Department;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Models\Dcs\TenantSetting;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $legacy_id
 * @property string $name
 * @property string $code
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'code'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasMasterData;

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The id the legacy API reports for this tenant: the Node id when the
     * tenant was imported, otherwise the Laravel id.
     */
    public function apiId(): string
    {
        return $this->legacy_id ?? (string) $this->id;
    }

    /**
     * @return HasMany<DocumentType, $this>
     */
    public function dcsDocumentTypes(): HasMany
    {
        return $this->hasMany(DocumentType::class);
    }

    /**
     * @return HasMany<Clause, $this>
     */
    public function dcsClauses(): HasMany
    {
        return $this->hasMany(Clause::class);
    }

    /**
     * @return HasOne<TenantSetting, $this>
     */
    public function dcsSettings(): HasOne
    {
        return $this->hasOne(TenantSetting::class);
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function dcsDepartments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * @return HasMany<NumberingRule, $this>
     */
    public function dcsNumberingRules(): HasMany
    {
        return $this->hasMany(NumberingRule::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
