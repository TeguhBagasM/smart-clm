<?php

namespace App\Actions\Templates;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\MasterData\DocumentModule;
use App\Enums\Templates\TemplateVersionStatus;
use App\Enums\Templates\VariableType;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\ContractVariable;
use App\Models\Templates\TemplateVersion;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gives a company the default fields, clauses and templates
 * (config/contract_templates.php). Idempotent: adds only a field whose code and
 * name, a clause whose Node id (legacy_key) and title, or a template whose Node
 * id and name the company does not have yet (active or not), so nothing changed,
 * renamed or hidden is touched. A new
 * template gets a DRAFT version 1 with copies of its clauses, its parties and
 * its fields, for an administrator to review and publish. Needs the categories
 * of the master data defaults; a missing one is reported, not created.
 *
 * @phpstan-type SeedReport array{added: array{variables: list<string>, clauses: list<string>, templates: list<string>}, warnings: list<string>}
 */
class SeedTenantTemplateLibrary
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @return SeedReport
     */
    public function handle(Tenant $tenant, bool $dryRun = false): array
    {
        $report = ['added' => ['variables' => [], 'clauses' => [], 'templates' => []], 'warnings' => []];
        $defaults = config()->array('contract_templates.defaults');
        DB::beginTransaction();

        try {
            $categories = $tenant->contractCategories()->get()->keyBy(fn (ContractCategory $category): string => mb_strtolower($category->name));
            $this->variables($tenant, $defaults['variables'], $report);
            $clauses = $this->clauses($tenant, $defaults['clauses'], $categories, $report);
            $this->templates($tenant, $defaults['templates'], $categories, $clauses, $report);

            if (! $dryRun && array_filter($report['added']) !== []) {
                $this->recordAuditLog->handle(null, 'contract_templates.defaults.seeded', $tenant, null, ['added' => array_filter($report['added'])], $tenant->id);
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $report;
    }

    /**
     * @param  list<array{code: string, name: string, type: string, description?: string, default?: string, source?: string}>  $rows
     * @param  SeedReport  $report
     */
    private function variables(Tenant $tenant, array $rows, array &$report): void
    {
        $existing = $tenant->contractVariables()->get();
        $taken = $existing->flatMap(fn (ContractVariable $v): array => ['c:'.mb_strtolower($v->code), 'n:'.mb_strtolower($v->name)])->all();
        $order = (int) $existing->max('sort_order');

        foreach ($rows as $row) {
            if (in_array('c:'.mb_strtolower($row['code']), $taken, true) || in_array('n:'.mb_strtolower($row['name']), $taken, true)) {
                continue;
            }

            (new ContractVariable)->forceFill([
                'tenant_id' => $tenant->id, 'code' => $row['code'], 'name' => $row['name'], 'value_type' => VariableType::from($row['type']),
                'description' => $row['description'] ?? null, 'default_value' => $row['default'] ?? null, 'source_field' => $row['source'] ?? null,
                'sort_order' => ++$order, 'is_active' => true, 'is_system' => true,
            ])->save();
            $report['added']['variables'][] = $row['code'];
        }
    }

    /**
     * @param  array<string, array{title: string, category: string, mandatory: bool, protected: bool, body: string}>  $rows
     * @param  Collection<string, ContractCategory>  $categories
     * @param  SeedReport  $report
     * @return array<string, ContractClause> by Node id, new or already there
     */
    private function clauses(Tenant $tenant, array $rows, Collection $categories, array &$report): array
    {
        $existing = $tenant->contractClauses()->get();
        $found = [];

        foreach ($rows as $key => $row) {
            $clause = $existing->first(fn (ContractClause $c): bool => $c->legacy_key === $key || mb_strtolower($c->title) === mb_strtolower($row['title']));

            if ($clause !== null) {
                $found[$key] = $clause;

                continue;
            }

            $category = $categories->get(mb_strtolower($row['category']));

            if ($category === null) {
                $report['warnings'][] = "Pasal \"{$row['title']}\": kategori \"{$row['category']}\" tidak ada, dilewati.";

                continue;
            }

            $clause = new ContractClause;
            $clause->forceFill([
                'tenant_id' => $tenant->id, 'contract_category_id' => $category->id, 'title' => $row['title'], 'body' => $row['body'],
                'is_mandatory' => $row['mandatory'], 'is_protected' => $row['protected'], 'revision' => 1, 'is_active' => true, 'is_system' => true, 'legacy_key' => $key,
            ])->save();
            $found[$key] = $clause;
            $report['added']['clauses'][] = $row['title'];
        }

        return $found;
    }

    /**
     * @param  list<array{key: string, name: string, category: string, description: string, clauses: list<string>, variables: list<string>, parties: list<string>}>  $rows
     * @param  Collection<string, ContractCategory>  $categories
     * @param  array<string, ContractClause>  $clauses
     * @param  SeedReport  $report
     */
    private function templates(Tenant $tenant, array $rows, Collection $categories, array $clauses, array &$report): void
    {
        $taken = $tenant->contractTemplates()->get(['name', 'legacy_key'])->flatMap(fn (ContractTemplate $t): array => ['n:'.mb_strtolower($t->name), 'k:'.$t->legacy_key])->all();
        $variables = $tenant->contractVariables()->get()->keyBy(fn (ContractVariable $v): string => mb_strtolower($v->code));

        foreach ($rows as $row) {
            $category = $categories->get(mb_strtolower($row['category']));

            if (in_array('n:'.mb_strtolower($row['name']), $taken, true) || in_array('k:'.$row['key'], $taken, true)) {
                continue;
            }

            if ($category === null) {
                $report['warnings'][] = "Template \"{$row['name']}\": kategori \"{$row['category']}\" tidak ada, dilewati.";

                continue;
            }

            $template = new ContractTemplate;
            $template->forceFill([
                'tenant_id' => $tenant->id, 'contract_category_id' => $category->id, 'name' => $row['name'], 'description' => $row['description'],
                'module' => $category->is_employee_key ? DocumentModule::Employee : DocumentModule::External, 'is_active' => true, 'is_system' => true, 'legacy_key' => $row['key'],
            ])->save();

            $version = new TemplateVersion;
            $version->forceFill(['contract_template_id' => $template->id, 'version_no' => 1, 'status' => TemplateVersionStatus::Draft, 'draft_revision' => 1])->save();

            foreach (array_values(array_filter(array_map(fn (string $key): ?ContractClause => $clauses[$key] ?? null, $row['clauses']))) as $i => $clause) {
                $version->clauses()->forceCreate(['position' => $i + 1, 'source_clause_id' => $clause->id, 'source_revision' => $clause->revision,
                    'title' => $clause->title, 'body' => $clause->body, 'is_mandatory' => $clause->is_mandatory, 'is_protected' => $clause->is_protected]);
            }

            foreach ($row['parties'] as $i => $label) {
                $version->parties()->forceCreate(['position' => $i + 1, 'label' => $label]);
            }

            foreach ($row['variables'] as $i => $code) {
                $variable = $variables->get(mb_strtolower($code));
                $variable === null
                    ? $report['warnings'][] = "Template \"{$row['name']}\": kolom isian \"{$code}\" tidak ada, dilewati."
                    : $version->variables()->attach($variable->id, ['is_required' => true, 'position' => $i + 1]);
            }

            $report['added']['templates'][] = $row['name'];
        }
    }
}
