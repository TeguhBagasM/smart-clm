<?php

namespace App\Actions\Templates;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\MasterData\DocumentModule;
use App\Enums\Templates\TemplateVersionStatus;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\TemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MasterData\OwnedIds;
use App\Support\Templates\UniqueLibraryName;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a contract template, or changes its identity (name, description,
 * category). A new template starts with an empty draft version 1. Its module
 * follows its category: the employee category makes an employee template.
 * Wording changes go through the draft (SaveTemplateDraft), never through here.
 */
class SaveContractTemplate
{
    private const AUDITED = ['name', 'description', 'contract_category_id', 'module'];

    public function __construct(private RecordAuditLog $recordAuditLog, private OwnedIds $ownedIds) {}

    /**
     * @param  array{name: string, description?: string|null, category_id: int}  $data
     */
    public function handle(User $actor, Tenant $tenant, array $data, ?ContractTemplate $template = null): ContractTemplate
    {
        if ($template !== null && $template->tenant_id !== $tenant->id) {
            throw (new ModelNotFoundException)->setModel(ContractTemplate::class);
        }

        $categoryId = $this->ownedIds->ensure(ContractCategory::class, $tenant, [(int) $data['category_id']], 'category_id', config()->string('contract_templates.messages.category_foreign'))[0];
        $category = ContractCategory::query()->findOrFail($categoryId);
        $name = trim($data['name']);
        UniqueLibraryName::ensure(ContractTemplate::class, $tenant->id, 'name', $name, $template, 'name', config()->string('contract_templates.messages.name_taken'));
        $description = trim((string) ($data['description'] ?? ''));

        $fields = [
            'name' => $name, 'description' => $description === '' ? null : $description, 'contract_category_id' => $categoryId,
            'module' => $category->is_employee_key ? DocumentModule::Employee : DocumentModule::External,
        ];

        return DB::transaction(function () use ($actor, $tenant, $fields, $template): ContractTemplate {
            $isNew = $template === null;
            $before = $isNew ? null : $template->only(self::AUDITED);
            $template ??= new ContractTemplate;

            if ($isNew) {
                $template->forceFill(['tenant_id' => $tenant->id, 'is_active' => true, 'is_system' => false, 'created_by_id' => $actor->id]);
            }

            $template->forceFill($fields + ['updated_by_id' => $actor->id])->save();

            if ($isNew) {
                $version = new TemplateVersion;
                $version->forceFill(['contract_template_id' => $template->id, 'version_no' => 1, 'status' => TemplateVersionStatus::Draft, 'draft_revision' => 1, 'created_by_id' => $actor->id, 'updated_by_id' => $actor->id])->save();
            }

            $this->recordAuditLog->handle($actor, 'contract_templates.templates.'.($isNew ? 'created' : 'updated'), $template, $before, $template->only(self::AUDITED), $tenant->id);

            return $template;
        });
    }
}
