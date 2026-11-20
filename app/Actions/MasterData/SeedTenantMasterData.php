<?php

namespace App\Actions\MasterData;

use App\Actions\Audit\RecordAuditLog;
use App\Models\MasterData\ContractDocumentType;
use App\Models\Tenant;
use App\Support\MasterData\MasterDataDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gives a company the default master data lists (config/master_data.php).
 * Idempotent: adds only entries whose code and name the company does not have
 * yet, active or not, so a deactivated default is never brought back and
 * nothing the company changed is overwritten. With $dryRun everything is
 * rolled back and only the report is returned. A run that adds something is
 * audited (no actor: it is the system), in the same transaction.
 */
class SeedTenantMasterData
{
    public function __construct(private MasterDataDefaults $defaults, private RecordAuditLog $recordAuditLog) {}

    /**
     * @return array{added: array<string, list<string>>, warnings: list<string>}
     */
    public function handle(Tenant $tenant, bool $dryRun = false): array
    {
        $report = ['added' => [], 'warnings' => []];

        DB::beginTransaction();

        try {
            foreach ($this->defaults->simpleLists() as $list => $definition) {
                $report['added'][$list] = $this->addMissing($tenant, $definition['model'], $definition['rows']);
            }

            $report['added']['contract_document_types'] = $this->addDocumentTypes($tenant, $report['warnings']);

            $added = array_filter($report['added']);

            if (! $dryRun && $added !== []) {
                $this->recordAuditLog->handle(null, 'master_data.defaults.seeded', $tenant, null, ['added' => $added], $tenant->id);
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $report;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<array{code: string, name: string, flags?: array<string, bool>, module?: mixed}>  $rows
     * @return list<string>
     */
    private function addMissing(Tenant $tenant, string $model, array $rows, ?callable $afterInsert = null): array
    {
        $existing = $model::query()->where('tenant_id', $tenant->id)->get();
        $codes = $existing->map(fn (Model $row): string => mb_strtolower($row->getAttribute('code')))->all();
        $names = $existing->map(fn (Model $row): string => mb_strtolower($row->getAttribute('name')))->all();
        $sortOrder = (int) $existing->max('sort_order');
        $added = [];

        foreach ($rows as $row) {
            if (in_array(mb_strtolower($row['code']), $codes, true) || in_array(mb_strtolower($row['name']), $names, true)) {
                continue;
            }

            $flags = array_filter(
                $row['flags'] ?? [],
                fn (bool $value, string $flag): bool => ! $existing->contains($flag, true),
                ARRAY_FILTER_USE_BOTH,
            );

            $record = new $model;
            $record->forceFill([
                'tenant_id' => $tenant->id,
                'code' => $row['code'],
                'name' => $row['name'],
                'sort_order' => ++$sortOrder,
                'is_active' => true,
                'is_system' => true,
                ...(isset($row['module']) ? ['module' => $row['module']] : []),
                ...$flags,
            ])->save();

            if ($afterInsert !== null) {
                $afterInsert($record, $row);
            }

            $codes[] = mb_strtolower($row['code']);
            $names[] = mb_strtolower($row['name']);
            $added[] = $row['name'];
        }

        return $added;
    }

    /**
     * Inserts the missing default document types and links each new one to its
     * categories; a category the company no longer has is reported, not created.
     *
     * @param  list<string>  $warnings
     * @return list<string>
     */
    private function addDocumentTypes(Tenant $tenant, array &$warnings): array
    {
        $categoryIds = $tenant->contractCategories()->pluck('id', 'name')
            ->mapWithKeys(fn (int $id, string $name): array => [mb_strtolower($name) => $id]);

        return $this->addMissing($tenant, ContractDocumentType::class, $this->defaults->documentTypes(),
            function (ContractDocumentType $type, array $row) use ($categoryIds, &$warnings): void {
                $ids = [];

                foreach ($row['categories'] as $category) {
                    $id = $categoryIds->get(mb_strtolower($category));
                    $id !== null ? $ids[] = $id : $warnings[] = "Jenis \"{$row['name']}\": kategori \"{$category}\" tidak ada, tautan dilewati.";
                }

                $type->categories()->attach($ids);
            });
    }
}
