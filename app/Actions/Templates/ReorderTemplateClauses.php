<?php

namespace App\Actions\Templates;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\TemplateClause;
use App\Models\Templates\TemplateVersion;
use App\Models\User;
use App\Support\Templates\DraftVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets the order of the clauses of the draft. The list must name every clause
 * of the draft exactly once (ids of the draft's clauses, not library ids).
 */
class ReorderTemplateClauses
{
    private const OFFSET = 10000;

    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  list<int>  $clauseIds
     */
    public function handle(User $actor, ContractTemplate $template, int $expectedRevision, array $clauseIds): TemplateVersion
    {
        return DB::transaction(function () use ($actor, $template, $expectedRevision, $clauseIds): TemplateVersion {
            $draft = DraftVersions::lockedDraft($template, $expectedRevision);
            $current = $draft->clauses()->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $wanted = collect($clauseIds)->map(fn ($id): int => (int) $id)->sort()->values()->all();

            if ($current !== $wanted) {
                throw ValidationException::withMessages(['clause_ids' => config()->string('contract_templates.messages.order_incomplete')]);
            }

            // Two steps, so the unique (version, position) index never meets a duplicate.
            TemplateClause::query()->where('contract_template_version_id', $draft->getKey())->update(['position' => DB::raw('"position" + '.self::OFFSET)]);

            foreach ($clauseIds as $i => $id) {
                TemplateClause::query()->whereKey((int) $id)->update(['position' => $i + 1]);
            }

            $draft->forceFill(['draft_revision' => (int) $draft->draft_revision + 1, 'updated_by_id' => $actor->id])->save();
            $this->recordAuditLog->handle($actor, 'contract_templates.versions.reordered', $draft, null, ['order' => $clauseIds], (int) $template->tenant_id);

            return $draft;
        });
    }
}
