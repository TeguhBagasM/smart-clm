<?php

namespace App\Actions\Templates;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Tenant;
use App\Support\MasterData\Legacy\ImportReport;
use App\Support\Templates\Legacy\LegacyClauseImporter;
use App\Support\Templates\Legacy\LegacyLibraryReader;
use App\Support\Templates\Legacy\LegacyTemplateImporter;
use App\Support\Templates\Legacy\LegacyVariableImporter;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Copies one Node company's library (fields, clauses, templates) into the
 * Laravel company whose tenants.legacy_id it names: all or nothing in one
 * transaction, safe to run again (rows are matched by the Node key), read only
 * on the Node side. With $dryRun everything runs and is rolled back, so the report
 * is exact. A run that is not clean is rolled back too, so nothing half-imported
 * is left behind.
 */
class ImportLegacyTemplateLibrary
{
    public function __construct(
        private LegacyLibraryReader $reader,
        private LegacyVariableImporter $variables,
        private LegacyClauseImporter $clauses,
        private LegacyTemplateImporter $templates,
        private RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(Tenant $tenant, bool $dryRun): ImportReport
    {
        $report = new ImportReport;
        $nodeId = $tenant->legacy_id;

        if ($nodeId === null || ! $this->reader->tenantExists($nodeId)) {
            $report->error('tenant', $nodeId === null ? 'perusahaan ini belum punya legacy_id (id Node)' : "Node tidak punya tenant \"{$nodeId}\"");

            return $report;
        }

        DB::beginTransaction();

        try {
            $this->variables->import($tenant, $this->reader->variables($nodeId), $report);
            $clauses = $this->clauses->import($tenant, $this->reader->clauses($nodeId), $report);
            $this->templates->import($tenant, $this->reader->templates($nodeId), $clauses, $report);

            if (! $dryRun && $report->isClean() && $report->total('created') + $report->total('adopted') > 0) {
                $this->recordAuditLog->handle(null, 'contract_templates.import.imported', $tenant, null, ['counts' => $report->counts], $tenant->id);
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun || ! $report->isClean() ? DB::rollBack() : DB::commit();

        return $report;
    }
}
