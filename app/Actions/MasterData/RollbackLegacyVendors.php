<?php

namespace App\Actions\MasterData;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Tenant;
use App\Support\MasterData\Legacy\ImportReport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Takes back what vendors:import-legacy brought into one company: every vendor
 * carrying a Node id is removed, all or nothing. Vendors made by hand stay.
 * Changes made in Laravel to imported vendors are lost with them. If anything
 * made after the import still refers to an imported vendor, nothing changes.
 */
class RollbackLegacyVendors
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(Tenant $tenant, bool $dryRun): ImportReport
    {
        $report = new ImportReport;
        DB::beginTransaction();

        try {
            $removed = $tenant->vendors()->whereNotNull('legacy_key')->delete();

            if ($removed > 0) {
                $report->count('vendors', 'removed', $removed);
            }

            if (! $dryRun && $removed > 0) {
                $this->recordAuditLog->handle(null, 'master_data.vendors.import_rolled_back', $tenant, null, ['counts' => $report->counts], $tenant->id);
            }
        } catch (QueryException) {
            DB::rollBack();
            $report = new ImportReport;
            $report->error('rollback', 'ada data yang dibuat sesudah impor masih memakai vendor hasil impor. Tidak ada yang diubah.');

            return $report;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $report;
    }
}
