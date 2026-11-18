<?php

namespace App\Support\Dcs;

use App\Models\Tenant;
use LogicException;

/**
 * The tenant a DCS request works in, set by the SetDcsTenant middleware. Reading
 * it before then is a programming error, so it throws instead of returning null.
 */
class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): Tenant
    {
        return $this->tenant ?? throw new LogicException('No DCS tenant has been resolved for this request.');
    }
}
