<?php

namespace App\Actions\MasterData;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\MasterData\MasterList;
use App\Models\Tenant;
use App\Support\MasterData\Legacy\ImportReport;
use App\Support\MasterData\Legacy\LegacyDocumentTypeImporter;
use App\Support\MasterData\Legacy\LegacyEmployeeImporter;
use App\Support\MasterData\Legacy\LegacyLookupImporter;
use App\Support\MasterData\Legacy\LegacyPartnerImporter;
use App\Support\MasterData\Legacy\LegacyReader;
use App\Support\MasterData\Legacy\LegacyUsageCheck;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Copies one Node tenant's master data into the Laravel company that carries
 * its id in tenants.legacy_id: all or nothing (one transaction), safe to run
 * again (rows are matched by their Node key), read only on the Node side. With
 * $dryRun the whole run happens and is rolled back, so the report is exact.
 * Salary, national ids, bank/contact details and the demo rows of the Node
 * code are never imported.
 */
class ImportLegacyMasterData
{
    public function __construct(
        private LegacyReader $reader,
        private LegacyLookupImporter $lookups,
        private LegacyDocumentTypeImporter $documentTypes,
        private LegacyPartnerImporter $partners,
        private LegacyEmployeeImporter $employees,
        private LegacyUsageCheck $usage,
        private RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(Tenant $tenant, bool $dryRun): ImportReport
    {
        $report = new ImportReport;
        $nodeId = $tenant->legacy_id;
        $node = $nodeId === null ? null : $this->reader->tenant($nodeId);

        if ($nodeId === null || $node === null) {
            $report->error('tenant', $nodeId === null ? 'perusahaan ini belum punya legacy_id (id Node)' : "Node tidak punya tenant \"{$nodeId}\"");

            return $report;
        }

        DB::beginTransaction();

        try {
            $master = $this->reader->masterData($nodeId);

            foreach ($node['platforms'] as $platform) {
                $this->lookups->upsert($tenant, MasterList::Platform, $platform, $platform, null, $report);
            }

            $this->categories($tenant, $this->reader->categories($nodeId), $report);
            $this->simple($tenant, MasterList::EmploymentType, (array) ($master['employmentTypes'] ?? []), $report);
            $this->partyTypes($tenant, (array) ($master['partyTypes'] ?? []), $report);
            $this->currencies($tenant, (array) ($master['currencies'] ?? []), $report);
            $this->documentTypes->import($tenant, (array) ($master['docTypes'] ?? []), $report);
            $this->partners->import($tenant, (array) ($master['operatorGroups'] ?? []), $report);
            $this->employees->import($tenant, $this->reader->employees($nodeId), $report);
            $this->usage->check($tenant, $this->reader->contractValues($nodeId), array_map(fn (mixed $d): string => trim((string) $d), (array) ($master['departments'] ?? [])), $report);

            if (! $dryRun && ($report->total('created') + $report->total('adopted')) > 0) {
                $this->recordAuditLog->handle(null, 'master_data.import.imported', $tenant, null, ['counts' => $report->counts, 'unknown_values' => count($report->unknown, COUNT_RECURSIVE), 'errors' => count($report->errors)], $tenant->id);
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $report;
    }

    /**
     * @param  list<mixed>  $names
     */
    private function simple(Tenant $tenant, MasterList $list, array $names, ImportReport $report): void
    {
        foreach ($names as $name) {
            $this->lookups->upsert($tenant, $list, trim((string) $name), (string) $name, null, $report);
        }
    }

    /**
     * @param  list<string>  $names
     */
    private function categories(Tenant $tenant, array $names, ImportReport $report): void
    {
        $employee = mb_strtolower(config()->string('master_data.defaults.employee_category'));

        foreach ($names as $name) {
            $marks = mb_strtolower(trim($name)) === $employee && ! $tenant->contractCategories()->where('is_employee_key', true)->exists();
            $this->lookups->upsert($tenant, MasterList::Category, trim($name), $name, null, $report, $marks ? ['is_employee_key' => true] : []);
        }
    }

    /**
     * @param  list<mixed>  $names
     */
    private function partyTypes(Tenant $tenant, array $names, ImportReport $report): void
    {
        $employee = mb_strtolower(config()->string('master_data.defaults.employee_party_type'));

        foreach ($names as $name) {
            $marks = in_array(mb_strtolower(trim((string) $name)), [$employee, 'employee'], true) && ! $tenant->partyTypes()->where('is_employee', true)->exists();
            $this->lookups->upsert($tenant, MasterList::PartyType, trim((string) $name), (string) $name, null, $report, $marks ? ['is_employee' => true] : []);
        }
    }

    /**
     * @param  list<mixed>  $codes
     */
    private function currencies(Tenant $tenant, array $codes, ImportReport $report): void
    {
        foreach ($codes as $value) {
            $code = mb_strtoupper(trim((string) $value));

            if (! preg_match('/^[A-Z]{3}$/', $code)) {
                $report->error("currencies:{$value}", 'bukan kode mata uang 3 huruf');
                $report->count('currencies', 'skipped');

                continue;
            }

            $this->lookups->upsert($tenant, MasterList::Currency, $code, $code, $code, $report);
        }
    }
}
