<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\NumberingMask;
use Illuminate\Support\Facades\DB;

/**
 * Sets the mask of the tenant's default rule, or of one document type's own
 * override (created on first use). Overrides always keep the default scope
 * tokens: only the mask is customisable.
 */
class SaveNumberingRule
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(User $actor, Tenant $tenant, string $mask, ?DocumentType $type): NumberingRule
    {
        return DB::transaction(function () use ($actor, $tenant, $mask, $type): NumberingRule {
            $rule = NumberingRule::query()
                ->where('tenant_id', $tenant->id)
                ->where('doc_type_id', $type?->id)
                ->first() ?? new NumberingRule;

            $before = $rule->exists ? ['mask' => $rule->mask] : null;

            $rule->forceFill([
                'tenant_id' => $tenant->id,
                'doc_type_id' => $type?->id,
                'name' => $rule->name ?? ($type === null ? 'Default ISO Rule' : 'Override'),
                'mask' => $mask,
                'scope_tokens' => $rule->scope_tokens ?? NumberingMask::DEFAULT_SCOPE_TOKENS,
                'is_active' => true,
            ])->save();

            $this->recordAuditLog->handle($actor, 'dcs.numbering_rule.saved', $rule, $before, ['mask' => $mask, 'doc_type' => $type?->code], $tenant->id);

            return $rule;
        });
    }
}
