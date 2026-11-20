<?php

namespace App\Actions\Templates;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractTemplate;
use App\Models\Templates\ContractVariable;
use App\Models\Templates\TemplateClause;
use App\Models\Templates\TemplateParty;
use App\Models\Templates\TemplateVersion;
use App\Models\User;
use App\Support\Templates\DraftVersions;
use App\Support\Templates\PlainText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Writes the draft of a template in one go: its three paragraphs, its parties,
 * the library clauses in order (copied now, audit Q-T2) and the fields it asks
 * for. Only the draft can change, and the revision the editor saw must match.
 * Placeholders in the clause text are checked by the variable engine (C3).
 */
class SaveTemplateDraft
{
    private const PARAGRAPHS = ['opening_paragraph', 'closing_paragraph', 'addendum_recital'];

    public function __construct(private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array{expected_revision: int, opening_paragraph?: string|null, closing_paragraph?: string|null, addendum_recital?: string|null, parties: list<string>, clause_ids: list<int>, variables: list<array{id: int, required: bool}>}  $data
     */
    public function handle(User $actor, ContractTemplate $template, array $data): TemplateVersion
    {
        $parties = array_map(fn (string $label): string => trim($label), $data['parties']);
        $clauseIds = array_map(intval(...), $data['clause_ids']);
        $variableIds = array_map(fn (array $row): int => (int) $row['id'], $data['variables']);
        $this->guardLists($parties, $clauseIds, $variableIds);

        $paragraphs = [];

        foreach (self::PARAGRAPHS as $key) {
            $value = trim((string) ($data[$key] ?? ''));

            if ($value !== '') {
                PlainText::check($value, $key, config()->integer('contract_templates.limits.paragraph'));
            }

            $paragraphs[$key] = $value === '' ? null : $value;
        }

        return DB::transaction(function () use ($actor, $template, $data, $parties, $clauseIds, $paragraphs): TemplateVersion {
            $draft = DraftVersions::lockedDraft($template, (int) $data['expected_revision']);
            $clauses = $this->libraryClauses((int) $template->tenant_id, $clauseIds);
            $this->fieldVariables((int) $template->tenant_id, $data['variables']);
            $before = $this->summary($draft);

            TemplateClause::query()->where('contract_template_version_id', $draft->getKey())->delete();
            TemplateParty::query()->where('contract_template_version_id', $draft->getKey())->delete();
            $draft->variables()->detach();

            foreach ($clauseIds as $i => $id) {
                $clause = $clauses[$id];
                $draft->clauses()->forceCreate([
                    'position' => $i + 1, 'source_clause_id' => $clause->id, 'source_revision' => $clause->revision,
                    'title' => $clause->title, 'body' => $clause->body, 'is_mandatory' => $clause->is_mandatory, 'is_protected' => $clause->is_protected,
                ]);
            }

            foreach ($parties as $i => $label) {
                $draft->parties()->forceCreate(['position' => $i + 1, 'label' => $label]);
            }

            foreach ($data['variables'] as $i => $row) {
                $draft->variables()->attach((int) $row['id'], ['is_required' => (bool) $row['required'], 'position' => $i + 1]);
            }

            $draft->forceFill($paragraphs + ['draft_revision' => (int) $draft->draft_revision + 1, 'updated_by_id' => $actor->id])->save();
            $this->recordAuditLog->handle($actor, 'contract_templates.versions.drafted', $draft, $before, $this->summary($draft), (int) $template->tenant_id);

            return $draft;
        });
    }

    /**
     * @param  list<string>  $parties
     * @param  list<int>  $clauseIds
     * @param  list<int>  $variableIds
     */
    private function guardLists(array $parties, array $clauseIds, array $variableIds): void
    {
        $limits = config()->array('contract_templates.limits');
        $messages = config()->array('contract_templates.messages');

        foreach ($parties as $label) {
            if ($label === '' || mb_strlen($label) > $limits['party_label']) {
                throw ValidationException::withMessages(['parties' => $messages['party_invalid']]);
            }
        }

        if (count($parties) > $limits['parties_per_version'] || count($clauseIds) > $limits['clauses_per_version'] || count($variableIds) > $limits['variables_per_version']) {
            throw ValidationException::withMessages(['clause_ids' => $messages['too_many']]);
        }

        if (array_unique($clauseIds) !== $clauseIds || array_unique($variableIds) !== $variableIds) {
            throw ValidationException::withMessages(['clause_ids' => $messages['duplicate_item']]);
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, ContractClause>
     */
    private function libraryClauses(int $tenantId, array $ids): array
    {
        $found = ContractClause::query()->where('tenant_id', $tenantId)->whereIn('id', $ids)->get()->keyBy('id');

        if ($found->count() !== count($ids)) {
            throw ValidationException::withMessages(['clause_ids' => config()->string('contract_templates.messages.clause_missing')]);
        }

        foreach ($found as $clause) {
            if (! $clause->is_active) {
                throw ValidationException::withMessages(['clause_ids' => strtr(config()->string('contract_templates.messages.clause_inactive'), [':title' => $clause->title])]);
            }
        }

        return $found->all();
    }

    /**
     * @param  list<array{id: int, required: bool}>  $rows
     */
    private function fieldVariables(int $tenantId, array $rows): void
    {
        $found = ContractVariable::query()->where('tenant_id', $tenantId)->whereIn('id', array_column($rows, 'id'))->get();

        if ($found->count() !== count($rows)) {
            throw ValidationException::withMessages(['variables' => config()->string('contract_templates.messages.variable_missing')]);
        }

        foreach ($found as $variable) {
            if (! $variable->is_active) {
                throw ValidationException::withMessages(['variables' => strtr(config()->string('contract_templates.messages.variable_inactive'), [':code' => '{{'.$variable->code.'}}'])]);
            }
        }
    }

    /**
     * @return array{revision: int, clauses: int, parties: int, variables: int}
     */
    private function summary(TemplateVersion $draft): array
    {
        return [
            'revision' => (int) $draft->draft_revision,
            'clauses' => TemplateClause::query()->where('contract_template_version_id', $draft->getKey())->count(),
            'parties' => TemplateParty::query()->where('contract_template_version_id', $draft->getKey())->count(),
            'variables' => DB::table('contract_template_version_variables')->where('contract_template_version_id', $draft->getKey())->count(),
        ];
    }
}
