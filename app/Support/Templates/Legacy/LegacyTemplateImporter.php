<?php

namespace App\Support\Templates\Legacy;

use App\Enums\MasterData\DocumentModule;
use App\Enums\Templates\TemplateVersionStatus;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\ContractVariable;
use App\Models\Templates\TemplateVersion;
use App\Models\Tenant;
use App\Support\MasterData\Legacy\ImportReport;
use App\Support\Templates\Placeholders;
use App\Support\Templates\UniqueLibraryName;
use Illuminate\Support\Carbon;

/**
 * Node templates into the library. A template in use at Node becomes a
 * published version 1, with copies of its clauses, parties and fields (Node
 * keeps no history per version; a later Node version number is reported). A
 * template already imported, or a seeded default with the same Node key, is left
 * as it is; a seeded default still in draft is reported to publish by hand.
 */
class LegacyTemplateImporter
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, ContractClause>  $clauses  imported clauses by Node id
     */
    public function import(Tenant $tenant, array $rows, array $clauses, ImportReport $report): void
    {
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $ref = "templates:{$id}";
            $existing = ContractTemplate::query()->where('tenant_id', $tenant->id)->where('legacy_key', $id)->first();

            if ($existing !== null) {
                if ($existing->is_system && $existing->draft()->exists()) {
                    $report->warn("Templat \"{$existing->name}\" masih draf bawaan; terbitkan di Laravel setelah diperiksa.");
                }

                $report->count('templates', 'unchanged');

                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $category = ContractCategory::query()->where('tenant_id', $tenant->id)->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) ($row['category'] ?? '')))])->first();
            $problems = $this->check($tenant, $row, $name, $category, $clauses, $report, $ref);

            if ($problems !== []) {
                foreach ($problems as $problem) {
                    $report->error($ref, $problem);
                }

                $report->count('templates', 'skipped');

                continue;
            }

            $this->create($tenant, $row, $name, $category, $clauses, $id);
            $report->count('templates', 'created');

            if ((int) ($row['version'] ?? 1) > 1) {
                $report->warn("Templat \"{$name}\" punya versi {$row['version']} di Node; yang dibawa hanya isi terakhir, sebagai versi 1.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, ContractClause>  $clauses
     * @return list<string>
     */
    private function check(Tenant $tenant, array $row, string $name, ?ContractCategory $category, array $clauses, ImportReport $report, string $ref): array
    {
        $problems = [];

        if ($name === '' || mb_strlen($name) > 200) {
            $problems[] = 'nama kosong atau lebih dari 200 karakter';
        } elseif (UniqueLibraryName::taken(ContractTemplate::class, $tenant->id, 'name', $name, null)) {
            $problems[] = 'nama templat ini sudah dipakai templat lain di perusahaan ini';
        }

        if ($category === null) {
            $problems[] = 'kategori "'.($row['category'] ?? '').'" tidak ada di perusahaan ini; impor master data dulu';
        }

        foreach ((array) ($row['clauseIds'] ?? []) as $clauseId) {
            if (! isset($clauses[(string) $clauseId])) {
                $problems[] = "pasal \"{$clauseId}\" tidak ada di perpustakaan (tidak diimpor atau tidak ditemukan)";
            }
        }

        foreach ((array) ($row['requiredVariables'] ?? []) as $code) {
            if (! ContractVariable::query()->where('tenant_id', $tenant->id)->where('code', (string) $code)->exists()) {
                $problems[] = "kolom isian \"{$code}\" tidak ada di perusahaan ini";
            }
        }

        if ($this->parties($row) === []) {
            $problems[] = 'tidak ada pihak yang diisi';
        }

        $allowed = [...array_map(strval(...), (array) ($row['requiredVariables'] ?? [])), ...array_keys(config()->array('contract_templates.system_tokens'))];
        $texts = [$row['openingParagraph'] ?? '', $row['closingParagraph'] ?? '', $row['addendumRecitalParagraph'] ?? ''];

        foreach ($texts as $text) {
            [$plain] = NodeText::plain((string) $text);

            if (NodeText::hasTags($plain) || Placeholders::invalidTokens($plain) !== [] || Placeholders::hasStrayBraces($plain)) {
                $problems[] = 'kalimat pembuka, penutup, atau adendum memuat HTML atau tanda kurung kurawal yang rusak';
            }

            foreach (array_diff(Placeholders::codes($plain), $allowed) as $code) {
                $report->unknown('templat', '{{'.$code.'}}');
            }
        }

        return $problems;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, ContractClause>  $clauses
     */
    private function create(Tenant $tenant, array $row, string $name, ContractCategory $category, array $clauses, string $id): void
    {
        $template = (new ContractTemplate)->forceFill([
            'tenant_id' => $tenant->id, 'contract_category_id' => $category->id, 'module' => $category->is_employee_key ? DocumentModule::Employee : DocumentModule::External,
            'name' => $name, 'description' => $this->text($row['description'] ?? null), 'is_active' => true, 'is_system' => false, 'legacy_key' => $id,
        ]);
        $template->save();

        $version = (new TemplateVersion)->forceFill(['contract_template_id' => $template->id, 'version_no' => 1, 'status' => TemplateVersionStatus::Draft, 'draft_revision' => 1,
            'opening_paragraph' => $this->plain($row['openingParagraph'] ?? null), 'closing_paragraph' => $this->plain($row['closingParagraph'] ?? null), 'addendum_recital' => $this->plain($row['addendumRecitalParagraph'] ?? null)]);
        $version->save();

        foreach (array_values((array) $row['clauseIds']) as $i => $clauseId) {
            $clause = $clauses[(string) $clauseId];
            $version->clauses()->forceCreate(['position' => $i + 1, 'source_clause_id' => $clause->id, 'source_revision' => $clause->revision, 'title' => $clause->title,
                'body' => $clause->body, 'is_mandatory' => $clause->is_mandatory, 'is_protected' => $clause->is_protected]);
        }

        foreach ($this->parties($row) as $i => $label) {
            $version->parties()->forceCreate(['position' => $i + 1, 'label' => $label]);
        }

        $ids = ContractVariable::query()->where('tenant_id', $tenant->id)->whereIn('code', array_map(strval(...), (array) ($row['requiredVariables'] ?? [])))->pluck('id', 'code');

        foreach (array_values((array) ($row['requiredVariables'] ?? [])) as $i => $code) {
            $version->variables()->attach($ids[(string) $code], ['is_required' => true, 'position' => $i + 1]);
        }

        $version->forceFill(['status' => TemplateVersionStatus::Published, 'published_at' => Carbon::now()])->save();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function parties(array $row): array
    {
        $labels = [];

        foreach ((array) ($row['parties'] ?? []) as $label) {
            $text = trim((string) $label);

            if ($text !== '' && mb_strlen($text) <= 150) {
                $labels[] = $text;
            }
        }

        return $labels;
    }

    private function plain(mixed $value): ?string
    {
        [$text] = NodeText::plain((string) $value);

        return $text === '' ? null : $text;
    }

    private function text(mixed $value): ?string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return $text === '' ? null : $text;
    }
}
