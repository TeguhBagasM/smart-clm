<?php

namespace App\Actions\Templates;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\Templates\TemplateVersionStatus;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\TemplateClause;
use App\Models\Templates\TemplateParty;
use App\Models\Templates\TemplateVersion;
use App\Models\User;
use App\Support\Templates\DraftVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishes the draft: it becomes the version new contracts use, frozen. The
 * version published before is retired in the same transaction, first, so the
 * one-published index is never broken. A draft needs at least one clause and
 * one party.
 */
class PublishTemplateVersion
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, ContractTemplate $template, int $expectedRevision): TemplateVersion
    {
        return DB::transaction(function () use ($actor, $template, $expectedRevision): TemplateVersion {
            $draft = DraftVersions::lockedDraft($template, $expectedRevision);

            if (! TemplateClause::query()->where('contract_template_version_id', $draft->getKey())->exists()) {
                throw ValidationException::withMessages(['clause_ids' => config()->string('contract_templates.messages.publish_needs_clause')]);
            }

            if (! TemplateParty::query()->where('contract_template_version_id', $draft->getKey())->exists()) {
                throw ValidationException::withMessages(['parties' => config()->string('contract_templates.messages.publish_needs_party')]);
            }

            $previous = TemplateVersion::query()
                ->where('contract_template_id', $template->getKey())
                ->where('status', TemplateVersionStatus::Published->value)
                ->lockForUpdate()
                ->first();

            if ($previous !== null) {
                $previous->forceFill(['status' => TemplateVersionStatus::Retired, 'retired_at' => now(), 'updated_by_id' => $actor->id])->save();
            }

            $draft->forceFill(['status' => TemplateVersionStatus::Published, 'published_at' => now(), 'published_by_id' => $actor->id, 'updated_by_id' => $actor->id])->save();
            $this->recordAuditLog->handle($actor, 'contract_templates.versions.published', $draft, null, ['version_no' => $draft->version_no, 'retired_version_no' => $previous?->version_no], (int) $template->tenant_id);

            return $draft;
        });
    }
}
