<?php

namespace App\Support\Templates\Legacy;

use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractVariable;
use App\Models\Tenant;
use App\Support\MasterData\Legacy\ImportReport;
use App\Support\Templates\PlaceholderGuard;
use App\Support\Templates\Placeholders;
use App\Support\Templates\UniqueLibraryName;

/**
 * Node clauses into the library, matched by the Node id (legacy_key): a clause
 * already imported is left as it is. Errors (no title, unknown category, a title
 * a hand-made clause already uses, leftover HTML, a malformed placeholder) skip
 * the row and make the run unclean. A placeholder that is not a field of the
 * company is reported as unknown. Returns the clauses by Node id, for templates.
 */
class LegacyClauseImporter
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, ContractClause>
     */
    public function import(Tenant $tenant, array $rows, ImportReport $report): array
    {
        $known = PlaceholderGuard::known($this->companyCodes($tenant));
        $limit = config()->integer('contract_templates.limits.clause_body');
        $byNodeId = [];

        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $ref = "clauses:{$id}";
            $existing = ContractClause::query()->where('tenant_id', $tenant->id)->where('legacy_key', $id)->first();

            if ($existing !== null) {
                $byNodeId[$id] = $existing;
                $report->count('clauses', 'unchanged');

                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $category = ContractCategory::query()->where('tenant_id', $tenant->id)->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) ($row['category'] ?? '')))])->first();
            [$body, $stripped] = NodeText::plain((string) ($row['content'] ?? ''));
            $problems = [];

            if ($title === '' || mb_strlen($title) > 200) {
                $problems[] = 'judul kosong atau lebih dari 200 karakter';
            }

            if ($category === null) {
                $problems[] = 'kategori "'.($row['category'] ?? '').'" tidak ada di perusahaan ini; impor master data dulu';
            }

            if ($title !== '' && UniqueLibraryName::taken(ContractClause::class, $tenant->id, 'title', $title, null)) {
                $problems[] = 'judul ini sudah dipakai pasal lain; ganti dulu di Node atau di Laravel';
            }

            if ($body === '' || mb_strlen($body) > $limit) {
                $problems[] = 'isi kosong atau lebih dari '.$limit.' karakter';
            }

            if (NodeText::hasTags($body)) {
                $problems[] = 'isi masih memuat tanda HTML setelah dibersihkan';
            }

            foreach (Placeholders::invalidTokens($body) as $token) {
                $problems[] = "tanda {$token} tidak dikenali";
            }

            if (Placeholders::hasStrayBraces($body)) {
                $problems[] = 'kurung kurawal ganda tidak lengkap';
            }

            foreach ($problems as $problem) {
                $report->error($ref, $problem);
            }

            foreach (array_diff(Placeholders::codes($body), $known) as $code) {
                $report->unknown('pasal', '{{'.$code.'}}');
            }

            if ($stripped) {
                $report->warn("Isi pasal \"{$title}\" dibersihkan dari format; tebal, warna, tabel, dan gambar tidak dibawa.");
            }

            if ($problems !== []) {
                $report->count('clauses', 'skipped');

                continue;
            }

            $clause = (new ContractClause)->forceFill([
                'tenant_id' => $tenant->id, 'contract_category_id' => $category?->id, 'title' => $title, 'body' => $body,
                'is_mandatory' => (bool) ($row['isMandatory'] ?? false), 'is_protected' => (bool) ($row['isProtected'] ?? false),
                'revision' => max(1, (int) ($row['version'] ?? 1)), 'is_active' => true, 'is_system' => false, 'legacy_key' => $id,
            ]);
            $clause->save();
            $byNodeId[$id] = $clause;
            $report->count('clauses', 'created');
        }

        return $byNodeId;
    }

    /**
     * @return list<string>
     */
    private function companyCodes(Tenant $tenant): array
    {
        $codes = [];

        foreach (ContractVariable::query()->where('tenant_id', $tenant->id)->where('is_active', true)->get() as $variable) {
            $codes[] = $variable->code;
        }

        return $codes;
    }
}
