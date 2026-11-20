<?php

namespace App\Support\MasterData;

use App\Models\MasterData\Vendor;

/**
 * What the audit trail keeps of a vendor. Contact, tax and bank values never
 * go into `audit_logs`: only the plain columns, and the names of the sensitive
 * ones that changed (audit vendor-template Q-V3).
 */
class VendorAuditSnapshot
{
    private const PLAIN = ['id', 'tenant_id', 'name', 'address', 'is_active', 'legacy_key'];

    /**
     * @param  list<string>  $changedSensitive
     * @return array<string, mixed>
     */
    public function of(Vendor $vendor, array $changedSensitive = []): array
    {
        $values = array_intersect_key($vendor->attributesToArray(), array_flip(self::PLAIN));

        return $changedSensitive === [] ? $values : $values + ['changed_sensitive' => $changedSensitive];
    }

    /**
     * The sensitive columns an unsaved change touches (call it before save()).
     *
     * @return list<string>
     */
    public function dirtySensitive(Vendor $vendor): array
    {
        return array_values(array_intersect(Vendor::SENSITIVE, array_keys($vendor->getDirty())));
    }
}
