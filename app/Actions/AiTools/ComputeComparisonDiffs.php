<?php

namespace App\Actions\AiTools;

use App\Enums\AiTools\ChangeKind;
use App\Models\AiTools\ComparisonRow;
use App\Models\AiTools\DocumentComparison;
use App\Support\Comparison\WordDiff;

/**
 * Computes the red/green highlighting of every row, in code. A paired row whose text is the same
 * (ignoring whitespace) becomes "Tetap"; an article on one side only is entirely removed or added.
 */
class ComputeComparisonDiffs
{
    public function __construct(private readonly WordDiff $diff) {}

    public function handle(DocumentComparison $comparison): void
    {
        $comparison->rows()->with(['articleA', 'articleB'])->orderBy('ordinal')->each(function (ComparisonRow $row): void {
            $a = $this->text($row->articleA?->heading, $row->articleA?->body, $row->articleA !== null);
            $b = $this->text($row->articleB?->heading, $row->articleB?->body, $row->articleB !== null);

            $row->forceFill([
                'diff' => $this->diff->diff($a, $b),
                'change_kind' => match (true) {
                    $row->articleA === null => ChangeKind::Added,
                    $row->articleB === null => ChangeKind::Removed,
                    $this->diff->same($a, $b) => ChangeKind::Unchanged,
                    default => ChangeKind::Changed,
                },
            ])->save();
        });
    }

    private function text(?string $heading, ?string $body, bool $exists): string
    {
        return $exists ? trim(($heading !== null && $heading !== 'Pembukaan' ? $heading."\n" : '').$body) : '';
    }
}
