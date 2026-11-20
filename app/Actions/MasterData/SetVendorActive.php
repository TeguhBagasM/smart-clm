<?php

namespace App\Actions\MasterData;

use App\Actions\Audit\RecordAuditLog;
use App\Models\MasterData\Vendor;
use App\Models\User;
use App\Support\MasterData\Usage;
use App\Support\MasterData\VendorAuditSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Hides a vendor from new choices, or brings it back. Nothing is deleted:
 * contracts keep their copy of the vendor. Returns how many records use it
 * (0 until contracts are migrated, see `vendors.usage_covers_contracts`) and
 * whether anything changed.
 */
class SetVendorActive
{
    public function __construct(private RecordAuditLog $recordAuditLog, private VendorAuditSnapshot $snapshot, private Usage $usage) {}

    /**
     * @return array{usage: int, changed: bool}
     */
    public function handle(User $actor, Vendor $vendor, bool $active): array
    {
        $usage = $this->usage->count($vendor);

        if ($vendor->is_active === $active) {
            return ['usage' => $usage, 'changed' => false];
        }

        DB::transaction(function () use ($actor, $vendor, $active): void {
            $before = $this->snapshot->of($vendor);
            $vendor->forceFill(['is_active' => $active, 'updated_by_id' => $actor->id])->save();
            $this->recordAuditLog->handle($actor, 'master_data.vendors.'.($active ? 'reactivated' : 'deactivated'), $vendor, $before, $this->snapshot->of($vendor), $vendor->tenant_id);
        });

        return ['usage' => $usage, 'changed' => true];
    }
}
