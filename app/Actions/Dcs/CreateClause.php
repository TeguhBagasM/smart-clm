<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\Clause;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateClause
{
    public function __construct(private SyncClauseDocTypes $syncClauseDocTypes, private RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array{title: string, content: string, category: string|null, isMandatory: bool, tags: list<string>, applicableDocTypeCodes: list<string>}  $data
     */
    public function handle(User $actor, Tenant $tenant, array $data): Clause
    {
        return DB::transaction(function () use ($actor, $tenant, $data): Clause {
            $clause = new Clause;

            $clause->forceFill([
                'tenant_id' => $tenant->id,
                'title' => $data['title'],
                'content' => $data['content'],
                'category' => $data['category'] ?? 'General',
                'is_mandatory' => $data['isMandatory'],
                'tags' => $data['tags'],
                'is_active' => true,
                'created_by_id' => $actor->id,
            ])->save();

            $this->syncClauseDocTypes->handle($tenant, $clause, $data['applicableDocTypeCodes']);

            $this->recordAuditLog->handle($actor, 'dcs.clause.created', $clause, null, ['title' => $clause->title, 'category' => $clause->category], $tenant->id);

            return $clause;
        });
    }
}
