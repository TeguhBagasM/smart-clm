<?php

namespace App\Support\MasterData\Legacy;

use App\Models\Tenant;

/**
 * Compares the values Node's contracts hold with the company's lists (after
 * the import) and reports each value that is not in them, with how many
 * contracts hold it. Also reports Node departments the DCS list lacks. Nothing
 * is written: contracts are not migrated.
 */
class LegacyUsageCheck
{
    /** @var array<string, string> contract field => where the value should be found */
    private const REFERENCES = [
        'category' => 'categories', 'docType' => 'document types', 'platform' => 'platforms', 'operator' => 'partners',
        'employmentType' => 'employment types', 'division' => 'departments', 'party2Type' => 'party types', 'currency' => 'currencies',
    ];

    /**
     * @param  array<string, array<string, int>>  $contractValues
     * @param  list<string>  $nodeDepartments
     */
    public function check(Tenant $tenant, array $contractValues, array $nodeDepartments, ImportReport $report): void
    {
        $known = $this->known($tenant);

        foreach ($contractValues as $field => $values) {
            foreach ($values as $value => $rows) {
                $lookup = mb_strtolower((string) $value);
                $lookup = $field === 'party2Type' && $lookup === 'employee' ? 'karyawan' : $lookup;

                if (! isset($known[self::REFERENCES[$field] ?? ''][$lookup])) {
                    $report->unknown("kontrak: {$field}", (string) $value, $rows);
                }
            }
        }

        foreach ($nodeDepartments as $department) {
            if (! isset($known['departments'][mb_strtolower($department)])) {
                $report->unknown('daftar departemen Node (belum ada di DCS)', $department);
            }
        }
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function known(Tenant $tenant): array
    {
        $set = fn (iterable $values): array => collect($values)->mapWithKeys(fn (mixed $value): array => [mb_strtolower((string) $value) => true])->all();
        $departments = $tenant->dcsDepartments()->get(['code', 'name']);

        return [
            'categories' => $set($tenant->contractCategories()->pluck('name')),
            'document types' => $set($tenant->contractDocumentTypes()->pluck('name')),
            'platforms' => $set($tenant->platforms()->pluck('name')),
            'partners' => $set($tenant->partners()->pluck('name')),
            'employment types' => $set($tenant->employmentTypes()->pluck('name')),
            'party types' => $set($tenant->partyTypes()->pluck('name')),
            'currencies' => $set($tenant->currencies()->pluck('code')),
            'departments' => $set($departments->pluck('name')) + $set($departments->pluck('code')),
        ];
    }
}
