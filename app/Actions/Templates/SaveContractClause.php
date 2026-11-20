<?php

namespace App\Actions\Templates;

use App\Actions\Audit\RecordAuditLog;
use App\Models\MasterData\ContractCategory;
use App\Models\Templates\ContractClause;
use App\Models\Templates\ContractVariable;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MasterData\OwnedIds;
use App\Support\Templates\PlaceholderGuard;
use App\Support\Templates\PlainText;
use App\Support\Templates\UniqueLibraryName;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Creates or changes a clause of the company's library. A change to the title,
 * body, category or the two flags raises `revision` by one; templates keep their
 * own copy, so they show "updated" instead of changing. The body is plain text
 * and titles are unique per company.
 */
class SaveContractClause
{
    private const AUDITED = ['title', 'body', 'contract_category_id', 'is_mandatory', 'is_protected', 'revision'];

    private const CONTENT = ['title', 'body', 'contract_category_id', 'is_mandatory', 'is_protected'];

    public function __construct(private RecordAuditLog $recordAuditLog, private OwnedIds $ownedIds) {}

    /**
     * @param  array{title: string, body: string, category_id: int, is_mandatory?: bool, is_protected?: bool}  $data
     */
    public function handle(User $actor, Tenant $tenant, array $data, ?ContractClause $clause = null): ContractClause
    {
        if ($clause !== null && $clause->tenant_id !== $tenant->id) {
            throw (new ModelNotFoundException)->setModel(ContractClause::class);
        }

        $body = trim($data['body']);
        PlainText::check($body, 'body', config()->integer('contract_templates.limits.clause_body'));
        $codes = [];

        foreach (ContractVariable::query()->where('tenant_id', $tenant->id)->where('is_active', true)->get() as $variable) {
            $codes[] = $variable->code;
        }

        $known = PlaceholderGuard::known($codes);
        PlaceholderGuard::ensureUsable($body, $known, 'body', config()->string('contract_templates.messages.placeholder_unknown'));
        $categoryId = $this->ownedIds->ensure(ContractCategory::class, $tenant, [(int) $data['category_id']], 'category_id', config()->string('contract_templates.messages.category_foreign'))[0];
        $title = trim($data['title']);
        UniqueLibraryName::ensure(ContractClause::class, $tenant->id, 'title', $title, $clause, 'title', config()->string('contract_templates.messages.title_taken'));

        $fields = [
            'title' => $title, 'body' => $body, 'contract_category_id' => $categoryId,
            'is_mandatory' => (bool) ($data['is_mandatory'] ?? false), 'is_protected' => (bool) ($data['is_protected'] ?? false),
        ];

        return DB::transaction(function () use ($actor, $tenant, $fields, $clause): ContractClause {
            $isNew = $clause === null;
            $before = $isNew ? null : $clause->only(self::AUDITED);
            $clause ??= new ContractClause;

            if ($isNew) {
                $clause->forceFill(['tenant_id' => $tenant->id, 'is_active' => true, 'is_system' => false, 'revision' => 1, 'created_by_id' => $actor->id]);
            }

            $clause->forceFill($fields + ['updated_by_id' => $actor->id]);

            if (! $isNew && $clause->isDirty(self::CONTENT)) {
                $clause->revision = (int) $clause->getOriginal('revision') + 1;
            }

            $clause->save();
            $this->recordAuditLog->handle($actor, 'contract_templates.clauses.'.($isNew ? 'created' : 'updated'), $clause, $before, $clause->only(self::AUDITED), $tenant->id);

            return $clause;
        });
    }
}
