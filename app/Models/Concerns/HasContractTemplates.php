<?php

namespace App\Models\Concerns;

use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\ContractVariable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The clause library, fields and templates a company owns (Klausul & Template).
 * Scoping is explicit through these relations, never a global scope.
 *
 * @mixin Tenant
 */
trait HasContractTemplates
{
    /** @return HasMany<ContractClause, $this> */
    public function contractClauses(): HasMany
    {
        return $this->hasMany(ContractClause::class);
    }

    /** @return HasMany<ContractVariable, $this> */
    public function contractVariables(): HasMany
    {
        return $this->hasMany(ContractVariable::class);
    }

    /** @return HasMany<ContractTemplate, $this> */
    public function contractTemplates(): HasMany
    {
        return $this->hasMany(ContractTemplate::class);
    }
}
