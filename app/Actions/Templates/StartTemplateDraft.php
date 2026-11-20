<?php

namespace App\Actions\Templates;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\Templates\TemplateVersionStatus;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\TemplateVersion;
use App\Models\User;
use App\Support\Templates\CopiesTemplateVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starts a new draft from the published version (or the latest one when none
 * is published), with the next version number. A published version is never
 * edited: this is the way to change it. One draft at a time.
 */
class StartTemplateDraft
{
    public function __construct(private RecordAuditLog $recordAuditLog, private CopiesTemplateVersion $copies) {}

    public function handle(User $actor, ContractTemplate $template): TemplateVersion
    {
        return DB::transaction(function () use ($actor, $template): TemplateVersion {
            ContractTemplate::query()->whereKey($template->getKey())->lockForUpdate()->firstOrFail();

            $versions = TemplateVersion::query()->where('contract_template_id', $template->getKey());

            if ((clone $versions)->where('status', TemplateVersionStatus::Draft->value)->exists()) {
                throw ValidationException::withMessages(['template' => config()->string('contract_templates.messages.draft_exists')]);
            }

            $source = (clone $versions)->where('status', TemplateVersionStatus::Published->value)->first()
                ?? (clone $versions)->orderByDesc('version_no')->firstOrFail();
            $next = (int) (clone $versions)->max('version_no') + 1;

            $draft = new TemplateVersion;
            $draft->forceFill(['contract_template_id' => $template->getKey(), 'version_no' => $next, 'status' => TemplateVersionStatus::Draft, 'draft_revision' => 1, 'created_by_id' => $actor->id, 'updated_by_id' => $actor->id])->save();
            $this->copies->copy($source, $draft);
            $this->recordAuditLog->handle($actor, 'contract_templates.versions.draft_started', $draft, null, ['version_no' => $next, 'copied_from_version_no' => $source->version_no], (int) $template->tenant_id);

            return $draft;
        });
    }
}
