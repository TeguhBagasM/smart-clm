<?php

namespace App\Support\Templates\Legacy;

use App\Enums\Templates\VariableType;
use App\Models\Templates\ContractVariable;
use App\Models\Tenant;
use App\Support\MasterData\Legacy\ImportReport;
use App\Support\Templates\Placeholders;
use App\Support\Templates\UniqueLibraryName;

/**
 * Node fields into the company's fields. Matched by the Node key (legacy_key).
 * A seeded default with the same code is adopted (it only receives the key); a
 * hand-made field with the same code is an error, not overwritten.
 */
class LegacyVariableImporter
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function import(Tenant $tenant, array $rows, ImportReport $report): void
    {
        $sources = [];

        foreach (config()->array('contract_templates.defaults.variables') as $default) {
            $sources[$default['code']] = $default['source'] ?? null;
        }

        $order = (int) ContractVariable::query()->where('tenant_id', $tenant->id)->max('sort_order');

        foreach ($rows as $row) {
            $code = (string) $row['id'];
            $ref = "variables:{$code}";

            if (preg_match('/\A'.Placeholders::CODE.'\z/', $code) !== 1) {
                $report->error($ref, 'kode tidak sesuai aturan kolom isian (satu kata, huruf dan angka, diawali huruf)');
                $report->count('variables', 'skipped');

                continue;
            }

            if (ContractVariable::query()->where('tenant_id', $tenant->id)->where('legacy_key', $code)->exists()) {
                $report->count('variables', 'unchanged');

                continue;
            }

            $existing = ContractVariable::query()->where('tenant_id', $tenant->id)->whereRaw('lower(code) = ?', [mb_strtolower($code)])->first();

            if ($existing !== null) {
                if ($existing->is_system && $existing->legacy_key === null) {
                    $existing->forceFill(['legacy_key' => $code])->save();
                    $report->count('variables', 'adopted');
                } else {
                    $report->error($ref, 'kode ini sudah dipakai kolom isian lain di perusahaan ini');
                    $report->count('variables', 'skipped');
                }

                continue;
            }

            $name = trim((string) ($row['label'] ?? '')) ?: $code;

            if (UniqueLibraryName::taken(ContractVariable::class, $tenant->id, 'name', $name, null)) {
                $report->error($ref, 'nama kolom isian ini sudah dipakai kolom lain di perusahaan ini');
                $report->count('variables', 'skipped');

                continue;
            }

            (new ContractVariable)->forceFill([
                'tenant_id' => $tenant->id, 'code' => $code, 'name' => $name,
                'value_type' => VariableType::fromNode(is_string($row['type'] ?? null) ? $row['type'] : null),
                'description' => $this->text($row['description'] ?? null), 'default_value' => $this->text($row['defaultValue'] ?? null),
                'source_field' => $sources[$code] ?? null, 'sort_order' => ++$order, 'is_active' => true, 'is_system' => false, 'legacy_key' => $code,
            ])->save();
            $report->count('variables', 'created');
        }
    }

    private function text(mixed $value): ?string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return $text === '' ? null : $text;
    }
}
