<?php

namespace App\Actions\Dcs;

use App\Models\Dcs\DocumentType;
use App\Models\Dcs\NumberingRule;
use App\Models\Tenant;
use App\Support\Dcs\DocumentTypeDefaults;
use App\Support\Dcs\NumberingMask;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Gives a tenant the default DCS configuration: the five standard document
 * types and the default numbering rule. Idempotent and never overwrites what a
 * tenant already has. Runs once per tenant (the first DCS request, or the
 * dcs:seed-defaults command), not on every request.
 */
class SeedDcsTenantDefaults
{
    public function __construct(private SyncDocTypeTemplate $syncDocTypeTemplate) {}

    public function handle(Tenant $tenant): void
    {
        try {
            DB::transaction(function () use ($tenant): void {
                foreach (DocumentTypeDefaults::types() as $definition) {
                    if (DocumentType::query()->where('tenant_id', $tenant->id)->where('code', $definition['code'])->exists()) {
                        continue;
                    }

                    $type = new DocumentType;

                    $type->forceFill([
                        'tenant_id' => $tenant->id,
                        'code' => $definition['code'],
                        'name' => $definition['name'],
                        'layout_style' => $definition['layout'],
                        'header_style' => $definition['layout']->defaultHeaderStyle(),
                        'letterhead' => $definition['letterhead'],
                        'document_level' => 'Internal',
                        'process_owner' => '',
                        'default_lanes' => [],
                        'is_active' => true,
                    ])->save();

                    $lanes = array_map(
                        fn (array $column): string => $column['label'],
                        array_merge(...array_column($definition['groups'], 'columns')),
                    );

                    $this->syncDocTypeTemplate->handle($tenant, $type, [
                        'sections' => $definition['sections'],
                        'groups' => $definition['groups'],
                        'lanes' => $lanes,
                    ]);
                }

                if (! NumberingRule::query()->where('tenant_id', $tenant->id)->whereNull('doc_type_id')->exists()) {
                    NumberingRule::query()->forceCreate([
                        'tenant_id' => $tenant->id,
                        'doc_type_id' => null,
                        'name' => 'Default ISO Rule',
                        'mask' => NumberingMask::DEFAULT_MASK,
                        'scope_tokens' => NumberingMask::DEFAULT_SCOPE_TOKENS,
                        'is_active' => true,
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request seeded the tenant first; its rows are what we wanted.
        }
    }
}
