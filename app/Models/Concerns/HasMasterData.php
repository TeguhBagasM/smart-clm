<?php

namespace App\Models\Concerns;

use App\Models\MasterData\ContractCategory;
use App\Models\MasterData\ContractDocumentType;
use App\Models\MasterData\Currency;
use App\Models\MasterData\Employee;
use App\Models\MasterData\EmploymentType;
use App\Models\MasterData\Partner;
use App\Models\MasterData\PartnerGroup;
use App\Models\MasterData\PartyType;
use App\Models\MasterData\TenantPlatform;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The master data lists a company owns. Scoping is explicit through these
 * relations, never a global scope (same rule as DCS).
 *
 * @mixin Tenant
 */
trait HasMasterData
{
    /** @return HasMany<TenantPlatform, $this> */
    public function platforms(): HasMany
    {
        return $this->hasMany(TenantPlatform::class);
    }

    /** @return HasMany<ContractCategory, $this> */
    public function contractCategories(): HasMany
    {
        return $this->hasMany(ContractCategory::class);
    }

    /** @return HasMany<ContractDocumentType, $this> */
    public function contractDocumentTypes(): HasMany
    {
        return $this->hasMany(ContractDocumentType::class);
    }

    /** @return HasMany<EmploymentType, $this> */
    public function employmentTypes(): HasMany
    {
        return $this->hasMany(EmploymentType::class);
    }

    /** @return HasMany<PartyType, $this> */
    public function partyTypes(): HasMany
    {
        return $this->hasMany(PartyType::class);
    }

    /** @return HasMany<Currency, $this> */
    public function currencies(): HasMany
    {
        return $this->hasMany(Currency::class);
    }

    /** @return HasMany<PartnerGroup, $this> */
    public function partnerGroups(): HasMany
    {
        return $this->hasMany(PartnerGroup::class);
    }

    /** @return HasMany<Partner, $this> */
    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
