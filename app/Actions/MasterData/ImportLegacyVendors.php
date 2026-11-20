<?php

namespace App\Actions\MasterData;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Tenant;
use App\Support\MasterData\Legacy\ImportReport;
use App\Support\MasterData\Legacy\LegacyReader;
use App\Support\MasterData\Legacy\LegacyVendorImporter;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Copies one Node tenant's vendors into the Laravel company carrying its id in
 * tenants.legacy_id: all or nothing (one transaction), safe to run again (rows
 * are matched by the Node id), read only on the Node side, mock rows of the
 * Node code left out. With $dryRun everything runs and is rolled back, so the
 * report is exact. A run with errors is rolled back too: nothing half-imported.
 */
class ImportLegacyVendors
{
    public function __construct(private LegacyReader $reader, private LegacyVendorImporter $vendors, private RecordAuditLog $recordAuditLog) {}

    public function handle(Tenant $tenant, bool $dryRun): ImportReport
    {
        $report = new ImportReport;
        $nodeId = $tenant->legacy_id;

        if ($nodeId === null || $this->reader->tenant($nodeId) === null) {
            $report->error('tenant', $nodeId === null ? 'perusahaan ini belum punya legacy_id (id Node)' : "Node tidak punya tenant \"{$nodeId}\"");

            return $report;
        }

        DB::beginTransaction();

        try {
            $this->vendors->import($tenant, $this->reader->vendors($nodeId), $report);

            if (! $dryRun && $report->errors === [] && $report->total('created') > 0) {
                $this->recordAuditLog->handle(null, 'master_data.vendors.imported', $tenant, null, ['counts' => $report->counts], $tenant->id);
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun || $report->errors !== [] ? DB::rollBack() : DB::commit();

        return $report;
    }
}
