<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\Clause;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Changes only what the request supplies. A null applicableDocTypeCodes leaves
 * the attached types alone; an empty list makes the clause usable by all.
 */
class UpdateClause
{
    public function __construct(private SyncClauseDocTypes $syncClauseDocTypes, private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array{title: string|null, content: string|null, category: string|null, isMandatory: bool|null, tags: list<string>|null, applicableDocTypeCodes: list<string>|null}  $data
     */
    public function handle(User $actor, Clause $clause, array $data): Clause
    {
        return DB::transaction(function () use ($actor, $clause, $data): Clause {
            $before = ['title' => $clause->title, 'category' => $clause->category, 'is_mandatory' => $clause->is_mandatory];

            $clause->forceFill(array_filter([
                'title' => $data['title'],
                'content' => $data['content'],
                'category' => $data['category'],
                'is_mandatory' => $data['isMandatory'],
                'tags' => $data['tags'],
            ], fn (mixed $value): bool => $value !== null))->save();

            if ($data['applicableDocTypeCodes'] !== null) {
                $this->syncClauseDocTypes->handle($clause->tenant, $clause, $data['applicableDocTypeCodes']);
            }

            $this->recordAuditLog->handle($actor, 'dcs.clause.updated', $clause, $before, ['title' => $clause->title, 'category' => $clause->category, 'is_mandatory' => $clause->is_mandatory], $clause->tenant_id);

            return $clause;
        });
    }
}
