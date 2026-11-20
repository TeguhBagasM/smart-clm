<?php

namespace App\Actions\Templates;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\Templates\TemplateVersionStatus;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\TemplateVersion;
use App\Models\User;
use App\Support\Templates\CopiesTemplateVersion;
use App\Support\Templates\UniqueLibraryName;
use Illuminate\Support\Facades\DB;

/**
 * Makes a new template from an existing one: the wording of its published
 * version (or its latest one) becomes the draft 1 of the copy, under a free name
 * such as "NDA (salinan)". The source is not touched.
 */
class DuplicateContractTemplate
{
    public function __construct(private RecordAuditLog $recordAuditLog, private CopiesTemplateVersion $copies) {}

    public function handle(User $actor, ContractTemplate $source): ContractTemplate
    {
        return DB::transaction(function () use ($actor, $source): ContractTemplate {
            $name = $this->freeName($source);
            $template = new ContractTemplate;
            $template->forceFill([
                'tenant_id' => $source->tenant_id, 'contract_category_id' => $source->contract_category_id, 'module' => $source->module,
                'name' => $name, 'description' => $source->description, 'is_active' => true, 'is_system' => false,
                'created_by_id' => $actor->id, 'updated_by_id' => $actor->id,
            ])->save();

            $from = $source->currentVersion ?? $source->versions()->firstOrFail();
            $version = new TemplateVersion;
            $version->forceFill(['contract_template_id' => $template->id, 'version_no' => 1, 'status' => TemplateVersionStatus::Draft, 'draft_revision' => 1, 'created_by_id' => $actor->id, 'updated_by_id' => $actor->id])->save();
            $this->copies->copy($from, $version);
            $this->recordAuditLog->handle($actor, 'contract_templates.templates.duplicated', $template, null, ['name' => $name, 'copied_from_id' => $source->id], (int) $source->tenant_id);

            return $template;
        });
    }

    private function freeName(ContractTemplate $source): string
    {
        $messages = config()->array('contract_templates.messages');
        $name = $source->name.$messages['copy_suffix'];

        for ($n = 2; UniqueLibraryName::taken(ContractTemplate::class, (int) $source->tenant_id, 'name', $name, null); $n++) {
            $name = $source->name.strtr($messages['copy_suffix_numbered'], [':n' => (string) $n]);
        }

        return $name;
    }
}
