<?php

namespace App\Actions\Templates;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\Templates\TemplateVersionStatus;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\ContractVariable;
use App\Models\Templates\TemplateVersion;
use App\Models\Tenant;
use App\Support\MasterData\Legacy\ImportReport;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Takes back what the library import brought in. Drafts, clauses and fields
 * that the import created are removed; seeded defaults only lose their Node key.
 * A published version is frozen by design (C1), so a template that was published
 * by the import cannot be removed: the run is refused and the report names it
 * (hide it instead). All or nothing.
 */
class RollbackLegacyTemplateLibrary
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(Tenant $tenant, bool $dryRun): ImportReport
    {
        $report = new ImportReport;
        DB::beginTransaction();

        try {
            $templates = ContractTemplate::query()->where('tenant_id', $tenant->id)->whereNotNull('legacy_key')->get();
            $clauses = ContractClause::query()->where('tenant_id', $tenant->id)->whereNotNull('legacy_key')->get();
            $variables = ContractVariable::query()->where('tenant_id', $tenant->id)->whereNotNull('legacy_key')->get();

            foreach ($templates as $template) {
                if (! $template->is_system && TemplateVersion::query()->where('contract_template_id', $template->id)->where('status', TemplateVersionStatus::Published->value)->exists()) {
                    $report->error("templates:{$template->legacy_key}", "\"{$template->name}\" sudah terbit dan tidak bisa dihapus. Sembunyikan saja.");
                }
            }

            foreach ($clauses as $clause) {
                if (! $clause->is_system && $clause->copies()->exists()) {
                    $report->error("clauses:{$clause->legacy_key}", "pasal \"{$clause->title}\" masih disalin ke templat");
                }
            }

            foreach ($variables as $variable) {
                if (! $variable->is_system && $variable->versions()->exists()) {
                    $report->error("variables:{$variable->legacy_key}", "kolom isian {$variable->code} masih dipakai templat");
                }
            }

            if ($report->errors === []) {
                $this->undo($templates, $clauses, $variables, $report);
            }

            if (! $dryRun && $report->errors === [] && $report->counts !== []) {
                $this->recordAuditLog->handle(null, 'contract_templates.import.rolled_back', $tenant, null, ['counts' => $report->counts], $tenant->id);
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun || $report->errors !== [] ? DB::rollBack() : DB::commit();

        return $report;
    }

    /**
     * @param  iterable<ContractTemplate>  $templates
     * @param  iterable<ContractClause>  $clauses
     * @param  iterable<ContractVariable>  $variables
     */
    private function undo(iterable $templates, iterable $clauses, iterable $variables, ImportReport $report): void
    {
        foreach ($templates as $template) {
            if ($template->is_system) {
                $template->forceFill(['legacy_key' => null])->save();
                $report->count('contract_templates', 'released');

                continue;
            }

            TemplateVersion::query()->where('contract_template_id', $template->id)->get()->each->delete();
            $template->delete();
            $report->count('contract_templates', 'removed');
        }

        foreach ($clauses as $clause) {
            if ($clause->is_system) {
                $clause->forceFill(['legacy_key' => null])->save();
                $report->count('contract_clauses', 'released');

                continue;
            }

            $clause->delete();
            $report->count('contract_clauses', 'removed');
        }

        foreach ($variables as $variable) {
            if ($variable->is_system) {
                $variable->forceFill(['legacy_key' => null])->save();
                $report->count('contract_variables', 'released');

                continue;
            }

            $variable->delete();
            $report->count('contract_variables', 'removed');
        }
    }
}
