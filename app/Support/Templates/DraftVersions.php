<?php

namespace App\Support\Templates;

use App\Enums\Templates\TemplateVersionStatus;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\TemplateVersion;
use Illuminate\Validation\ValidationException;

/**
 * The draft of a template, locked for writing. Call it inside the transaction
 * that writes. The editor sends the draft_revision it saw; a different one is
 * refused, so two people never overwrite each other silently (optimistic lock).
 */
class DraftVersions
{
    public static function lockedDraft(ContractTemplate $template, int $expectedRevision): TemplateVersion
    {
        ContractTemplate::query()->whereKey($template->getKey())->lockForUpdate()->firstOrFail();

        $draft = TemplateVersion::query()
            ->where('contract_template_id', $template->getKey())
            ->where('status', TemplateVersionStatus::Draft->value)
            ->lockForUpdate()
            ->first();

        if ($draft === null) {
            throw ValidationException::withMessages(['template' => config()->string('contract_templates.messages.no_draft')]);
        }

        if ($expectedRevision !== (int) $draft->draft_revision) {
            throw ValidationException::withMessages(['draft_revision' => config()->string('contract_templates.messages.draft_conflict')]);
        }

        return $draft;
    }
}
