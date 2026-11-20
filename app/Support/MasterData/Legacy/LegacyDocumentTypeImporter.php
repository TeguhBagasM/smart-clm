<?php

namespace App\Support\MasterData\Legacy;

use App\Enums\MasterData\DocumentModule;
use App\Enums\MasterData\MasterList;
use App\Models\MasterData\ContractDocumentType;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Node `masterData.docTypes` into contract document types. Categories are
 * linked by name; a category the company does not have is reported and left
 * out. The extra code segments come along. An "other, type it in" entry is
 * imported hidden. The template and number format of a type are not imported
 * (they belong to modules that are not migrated yet).
 */
class LegacyDocumentTypeImporter
{
    public function __construct(private LegacyLookupImporter $lookups) {}

    /**
     * @param  list<mixed>  $docTypes
     */
    public function import(Tenant $tenant, array $docTypes, ImportReport $report): void
    {
        $categories = $tenant->contractCategories()->pluck('id', 'name')->mapWithKeys(fn (int $id, string $name): array => [mb_strtolower($name) => $id]);
        $other = config()->string('master_data.import.other_pattern');

        foreach ($docTypes as $index => $type) {
            $type = is_array($type) ? $type : [];
            $name = trim((string) ($type['name'] ?? ''));
            $key = trim((string) ($type['id'] ?? '')) ?: 'name:'.mb_strtolower($name);
            $code = mb_strtoupper(trim((string) ($type['code'] ?? '')));

            if ($code !== '' && ! preg_match('/^[A-Z0-9][A-Z0-9._-]{0,19}$/', $code)) {
                $report->error("document-types:{$key}", 'kode tidak valid (huruf, angka, titik, garis; paling banyak 20)');

                continue;
            }

            $hidden = preg_match($other, $name) === 1;
            $retention = (int) ($type['retentionYears'] ?? 0);
            [$row, $outcome] = $this->lookups->upsert($tenant, MasterList::DocumentType, $key, $name, $code === '' ? null : $code, $report, [
                'module' => (DocumentModule::tryFrom((string) ($type['appliesTo'] ?? '')) ?? DocumentModule::Both)->value,
                'retention_years' => $retention > 0 ? $retention : null, 'is_active' => ! $hidden,
            ]);

            if ($row instanceof ContractDocumentType && $outcome === 'created') {
                $row->categories()->sync($this->categoryIds((array) ($type['categories'] ?? []), $categories, $report, $key));
                $this->extraCodes($row, (array) ($type['extraCodes'] ?? []), $report, $key);
                $hidden && $report->warn("document-types:{$key}: entri \"pilihan lain\" diimpor dalam keadaan tersembunyi");
            }
        }
    }

    /**
     * @param  array<mixed>  $names
     * @param  Collection<string, int>  $known
     * @return list<int>
     */
    private function categoryIds(array $names, $known, ImportReport $report, string $key): array
    {
        $ids = [];

        foreach ($names as $name) {
            $id = $known->get(mb_strtolower(trim((string) $name)));
            $id === null ? $report->unknown('kategori di jenis dokumen', trim((string) $name)) : $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<mixed>  $codes
     */
    private function extraCodes(ContractDocumentType $row, array $codes, ImportReport $report, string $key): void
    {
        foreach (array_values($codes) as $position => $code) {
            $label = trim((string) ($code['label'] ?? ''));
            $value = trim((string) ($code['value'] ?? ''));

            if ($label === '' || mb_strlen($label) > 60 || ! preg_match('/^[A-Za-z0-9._-]{1,30}$/', $value)) {
                $report->error("document-types:{$key}", 'kode tambahan #'.($position + 1).' tidak valid, dilewati');

                continue;
            }

            $row->extraCodes()->create(['label' => $label, 'value' => $value, 'sort_order' => $position + 1]);
        }
    }
}
