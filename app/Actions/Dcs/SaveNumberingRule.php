<?php

namespace App\Actions\Dcs;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Models\Dcs\NumberSequence;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Dcs\NumberingMask;
use Illuminate\Support\Facades\DB;

/**
 * Sets the mask of the tenant's default rule, or of one document type's own
 * override (created on first use). Only the mask is customisable; the counter's scope
 * follows the mask until the rule has numbered a document (see `NumberingMask::scopeFor`).
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

            $before = $rule->exists ? ['mask' => $rule->mask, 'scope_tokens' => $rule->scope_tokens] : null;

            // A rule that has already numbered documents keeps its counters; a new or unused one
            // takes the scope that fits the mask (new style: per document type and year).
            $unused = ! $rule->exists || ! NumberSequence::query()->where('rule_id', $rule->id)->exists();
            $scope = $unused ? NumberingMask::scopeFor($mask) : $rule->scope_tokens;

            $rule->forceFill([
                'tenant_id' => $tenant->id,
                'doc_type_id' => $type?->id,
                'name' => $rule->name ?? ($type === null ? 'Default ISO Rule' : 'Override'),
                'mask' => $mask,
                'scope_tokens' => $scope,
                'is_active' => true,
            ])->save();

            $this->recordAuditLog->handle($actor, 'dcs.numbering_rule.saved', $rule, $before, ['mask' => $mask, 'scope_tokens' => $scope, 'doc_type' => $type?->code], $tenant->id);

            return $rule;
        });
    }
}
