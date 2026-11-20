<?php

namespace App\Support\Templates;

use App\Models\Templates\ContractVariable;
use App\Models\Templates\TemplateVersion;

/**
 * Copies the wording, clauses, parties and fields of one version into an empty
 * draft. Used when a new draft starts from a published version and when a
 * template is duplicated. Each clause copy keeps where it came from.
 */
class CopiesTemplateVersion
{
    public function copy(TemplateVersion $from, TemplateVersion $to): void
    {
        $from->loadMissing(['clauses', 'parties', 'variables']);

        $to->forceFill([
            'opening_paragraph' => $from->opening_paragraph,
            'closing_paragraph' => $from->closing_paragraph,
            'addendum_recital' => $from->addendum_recital,
        ])->save();

        foreach ($from->clauses as $clause) {
            $to->clauses()->forceCreate([
                'position' => $clause->position, 'source_clause_id' => $clause->source_clause_id, 'source_revision' => $clause->source_revision,
                'title' => $clause->title, 'body' => $clause->body, 'is_mandatory' => $clause->is_mandatory, 'is_protected' => $clause->is_protected,
            ]);
        }

        foreach ($from->parties as $party) {
            $to->parties()->forceCreate(['position' => $party->position, 'label' => $party->label]);
        }

        $to->variables()->attach($from->variables->mapWithKeys(fn (ContractVariable $variable): array => [
            $variable->id => [
                'is_required' => (bool) $variable->pivot?->getAttribute('is_required'),
                'position' => (int) $variable->pivot?->getAttribute('position'),
            ],
        ])->all());
    }
}
