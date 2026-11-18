<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Returns a document type to the tenant's default mask by deleting its override.
 * The default rule itself can never be removed this way. Does nothing when
 * there is no override.
 */
class RemoveNumberingOverride
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Tenant $tenant, DocumentType $type): void
    {
        DB::transaction(function () use ($actor, $tenant, $type): void {
            $override = NumberingRule::query()->where('tenant_id', $tenant->id)->where('doc_type_id', $type->id)->first();

            if ($override === null) {
                return;
            }

            $override->delete();

            $this->recordAuditLog->handle($actor, 'dcs.numbering_rule.override_removed', $override, ['mask' => $override->mask, 'doc_type' => $type->code], null, $tenant->id);
        });
    }
}
