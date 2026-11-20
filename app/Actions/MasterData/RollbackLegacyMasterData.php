<?php

namespace App\Actions\MasterData;

use App\Actions\Audit\RecordAuditLog;
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
use App\Support\MasterData\Legacy\ImportReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Takes back what master-data:import-legacy brought into one company: rows
 * carrying a Node key are removed, except a seeded default that the import only
 * adopted, which just loses the key. All or nothing. If something made after the
 * import (a hand-made partner in an imported group, an employee on an imported
 * employment type) still refers to a row, nothing is changed and the report says
 * so. Changes made by hand to imported rows are lost with them.
 */
class RollbackLegacyMasterData
{
    /** @var list<class-string<Model>> dependants first */
    private const ORDER = [
        Employee::class, Partner::class, PartnerGroup::class, ContractDocumentType::class,
        PartyType::class, EmploymentType::class, Currency::class, ContractCategory::class, TenantPlatform::class,
    ];

    /** Tables without seeded defaults: every keyed row came from the import. */
    private const WITHOUT_DEFAULTS = [Employee::class, Partner::class, PartnerGroup::class];

    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(Tenant $tenant, bool $dryRun): ImportReport
    {
        $report = new ImportReport;
        DB::beginTransaction();

        try {
            foreach (self::ORDER as $model) {
                $table = (new $model)->getTable();
                $rows = fn () => $model::query()->where('tenant_id', $tenant->id)->whereNotNull('legacy_key');

                if (! in_array($model, self::WITHOUT_DEFAULTS, true)) {
                    $this->count($report, $table, 'released', $rows()->where('is_system', true)->update(['legacy_key' => null]));
                }

                $this->count($report, $table, 'removed', $rows()->delete());
            }

            if (! $dryRun && ($report->total('removed') + $report->total('released')) > 0) {
                $this->recordAuditLog->handle(null, 'master_data.import.rolled_back', $tenant, null, ['counts' => $report->counts], $tenant->id);
            }
        } catch (QueryException) {
            DB::rollBack();
            $report = new ImportReport;
            $report->error('rollback', 'ada data yang dibuat sesudah impor masih memakai baris hasil impor. Tidak ada yang diubah.');

            return $report;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $report;
    }

    private function count(ImportReport $report, string $table, string $outcome, int $rows): void
    {
        if ($rows > 0) {
            $report->count($table, $outcome, $rows);
        }
    }
}
